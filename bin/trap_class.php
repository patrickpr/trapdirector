<?php


class Trap
{
	// Configuration files a dirs
	protected $icingaweb2_etc; //< Icinga etc path	
	protected $trap_module_config; //< config.ini of module	
	protected $icingaweb2_ressources; //< resources.ini of icingaweb2
	// Options from config.ini
	protected $snmptranslate='/usr/bin/snmptranslate';
	protected $snmptranslate_dirs='/usr/share/icingaweb2/modules/trapdirector/mibs:/usr/share/snmp/mibs';
	
	protected $icinga2cmd='/var/run/icinga2/cmd/icinga2.cmd';
	
	// Logs
	protected $debug_level;  // 0=No output 1=critical 2=warning 3=trace 4=ALL
	protected $debug_file="/tmp/trapdebug.txt";	
	// Databases
	protected $trapDB; //< trap database
	protected $idoDB; //< ido database
	
	// Trap received data
	protected $receivingHost;
	public $trap_data=array();
	public $trap_data_ext=array();
	
	public function __construct($etc_dir='/etc/icingaweb2',$dbg_level=4)
	{
		$this->icingaweb2_etc=$etc_dir;
		$this->trap_module_config=$this->icingaweb2_etc."/modules/trapdirector/config.ini";		
		$this->icingaweb2_ressources=$this->icingaweb2_etc."/resources.ini";
		
		$this->getOptions();
		
		$this->debug_level=$dbg_level;
		$this->trap_data=array(
			'source_ip'	=> 'unknown',
			'source_port'	=> 'unknown',
			'destination_ip'	=> 'unknown',
			'destination_port'	=> 'unknown',
			'trap_oid'	=> 'unknown',
		);
	}
	
	protected function getOptions()
	{
		// TODO set options in config.ini
		// See 'options from config.ini above
	}
	
	/** Send log. Throws exception on critical error
	*	@param	string $message Message to log
	*	@param	int $level 1=critical 2=warning 3=trace 4=debug
	*	@param  int $destination (Not implemented)
	*	@return None
	**/	
	public function trapLog( $message, $level, $destination )
	{	// TODO : add date/time for file
		if ($this->debug_level >= $level) 
		{
			file_put_contents ($this->debug_file, 
				'[File '.__FILE__ .' # line:'.__LINE__.'] '.$message . "\n", FILE_APPEND);
		}
		if ($level == 1)
		{
			throw new Exception($message);
		}
	}

	/** Connects to trapdb 
	*	@return PDO connection
	*/
	public function db_connect_trap() 
	{
		if ($this->trapDB != null) { return $this->trapDB; }
		$this->trapDB=$this->db_connect('traps');
		return $this->trapDB;
	}

	/** Connects to idodb 
	*	@return PDO connection
	*/
	public function db_connect_ido() 
	{
		if ($this->idoDB != null) { return $this->idoDB; }
		$this->idoDB=$this->db_connect('ido');
		return $this->idoDB;
	}	
	
	/** connects to database named by parameter
	*	@param string database : 'traps' for traps database, 'ido' for ido database
	*	@return PDO connection
	**/
	protected function db_connect($database) {
		$confarray=$this->get_database($database);
		//	$dsn = 'mysql:dbname=traps;host=127.0.0.1';
		$dsn= $confarray[0].':dbname='.$confarray[2].';host='.$confarray[1];
		$user = $confarray[3];
		$password = $confarray[4];
		try {
			$dbh = new PDO($dsn, $user, $password);
		} catch (PDOException $e) {
			$this->trapLog('Connection failed : ' . $e->getMessage(),1,0);
		}
		return $dbh;
	}

	/** Get database connexion options
	*	@param string database : 'traps' for traps database, 'ido' for ido database
	*	@return array( DB type (mysql, pgsql.) , db_host, database name , db_user, db_pass)
	**/
	protected function get_database($database) {

		$trap_config=parse_ini_file($this->trap_module_config,true);
		if ($trap_config == false) 
		{
			$this->trapLog("Error reading ini file : ".$this->trap_module_config,1,0); 
			exit(1);
		}
		if ($database == 'traps')
		{
			if (!isset($trap_config['config']['database'])) 
			{
				$this->trapLog("No Config/database in config file: ".$this->trap_module_config,1,0); 
				exit(1);
			}
			$db_name=$trap_config['config']['database'];
		} 
		else if ($database == 'ido')
		{
			if (!isset($trap_config['config']['IDOdatabase'])) 
			{
				$this->trapLog("No Config/IDOdatabase in config file: ".$this->trap_module_config,1,0); 
				exit(1);
			}
			$db_name=$trap_config['config']['IDOdatabase'];		
		}
		else
		{
			$this->trapLog("Unknown database type : ".$database,1,0); 
			exit(1);		
		}	
		$this->trapLog("Found database in config file: ".$db_name,3,0); 
		$db_config=parse_ini_file($this->icingaweb2_ressources,true);
		if ($db_config == false) 
		{
			$this->trapLog("Error reading ini file : ".$this->icingaweb2_ressources,1,0); 
			exit(1);
		}
		if (!isset($db_config[$db_name])) 
		{
			$this->trapLog("No Config/database in config file: ".$this->icingaweb2_ressources,1,0); 
			exit(1);
		}
		$db_type=$db_config[$db_name]['db'];
		$db_host=$db_config[$db_name]['host'];
		$db_sql_name=$db_config[$db_name]['dbname'];
		$db_user=$db_config[$db_name]['username'];
		$db_pass=$db_config[$db_name]['password'];	
		$this->trapLog( "$db_type $db_host $db_sql_name $db_user $db_pass",3,0); 
		return array($db_type,$db_host,$db_sql_name,$db_user,$db_pass);
	}	
	
	/** read data from stream
	*	@param $stream input stream, defaults to "php://stdin"
	*	@return array trap data
	*/
	public function read_trap($stream='php://stdin')
	{
		//Read data from snmptrapd from stdin
		$input_stream=fopen($stream, 'r');

		if ($input_stream==FALSE)
		{
			$this->trapLog("Error reading stdin !",1,0); 
		}

		// line 1 : host
		$this->receivingHost=chop(fgets($input_stream));
		if ($this->receivingHost == FALSE)
		{
			$this->trapLog("Error reading Host !",1,0); 
		}
		// line 2 IP:port=>IP:port
		$IP=chop(fgets($input_stream));
		if ($IP == FALSE)
		{
			$this->trapLog("Error reading IP !",1,0); 
		}
		$ret_code=preg_match('/.DP: \[(.*)\]:(.*)->\[(.*)\]:(.*)/',$IP,$matches);
		if ($ret_code==0 || $ret_code==FALSE) 
		{
			$this->trapLog('Error parsing IP : '.$IP,2,0);
		} 
		else 
		{		
			$this->trap_data['source_ip']=$matches[1];
			$this->trap_data['destination_ip']=$matches[3];
			$this->trap_data['source_port']=$matches[2];
			$this->trap_data['destination_port']=$matches[4];
		}

		$trap_vars=array();
		while (($vars=chop(fgets($input_stream))) !=FALSE)
		{
			$ret_code=preg_match('/^([^ ]+) (.*)$/',$vars,$matches);
			if ($ret_code==0 || $ret_code==FALSE) 
			{
				$this->trapLog('No match on trap data : '.$vars,2,0);
			} else 
			{
				if ($matches[1]=='.1.3.6.1.6.3.1.1.4.1.0')
				{
					$this->trap_data['trap_oid']=$matches[2];				
				}
				else
				{
					$object= new stdClass;
					$object->oid =$matches[1];
					$object->value = $matches[2];
					array_push($this->trap_data_ext,$object);
				}
			}
		}

		if ($this->trap_data['trap_oid']=='unknown') 
		{
			$this->trapLog('no trap oid found',1,0);
		} 

		// Translate oids. (TODO : maybe in separate function)
		echo $this->trap_data['trap_oid']."\n";
		$retArray=$this->translateOID($this->trap_data['trap_oid']);
		if ($retArray != null)
		{
			$this->trap_data['trap_name']=$retArray['trap_name'];
			$this->trap_data['trap_name_mib']=$retArray['trap_name_mib'];
		}
		foreach ($this->trap_data_ext as $key => $val)
		{
			$retArray=$this->translateOID($val->oid);
			if ($retArray != null)
			{
				$this->trap_data_ext[$key]->oid_name=$retArray['trap_name'];
				$this->trap_data_ext[$key]->oid_name_mib=$retArray['trap_name_mib'];
			}			
		}
		

		$this->trap_data['status']= 'waiting';
		
		return $this->trap_data;
	}

	/** Translate oid into array(MIB,Name)
	* @param $ois oid to translate
	* @return null if not found or array(MIB,Name)
	*/
	public function translateOID($oid)
	{
		// Try to get oid name from snmptranslate
		$translate=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
				' '.$oid,$translate_output);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code==0 || $ret_code==FALSE) {
			return NULL;
		} else {
			return array('trap_name_mib'=>$matches[1],'trap_name'=>$matches[2]);
		}		
	}

	/** Write trap data to trap database
	*/
	public function writeTrapToDB()
	{
		$db_conn=$this->db_connect_trap();
		
		$insert_col='';
		$insert_val='';
		// add date time
		$this->trap_data['date_received'] = date("Y-m-d H:i:s");

		$firstcol=1;
		foreach ($this->trap_data as $col => $val)
		{
			if ($firstcol==0) 
			{
				$insert_col .=',';
				$insert_val .=',';
			}
			$insert_col .= ' `' . $col .'` ';
			$insert_val .= ($val==null)? 'NULL' : $db_conn->quote($val);
			$firstcol=0;
		}

		$sql= 'INSERT INTO traps_received (' . $insert_col . ') VALUES ('.$insert_val.');';

		$this->trapLog('sql : '.$sql,3,0);
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error SQL insert : '.$sql,1,0);
		}

		$this->trapLog('SQL insertion OK',3,0);

		// Get last id to insert oid/values in secondary table
		$sql='SELECT LAST_INSERT_ID();';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Erreur recuperation id',1,0);
		}

		// TODO check value of $inserted_id
		$inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
		$this->trapLog('id found: '.$inserted_id,3,0);
		
		// Fill trap extended data table
		foreach ($this->trap_data_ext as $key => $value) {
			
			// TODO : detect if trap value is encoded and decode it to UTF-8 for database
			$firstcol=1;
			$value->trap_id = $inserted_id;
			$insert_col='';
			$insert_val='';
			foreach ($value as $col => $val)
			{
				if ($firstcol==0) 
				{
					$insert_col .=',';
					$insert_val .=',';
				}
				$insert_col .= ' `' . $col .'` ';
				$insert_val .= ($val==null)? 'NULL' : $db_conn->quote($val);
				$firstcol=0;
			}

			$sql= 'INSERT INTO traps_received_data (' . $insert_col . ') VALUES ('.$insert_val.');';			

			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('Erreur insertion data : ' . $sql,2,0);
			}	
		}		
	}

	/** Get rules from rule database with ip and oid
	*	@param $ip ipv4 or ipv6
	*	@param $oid oid in numeric
	*	@retrun PDO object
	*/	
	protected function getRules($ip,$oid)
	{
		$db_conn=$this->db_connect_trap();
		$sql='SELECT * from traps_rules WHERE ( ( ip4=\''.$ip.'\' OR ip6=\''.$ip.'\' ) AND trap_oid=\''.$oid.'\' )';
		$this->trapLog('SQL query : '.$sql,4,0);
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,2,0);
		}
		return $ret_code->fetchAll();
	}
	
	/** Send SERVICE_CHECK_RESULT 
	*/
	public function serviceCheckResult($host,$service,$state,$display)
	{
		$send = '[' . date('U') .'] PROCESS_SERVICE_CHECK_RESULT;' .
			$host.';' .$service .';' . $state . ';'.$display;
		$this->trapLog( $send." : to : " .$this->icinga2cmd,3,0);
		
		// TODO : file_put_contents & fopen (,'w' or 'a') does not work. See why.
		$output=exec('echo "'.$send.'" > ' .$this->icinga2cmd,$output);
	}
	
	/** Resolve display. 
	*	Changes OID(<oid>) to value if found or text "<not in trap>"
	*	@param $display string
	*	@return string display
	*/
	protected function applyDisplay($display)
	{
		while (preg_match('/OID\(([0-9\.]+)\)/',$display,$matches) == 1)
		{
			$fullText=$matches[0];
			$oid=$matches[1];
			$found=0;
			foreach($this->trap_data_ext as $key => $val)
			{
				if ($oid == $val->oid)
				{
					$display=preg_replace('/OID\('.$oid.'\)/',$val->value,$display,-1,$rep);
					if ($rep==0)
					{
						$this->trapLog("Error in display",2,0);
						return $display;
					}
					$found=1;
					break;
				}
			}
			if ($found==0)
			{
				$display=preg_replace('/OID\('.$oid.'\)/','<not in trap>',$display,-1,$rep);
				if ($rep==0)
				{
					$this->trapLog("Error in display",2,0);
					return $display;
				}				
			}
		}
		return $display;
	}
	
	/** Match rules for current trap and do action
	*/
	public function applyRules()
	{
		$rules = $this->getRules($this->trap_data['source_ip'],$this->trap_data['trap_oid']);
		if ($rules==FALSE || count($rules)==0)
		{
			$this->trapLog('No rules found for this trap',3,0);
			$this->trap_data['status']='unknown';
			return;
		}
		//print_r($rules);
		foreach ($rules as $rkey => $rule)
		{
			$host_name=$rule['host_name'];
			$service_name=$rule['service_name'];
			$action=$rule['action'];
			$display=$this->applyDisplay($rule['display']);
			$this->serviceCheckResult($host_name,$service_name,$action,$display);
		}
		$this->trap_data['status']='done';
	}
}

?>