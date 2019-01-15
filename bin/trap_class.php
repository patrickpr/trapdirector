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
	protected $db_prefix='traps_';
	
	//**** Options from config database
	// Logs 
	protected $debug_level=2;  // 0=No output 1=critical 2=warning 3=trace 4=ALL
	protected $alert_output='syslog'; // alert type : file, syslog, display
	protected $debug_file="/tmp/trapdebug.txt";
	protected $syslog_logfile=null; // TODO check if useful
	//**** End options from database
	
	//protected $debug_file="php://stdout";	
	// Databases
	protected $trapDB; //< trap database
	protected $idoDB; //< ido database
	
	// Trap received data
	protected $receivingHost;
	public $trap_data=array(); //< Main trap data (oid, source...)
	public $trap_data_ext=array(); //< Additional trap data objects (oid/value).
	public $trap_id=null; //< trap_id after sql insert
	public $trap_action=null; //< trap action for final write
	
	function __construct($etc_dir='/etc/icingaweb2')
	{
		$this->icingaweb2_etc=$etc_dir;
		$this->trap_module_config=$this->icingaweb2_etc."/modules/trapdirector/config.ini";		
		$this->icingaweb2_ressources=$this->icingaweb2_etc."/resources.ini";
		
		$this->getOptions();

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
		$trap_config=parse_ini_file($this->trap_module_config,true);
		if ($trap_config == false) 
		{
			$this->trapLog("Error reading ini file : ".$this->trap_module_config,1,'syslog'); 
		}
		// Snmptranslate binary path
		if (!isset($trap_config['config']['snmptranslate'])) 
		{ // not in config : warning 
			$this->trapLog("No snmptranslate in config file: ".$this->trap_module_config,2,'syslog'); 
		}
		else
		{
			$this->snmptranslate=$trap_config['config']['snmptranslate'];
		}
		// mibs path 
		if (!isset($trap_config['config']['snmptranslate_dirs'])) 
		{ // not in config : warning 
			$this->trapLog("No snmptranslate_dirs in config file: ".$this->trap_module_config,2,'syslog'); 
		}
		else
		{
			$this->snmptranslate_dirs=$trap_config['config']['snmptranslate_dirs'];
		}
		// icinga2cmd path		
		if (!isset($trap_config['config']['icingacmd'])) 
		{ // not in config : warning 
			$this->trapLog("No icingacmd in config file: ".$this->trap_module_config,2,'syslog'); 
		}
		else
		{
			$this->icinga2cmd=$trap_config['config']['icingacmd'];
		}
		// table prefix
		if (!isset($trap_config['config']['database_prefix'])) 
		{ // not in config : warning 
			$this->trapLog("No database_prefix in config file: ".$this->trap_module_config,2,'syslog'); 
		}
		else
		{
			$this->db_prefix=$trap_config['config']['database_prefix'];
		}	
		
		/***** Database options :  ***/
		$this->getDBConfigIfSet('log_level',$this->debug_level);
		$this->getDBConfigIfSet('log_destination',$this->alert_output);
		$this->getDBConfigIfSet('log_file',$this->debug_file);
	}

	protected function getDBConfigIfSet($element,&$variable)
	{
		$value=$this->getDBConfig($element);
		if ($value != 'null') $variable=$value;
	}
	/** Get data from db_config
	*	@param $element name of param
	*	@return $value (or null)
	*/	
	protected function getDBConfig($element)
	{
		$db_conn=$this->db_connect_trap();
		$sql='SELECT value from '.$this->db_prefix.'db_config WHERE ( name=\''.$element.'\' )';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,2,'');
			return null;
		}
		$value=$ret_code->fetch();
		if ($value != null && isset($value['value']))
		{
			return $value['value'];
		}
		return null;
	}
	
	/** Send log. Throws exception on critical error
	*	@param	string $message Message to log
	*	@param	int $level 1=critical 2=warning 3=trace 4=debug
	*	@param  int $destination file/syslog/display
	*	@return None
	**/	
	public function trapLog( $message, $level, $destination ='')
	{	
		if ($this->debug_level >= $level) 
		{
			$message = '['.  date("Y/m/d H:i:s") . '] ' .
				'['. basename(__FILE__) . '] : ' .$message . "\n";
			
			if ($destination != '' )$output=$destination;
			else $output=$this->alert_output;
			switch ($output)
			{
				case 'file':
					file_put_contents ($this->debug_file, $message , FILE_APPEND);
					break;
				case 'syslog':
					// TODO : check if openlog is needed to set ident, etc...
					switch($level)
					{
						case 1 : $prio = LOG_ERR;break;
						case 2 : $prio = LOG_WARNING;break;
						case 3 : $prio = LOG_INFO;break;
						case 4 : $prio = LOG_DEBUG;break;
					}
					syslog($prio,$message);
					break;
				case 'display':
					echo $message;
					break;
				default : // nothing we can do at this point
					throw new Exception($message);
			}
		}
		if ($level == 1)
		{
			throw new Exception($message);
		}
	}
	
	public function setLogging($debug_lvl,$output_type,$output_option=null)
	{
		$this->debug_level=$debug_lvl;
		switch ($output_type)
		{
			case 'file':
				if ($output_option != null) $this->debug_file=$output_option;
				$this->alert_output='file';
				break;
			case 'syslog':
				if ($output_option != null) $this->syslog_logfile=$output_option;
				$this->alert_output='syslog';
				break;
			case 'display':
				$this->alert_output='display';
				break;
			default : // syslog should always work....
				$this->trapLog("Error in log output : ".$output_type,1,'syslog');
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
			$this->trapLog('Connection failed : ' . $e->getMessage(),1,'');
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
			$this->trapLog("Error reading ini file : ".$this->trap_module_config,1,''); 
		}
		if ($database == 'traps')
		{
			if (!isset($trap_config['config']['database'])) 
			{
				$this->trapLog("No Config/database in config file: ".$this->trap_module_config,1,''); 
			}
			$db_name=$trap_config['config']['database'];
		} 
		else if ($database == 'ido')
		{
			if (!isset($trap_config['config']['IDOdatabase'])) 
			{
				$this->trapLog("No Config/IDOdatabase in config file: ".$this->trap_module_config,1,''); 
			}
			$db_name=$trap_config['config']['IDOdatabase'];		
		}
		else
		{
			$this->trapLog("Unknown database type : ".$database,1,''); 		
		}	
		$this->trapLog("Found database in config file: ".$db_name,3,''); 
		$db_config=parse_ini_file($this->icingaweb2_ressources,true);
		if ($db_config == false) 
		{
			$this->trapLog("Error reading ini file : ".$this->icingaweb2_ressources,1,''); 
		}
		if (!isset($db_config[$db_name])) 
		{
			$this->trapLog("No Config/database in config file: ".$this->icingaweb2_ressources,1,''); 
		}
		$db_type=$db_config[$db_name]['db'];
		$db_host=$db_config[$db_name]['host'];
		$db_sql_name=$db_config[$db_name]['dbname'];
		$db_user=$db_config[$db_name]['username'];
		$db_pass=$db_config[$db_name]['password'];	
		$this->trapLog( "$db_type $db_host $db_sql_name $db_user $db_pass",3,''); 
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
			$this->trapLog("Error reading stdin !",1,''); 
		}

		// line 1 : host
		$this->receivingHost=chop(fgets($input_stream));
		if ($this->receivingHost == FALSE)
		{
			$this->trapLog("Error reading Host !",1,''); 
		}
		// line 2 IP:port=>IP:port
		$IP=chop(fgets($input_stream));
		if ($IP == FALSE)
		{
			$this->trapLog("Error reading IP !",1,''); 
		}
		$ret_code=preg_match('/.DP: \[(.*)\]:(.*)->\[(.*)\]:(.*)/',$IP,$matches);
		if ($ret_code==0 || $ret_code==FALSE) 
		{
			$this->trapLog('Error parsing IP : '.$IP,2,'');
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
				$this->trapLog('No match on trap data : '.$vars,2,'');
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
			$this->trapLog('no trap oid found',1,'');
		} 

		// Translate oids. (TODO : maybe in separate function)
		
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
		// try from database
		$db_conn=$this->db_connect_trap();
		
		$sql='SELECT mib,name from '.$this->db_prefix.'mib_cache WHERE oid=\''.$oid.'\';';
		$this->trapLog('SQL query : '.$sql,4,'');
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$name=$ret_code->fetch();
		if ($name['name'] != null)
		{
			return array('trap_name_mib'=>$name['mib'],'trap_name'=>$name['name']);
		}
		
		// Also check if it is not an instance of OID
		$oid_instance=preg_replace('/\.[0-9]+$/','',$oid);
		
		$sql='SELECT mib,name from '.$this->db_prefix.'mib_cache WHERE oid=\''.$oid_instance.'\';';
		$this->trapLog('SQL query : '.$sql,4,'');
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$name=$ret_code->fetch();
		if ($name['name'] != null)
		{
			return array('trap_name_mib'=>$name['mib'],'trap_name'=>$name['name']);
		}
		
		// Try to get oid name from snmptranslate
		$translate=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
				' '.$oid,$translate_output);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code==0 || $ret_code==FALSE) {
			return NULL;
		} else {
			$this->trapLog('Found name with snmptrapd and not in DB for oid='.$oid,2,'');
			return array('trap_name_mib'=>$matches[1],'trap_name'=>$matches[2]);
		}	
	}

	
	/** Erase old trap records 
	*	@param $days : erase traps when more than $days old
	*	@return : number of lines deleted
	**/
	public function eraseOldTraps($days=0)
	{
		if ($days==0)
		{
			if (($days=$this->getDBConfig('db_remove_days')) == null)
			{
				$this->trapLog('No days specified & no db value : no tap erase' ,2,'');
				return;
			}
		}
		$db_conn=$this->db_connect_trap();
		$daysago = strtotime("-".$days." day");
		$sql= 'delete from '.$this->db_prefix.'received where date_received < "'.date("Y-m-d H:i:s",$daysago).'";"';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error erasing traps : '.$sql,1,'');
		}
		$this->trapLog('Erased traps older than '.$days.' day(s) : '.$sql,3);
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

		$sql= 'INSERT INTO '.$this->db_prefix.'received (' . $insert_col . ') VALUES ('.$insert_val.');';

		$this->trapLog('sql : '.$sql,3,'');
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error SQL insert : '.$sql,1,'');
		}

		$this->trapLog('SQL insertion OK',3,'');

		// Get last id to insert oid/values in secondary table
		$sql='SELECT LAST_INSERT_ID();';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Erreur recuperation id',1,'');
		}

		// TODO check value of $inserted_id
		$inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
		$this->trap_id=$inserted_id;
		$this->trapLog('id found: '.$inserted_id,3,'');
		
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

			$sql= 'INSERT INTO '.$this->db_prefix.'received_data (' . $insert_col . ') VALUES ('.$insert_val.');';			

			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('Erreur insertion data : ' . $sql,2,'');
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
		// fetch rules based on IP in rule and OID
		$sql='SELECT * from '.$this->db_prefix.'rules WHERE trap_oid=\''.$oid.'\' ';
		$this->trapLog('SQL query : '.$sql,4,'');
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,2,'');
		}
		$rules_all=$ret_code->fetchAll();
		//echo "rule all :\n";print_r($rules_all);echo "\n";
		$rules_ret=array();
		$rule_ret_key=0;
		foreach ($rules_all as $key => $rule)
		{
			if ($rule['ip4']==$ip || $rule['ip6']==$ip)
			{
				$rules_ret[$rule_ret_key]=$rules_all[$key];
				$rule_ret_key++;
				continue;
			}
			if (isset($rule['host_group_name']) && $rule['host_group_name']!=null)
			{ // get ips of group members by oid
				$db_conn2=$this->db_connect_ido();
				$sql="SELECT m.host_object_id, a.address as ip4, a.address6 as ip6, b.name1 as host_name
						FROM icinga_objects as o
						LEFT JOIN icinga_hostgroups as h ON o.object_id=h.hostgroup_object_id
						LEFT JOIN icinga_hostgroup_members as m ON h.hostgroup_id=m.hostgroup_id
						LEFT JOIN icinga_hosts as a ON a.host_object_id = m.host_object_id
						LEFT JOIN icinga_objects as b ON b.object_id = a.host_object_id
						WHERE o.name1='".$rule['host_group_name']."';";
				if (($ret_code2=$db_conn2->query($sql)) == FALSE) {
					$this->trapLog('No result in query : ' . $sql,2,'');
					continue;
				}
				$grouphosts=$ret_code2->fetchAll();
				//echo "rule grp :\n";print_r($grouphosts);echo "\n";
				foreach ( $grouphosts as $gkey=>$host)
				{
					//echo $host['ip4']."\n";
					if ($host['ip4']==$ip || $host['ip6']==$ip)
					{
						//echo "Rule added \n";
						$rules_ret[$rule_ret_key]=$rules_all[$key];
						$rules_ret[$rule_ret_key]['host_name']=$host['host_name'];
						$rule_ret_key++;
					}	
				}
			}
		}
		//echo "rule rest :\n";print_r($rules_ret);echo "\n";exit(0);
		return $rules_ret;
	}

	/** Add rule match to rule
	*	@param rule id
	*/
	protected function add_rule_match($id, $set)
	{
		$db_conn=$this->db_connect_trap();
		$sql="UPDATE ".$this->db_prefix."rules SET num_match = '".$set."' WHERE (id = '".$id."');";
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error in update query : ' . $sql,2,'');
		}
	}
	
	/** Send SERVICE_CHECK_RESULT 
	*/
	public function serviceCheckResult($host,$service,$state,$display)
	{
		$send = '[' . date('U') .'] PROCESS_SERVICE_CHECK_RESULT;' .
			$host.';' .$service .';' . $state . ';'.$display;
		$this->trapLog( $send." : to : " .$this->icinga2cmd,3,'');
		
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
		while (preg_match('/_OID\(([0-9\.]+)\)/',$display,$matches) == 1)
		{
			$fullText=$matches[0];
			$oid=$matches[1];
			$found=0;
			foreach($this->trap_data_ext as $key => $val)
			{
				if ($oid == $val->oid)
				{
					$display=preg_replace('/_OID\('.$oid.'\)/',$val->value,$display,-1,$rep);
					if ($rep==0)
					{
						$this->trapLog("Error in display",2,'');
						return $display;
					}
					$found=1;
					break;
				}
			}
			if ($found==0)
			{
				$display=preg_replace('/_OID\('.$oid.'\)/','<not in trap>',$display,-1,$rep);
				if ($rep==0)
				{
					$this->trapLog("Error in display",2,'');
					return $display;
				}				
			}
		}
		return $display;
	}

	
	/***************** Eval & tokenizer functions ****************/
	protected function eval_getElement($rule,&$item)
	{
		while ($rule[$item]==' ') $item++;
		if (preg_match('/[0-9\.]/',$rule[$item]))
		{ // number
	
			$item2=$item+1; 
			while (($item2!=strlen($rule)) && (preg_match('/[0-9\.]/',$rule[$item2]))) { $item2++ ;}
			$val=substr($rule,$item,$item2-$item);
			$item=$item2;
			//echo "number ".$val."\n";
			return array(0,$val);
		}
		if ($rule[$item] == '"')
		{ // string
			$item++;
			$item2=$this->eval_getNext($rule,$item,'"');
			$val=substr($rule,$item,$item2-$item-1);
			$item=$item2;
			//echo "string : ".$val."\n";
			return array(1,$val);
		}
		
		if ($rule[$item] == '(')
		{ // grouping
			$start=$item+1;
			while (($rule[$item] != ')' ) && ($item < strlen($rule))) 
			{ 
				if ($rule[$item] == '"' )
				{ // pass through string
					$item++;
					$item=$this->eval_getNext($rule,$item,'"');
				} 
				else{
					$item++;
				}
			}
			
			if ($item==strlen($rule)) {throw new Exception("no closing () in ".$rule ." at " .$item);}
			$val=substr($rule,$start,$item-$start);
			$item++;
			$start=0;
			//echo "group : ".$val."\n";
			return array(2,$this->evaluation($val,$start));		
		}
		throw new Exception("number/string not found in ".$rule ." at " .$item);
		
	}
	
	protected function eval_getNext($rule,$item,$tok)
	{
		while (($rule[$item] != $tok ) && ($item < strlen($rule))) { $item++;}
		if ($item==strlen($rule)) throw new Exception("closing '".$tok."' not found in ".$rule ." at " .$item);
		return $item+1;
	}
	protected function eval_getOper($rule,&$item)
	{
		while ($rule[$item]==' ') $item++;
		switch ($rule[$item])
		{
			case '<':
				if ($rule[$item+1]=='=') { $item+=2; return array(0,"<=");}
				$item++; return array(0,"<");
			case '>':
				if ($rule[$item+1]=='=') { $item+=2; return array(0,">=");}
				$item++; return array(0,">");
			case '=':
				$item++; return array(0,"=");	
			case '!':
				if ($rule[$item+1]=='=') { $item+=2; return array(0,"!=");}
				throw new Exception("Erreur in expr - incorrect operator '!'  found in ".$rule ." at " .$item);;
			case '|':
				$item++; return array(1,"|");	
			case '&':
				$item++; return array(1,"&");
			default	:
				throw new Exception("Erreur in expr - operator not found in ".$rule ." at " .$item);
		}
	}
	
	/** Evaluation : makes token and evaluate. 
	*	Public function for expressions testing
	*	accepts : < > = <= >= !=  (typec = 0)
	*	operators : & | (typec=1)
	*	with : integers [0-9]+ (type 0) or strings "" (type 1) or results (type 2)
	*   comparison int vs strings will return null (error)
	*	return : bool or null on error
	*/
	public function evaluation($rule,&$item){
		
		$item2=0;
		list($type1,$val1) = $this->eval_getElement($rule,$item);
		//echo "Elmt: ".$val1." : ".substr($rule,$item)."\n";
		if ($item==strlen($rule)) {/*echo "1val\n"*/;return $val1;}  // If only element, return value
		list($typec,$comp) = $this->eval_getOper($rule,$item);
		//echo "Comp : ".$comp." : ".substr($rule,$item)."\n";
		list($type2,$val2) = $this->eval_getElement($rule,$item);
		//echo "Elmt: ".$val2." : ".substr($rule,$item)."\n";
		
		if ($type1!=$type2) { return null;} // cannot compare different types
		if ($typec==1 && $type1 !=2) {return null;} // cannot use & or | with string/number 
		
		switch ($comp){
			case '<':	$retVal= ($val1 < $val2); break;
			case '<=':	$retVal= ($val1 <= $val2); break;
			case '>':	$retVal= ($val1 > $val2); break;
			case '>=':	$retVal= ($val1 >= $val2); break;
			case '=':	$retVal= ($val1 == $val2); break;
			case '!=':	$retVal= ($val1 != $val2); break;
			case '|':	$retVal= ($val1 || $val2); break;
			case '&':	$retVal= ($val1 && $val2); break;
			default:  throw new Exception("Error in expression - unknown comp : ".$comp);
		}
		if ($item==strlen($rule)) return $retVal; // End of string : return evaluation
		// check for logical operator :
		switch ($rule[$item])
		{
			case '|':	$item++; return ($retVal || $this->evaluation($rule,$item) ); break;
			case '&':	$item++; return ($retVal && $this->evaluation($rule,$item) ); break;
			
			default:  throw new Exception("Erreur in expr - garbadge at end of expression : ".$rule[$item]);
		}
	}
	// Remove all whitespaces (when not quoted)
	public function eval_cleanup($rule)
	{
		$item=0;
		$rule2='';
		while ($item < strlen($rule))
		{
			if ($rule[$item]==' ') { $item++; continue; }
			if ($rule[$item]=='"')
			{
				$rule2.=$rule[$item];
				$item++;
				while (($rule[$item]!='"') && ($item < strlen($rule)))
				{
					$rule2.=$rule[$item];
					$item++;
				}
				if ($item == strlen ($rule)) throw new Exception("closing '".$tok."' not found in ".$rule ." at " .$item);
				$rule2.=$rule[$item];
				$item++;
				continue;
			}
			
			$rule2.=$rule[$item];
			$item++;		
		}
		
		return $rule2;		
	}		
	
	/** Evaluation rule (uses eval_* functions recursively)
	*	@param $rule string rule ( _OID(.1.3.6.1.4.1.8072.2.3.2.1)=_OID(.1.3.6.1.2.1.1.3.0) )
	*	@return : true : rule match, false : rule don't match , throw exception on error.
	*/
	
	protected function eval_rule($rule)
	{
		if ($rule==null || $rule == '')
		{
			return true;
		}
		while (preg_match('/_OID\(([0-9\.]+)\)/',$rule,$matches) == 1)
		{
			$fullText=$matches[0];
			$oid=$matches[1];
			$found=0;
			foreach($this->trap_data_ext as $key => $val)
			{
				if ($oid == $val->oid)
				{
					if (!preg_match('/^[0-9]+\.?[0-9]*$/',$val->value))
					{ // If not a number, put "" 
						$val->value='"'.$val->value.'"';
					}
					$rule=preg_replace('/_OID\('.$oid.'\)/',$val->value,$rule,-1,$rep);
					if ($rep==0)
					{
						$this->trapLog("Error in rule_eval",2,'');
						return false;
					}
					$found=1;
					break;
				}
			}
			if ($found==0)
			{	// OID not found : put false(0) instead
				// TODO : make difference between 0 and false.
				$rule=preg_replace('/_OID\('.$oid.'\)/','0',$rule,-1,$rep);				
			}
		}
		$item=0;
		$rule=$this->eval_cleanup($rule);
		$this->trapLog('Rule after clenup: '.$rule,3,'');
		
		return  $this->evaluation($rule,$item);
	}
	
	/** Match rules for current trap and do action
	*/
	public function applyRules()
	{
		$rules = $this->getRules($this->trap_data['source_ip'],$this->trap_data['trap_oid']);
		
		if ($rules==FALSE || count($rules)==0)
		{
			$this->trapLog('No rules found for this trap',3,'');
			$this->trap_data['status']='unknown';
			return;
		}
		//print_r($rules);
		foreach ($rules as $rkey => $rule)
		{
			
			$host_name=$rule['host_name'];
			$service_name=$rule['service_name'];
			
			$display=$this->applyDisplay($rule['display']);
			
			try
			{
				$this->trapLog('Rule to eval : '.$rule['rule'],3,'');
				$evalr=$this->eval_rule($rule['rule']);
				
				if ($evalr == true)
				{
					//$this->trapLog('rules OOK: '.print_r($rule),3,'');
					$action=$rule['action_match'];
					$this->trapLog('action OK : '.$action,3,'');
					if ($action != -1)
					{
						$this->serviceCheckResult($host_name,$service_name,$action,$display);
						$this->add_rule_match($rule['id'],$rule['num_match']+1);
						$this->trap_action='Status '.$action.' to '.$host_name.'/'.$service_name;
					}
				}
				else
				{
					//$this->trapLog('rules KOO : '.print_r($rule),3,'');
					
					$action=$rule['action_nomatch'];
					$this->trapLog('action NOK : '.$action,3,'');
					if ($action != -1)
					{
						$this->serviceCheckResult($host_name,$service_name,$action,$display);
						$this->add_rule_match($rule['id'],$rule['num_match']+1);
						$this->trap_action='Status '.$action.' to '.$host_name.'/'.$service_name;
					}					
				}
				// Put name in source_name
				if (!isset($this->trap_data['source_name']))
				{
					$this->trap_data['source_name']=$rule['host_name'];
				}
				else
				{
					if (!preg_match('/'.$rule['host_name'].'/',$this->trap_data['source_name']))
					{ // only add if not present
						$this->trap_data['source_name'].=','.$rule['host_name'];
					}
				}
			}
			catch (Exception $e) 
			{ 
				$this->trapLog('Error in rule eval',2,'');
				//echo $e->getMessage() . "\n";
			}
			
		}
		$this->trap_data['status']='done';
	}

	/** Add Time a action to rule
	*	@param rule id
	*/
	public function add_rule_final($time)
	{
		$db_conn=$this->db_connect_trap();
		$sql="UPDATE ".$this->db_prefix."received SET process_time = '".$time."' , status_detail='".$this->trap_action."'  WHERE (id = '".$this->trap_id."');";
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error in update query : ' . $sql,2,'');
		}
	}
	
	/*********** UTILITIES *********************/
	
	/** Create database schema 
	*	@param $schema_file	File to read schema from
	*	@param $table_prefix to replace #PREFIX# in schema file by this
	*/
	public function create_schema($schema_file,$table_prefix)
	{
		//Read data from snmptrapd from stdin
		$input_stream=fopen($schema_file, 'r');

		if ($input_stream==FALSE)
		{
			$this->trapLog("Error reading schema !",1,''); 
		}
		$newline='';
		while (($line=fgets($input_stream)) != FALSE)
		{
			$newline.=chop(preg_replace('/#PREFIX#/',$table_prefix,$line));
		}
		$db_conn=$this->db_connect_trap();
		$sql= $newline;
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('Error create schema : '.$sql,1,'');
		}
		$this->trapLog('Schema created',3);		
	}

	/** reset service to OK after time defined in rule
	*	TODO logic is : get all service in error + all all rules, see if getting all rules then select services is better 
	*	@return : like a plugin : status code (0->3) <message> | <perfdata>
	**/
	public function reset_services()
	{
		// Get all services not in 'ok' state
		$sql_query="SELECT s.service_object_id,
	 UNIX_TIMESTAMP(s.last_check) AS last_check,
	s.current_state as state,
	v.name1 as host_name,
    v.name2 as service_name
	FROM icinga_servicestatus AS s 
    LEFT JOIN icinga_objects as v ON s.service_object_id=v.object_id
    WHERE s.current_state != 0;";
		$db_conn=$this->db_connect_ido();
		if (($services_db=$db_conn->query($sql_query)) == FALSE) { // set err to 1 to throw exception.
			$this->trapLog('No result in query : ' . $sql_query,1,'');
		}
		$services=$services_db->fetchAll();
		
		// Get all rules
		$sql_query="SELECT host_name, service_name, revert_ok FROM traps_rules where revert_ok != 0;";
		$db_conn2=$this->db_connect_trap();
		if (($rules_db=$db_conn2->query($sql_query)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql_query,1,''); 
		}
		$rules=$rules_db->fetchAll();
		
		$now=date('U');
		
		$numreset=0;
		foreach ($rules as $key=>$rule)
		{
			foreach ($services as $skey => $service)
			{
				if ($service['service_name'] == $rule['service_name'] &&
					$service['host_name'] == $rule['host_name'] &&
					($service['last_check'] + $rule['revert_ok']) < $now)
				{
					$this->serviceCheckResult($service['host_name'],$service['service_name'],0,'Reset service to OK after '.$rule['revert_ok'].' seconds');
					$numreset++;
				}
			}
		}
		echo "\n";
		echo $numreset . " service(s) reset to OK\n";
		return 0;
		
	}

	public function update_mibs_options()
	{
		$db_conn=$this->db_connect_trap();

		/**********  Create all trap objetcs  *****/
		// Get max number
		$sql='SELECT id,oid FROM '.$this->db_prefix.'mib_cache WHERE type=21;';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$traps=$ret_code->fetchAll();
		
		foreach ($traps as $key=>$trap)
		{
			$trapOID=$trap['oid'];
			$trapID=$trap['id'];
			// get OBJECTS for this trap OID
			$snmptrans=null;
			$return=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
					' -Td '.$trapOID,$snmptrans,$retVal);
			if ($retVal!=0)
			{
				$this->trapLog('error executing snmptranslate',1,'');
			}
			$synt=null;
			foreach ($snmptrans as $line)
			{	
			if (preg_match('/OBJECTS.*\{([^\}]+)\}/',$line,$match))
				{
					$synt=$match[1];
				}
			}
			if ($synt == null) 
			{
				//echo "No objects for $trapOID\n";
				continue;
			}
			//echo "$synt \n";
			$trapObjects=array();
			while (preg_match('/ *([^ ,]+) *,* */',$synt,$match))
			{
				array_push($trapObjects,$match[1]);
				$synt=preg_replace('/'.$match[0].'/','',$synt);
			}
			//print_r($trapObjects);
			// Delete all trap objects for this trap_id
			$sql='DELETE FROM '.$this->db_prefix.'mib_cache_trap_object where trap_id='.$trapID.';';
			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('No result in query : ' . $sql,1,'');
			}
			// create them again
			foreach ($trapObjects as $trapObject)
			{
				$sql='INSERT INTO '.$this->db_prefix.'mib_cache_trap_object '.
				'(trap_id,object_name) VALUE ('.$trapID.' , \''.$trapObject.'\');';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('No result in query : ' . $sql,1,'');
				}
			}
		}		

		/**********  Create all syntax from the mibs  *****/
		// Get max number
		$sql='SELECT MAX(type) FROM '.$this->db_prefix.'mib_cache;';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$num=$ret_code->fetch()['MAX(type)'];
		//echo $sql."\n";print_r($num);echo"\n";
		for ($i=0;$i<$num;$i++)
		{
			// get an oid for type=$i
			$sql='SELECT oid FROM '.$this->db_prefix.'mib_cache WHERE type=\''.$i.'\' LIMIT 1;';
			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('No result in query : ' . $sql,1,'');
			}
			$oid=$ret_code->fetch()['oid'];
			if ($oid == null) 
			{
				continue;
			}
			// get SYNTAX for this OID
			$snmptrans=null;
			$return=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
					' -Td '.$oid,$snmptrans,$retVal);
			if ($retVal!=0)
			{
				$this->trapLog('error executing snmptranslate',1,'');
			}
			$synt=null;
			foreach ($snmptrans as $line)
			{	
				if (preg_match('/^[\t ]+SYNTAX[\t ]+(.*)/',$line,$match))
				{
					$synt=$match[1];
				}
			}
			if ($synt == null) 
			{
				continue;
			}
			// check if type exists -> update or insert
			$sql='SELECT num FROM '.$this->db_prefix.'mib_cache_syntax WHERE num=\''.$i.'\';';
			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('No result in query : ' . $sql,1,'');
			}
			$numDB=$ret_code->fetch()['num'];
			if ($numDB==null)
			{
				$sql='INSERT INTO '.$this->db_prefix.'mib_cache_syntax '.
				'(num,value) VALUES ('.$i.',\''.$synt.'\');';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('Error in query : ' . $sql,1,'');
				}					
			}
			else
			{
				$sql='UPDATE '.$this->db_prefix.'mib_cache_syntax '.
				'SET value=\''.$synt.'\' WHERE num='.$i.';';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('Error in query : ' . $sql,1,'');
				}					
			}
		}

		/**********  Create all textual conventions  *****/
		// Get max number
		$sql='SELECT MAX(textual_convention) FROM '.$this->db_prefix.'mib_cache;';
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$num=$ret_code->fetch()['MAX(textual_convention)'];
		//echo $sql."\n";print_r($num);echo"\n";
		for ($i=0;$i<$num;$i++)
		{
			// get an oid for textual_convention=$i
			$sql='SELECT oid FROM '.$this->db_prefix.'mib_cache WHERE textual_convention=\''.$i.'\' LIMIT 1;';
			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('No result in query : ' . $sql,1,'');
			}
			$oid=$ret_code->fetch()['oid'];
			if ($oid == null) 
			{
				continue;
			}
			// get SYNTAX for this OID
			$snmptrans=null;
			$return=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
					' -Td '.$oid,$snmptrans,$retVal);
			if ($retVal!=0)
			{
				$this->trapLog('error executing snmptranslate',1,'');
			}
			$synt=null;
			foreach ($snmptrans as $line)
			{	
				if (preg_match('/TEXTUAL CONVENTION[\t ]+(.*)/',$line,$match))
				{
					$synt=$match[1];
				}
			}
			if ($synt == null) 
			{
				continue;
			}
			// check if tc exists -> update or insert
			$sql='SELECT num FROM '.$this->db_prefix.'mib_cache_tc WHERE num=\''.$i.'\';';
			if (($ret_code=$db_conn->query($sql)) == FALSE) {
				$this->trapLog('No result in query : ' . $sql,1,'');
			}
			$numDB=$ret_code->fetch()['num'];
			if ($numDB==null)
			{
				$sql='INSERT INTO '.$this->db_prefix.'mib_cache_tc '.
				'(num,value) VALUES ('.$i.',\''.$synt.'\');';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('Error in query : ' . $sql,1,'');
				}					
			}
			else
			{
				$sql='UPDATE '.$this->db_prefix.'mib_cache_tc '.
				'SET value=\''.$synt.'\' WHERE num='.$i.';';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('Error in query : ' . $sql,1,'');
				}					
			}
		}	
		
	}
	/** Cache mib in database
	*/
	public function update_mib_database($display_progress=false)
	{
		// Timing 
		$timeTaken = microtime(true);
		// Get all mib objects from all mibs
		$return=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
				' -On -Tto ',$objects_s,$retVal);
		if ($retVal!=0)
		{
			$this->trapLog('error executing snmptranslate',1,'');
		}
		
		// Get all mibs from databse
		
		$db_conn=$this->db_connect_trap();
		// fetch rules based on IP in rule and OID
		$sql='SELECT * from '.$this->db_prefix.'mib_cache;';
		$this->trapLog('SQL query : '.$sql,4,'');
		if (($ret_code=$db_conn->query($sql)) == FALSE) {
			$this->trapLog('No result in query : ' . $sql,1,'');
		}
		$dbRulesAll=$ret_code->fetchAll();
		$dbRulesIndex=array();
		// Create index for db;
		foreach($dbRulesAll as $key=>$val)
		{
			$dbRulesIndex[$val['oid']]=$key;
		}
		// Count elements to show progress
		$numElements=count($objects_s);
		$step=$basestep=$numElements/10;
		$num_step=0;
		for ($curElement=0;$curElement < $numElements;$curElement++)
		{
			if ($curElement>$step) 
			{ // display progress
				$num_step++;
				$step+=$basestep;
				if ($display_progress)
				{				
					echo '..'.($num_step*10).'%';
				}
			}
			// Get oid or pass if not found
			if (!preg_match('/^\.[0-9\.]+$/',$objects_s[$curElement]))
			{
				continue;
			}
			$oid=$objects_s[$curElement];
			// get next line 
			$curElement++;
			if (!preg_match('/ +([^\(]+)\(.+\) type=([0-9]+)( tc=([0-9]+))?( hint=(.+))?/',
						$objects_s[$curElement],$match))
			{
				continue;
			}

			if ($match[2]==0)
			{ 	// object type=0 : not oid -> continue
				continue;
			}
			$name=$match[1];
			$type=$match[2];
			$textConv=(isset($match[4]))?$match[4]:null;
			$dispHint=(isset($match[6]))?$match[6]:null;
			//echo $objects_s[$curElement]."\n";
			//print_r($match);
			if (isset($dbRulesIndex[$oid]))
			{
				if ( $name!=$dbRulesAll[$dbRulesIndex[$oid]]['name'] ||
					$type!=$dbRulesAll[$dbRulesIndex[$oid]]['type'] ||
					$textConv!=$dbRulesAll[$dbRulesIndex[$oid]]['textual_convention'] ||
					$dispHint!=$dbRulesAll[$dbRulesIndex[$oid]]['display_hint'] )
				{ // Do update (TODO : check MIB does not change )
					echo 'Update : '.$oid."\n";
					echo "$name# ".$dbRulesAll[$dbRulesIndex[$oid]]['name']."#\n";
					echo "$type# ".$dbRulesAll[$dbRulesIndex[$oid]]['type']."#\n";
					echo "$textConv# ".$dbRulesAll[$dbRulesIndex[$oid]]['textual_convention']."#\n";
					echo "$dispHint# ".$dbRulesAll[$dbRulesIndex[$oid]]['display_hint']."#\n";
					$sql='UPDATE '.$this->db_prefix.'mib_cache SET '.
					"name = '".$name."' , type = '".$type."' , textual_convention = ".
					(($textConv==null)?'null':"'".$textConv."'")
					." , display_hint = ".
					(($dispHint==null)?'null':"'".$dispHint."'")." WHERE id='".
					$dbRulesAll[$dbRulesIndex[$oid]]['id'] ."' ;";
					//$this->trapLog('SQL query : '.$sql,4,'');
					if (($ret_code=$db_conn->query($sql)) == FALSE) {
						$this->trapLog('Error in query : ' . $sql,1,'');
					}			
				}
				else
				{
					//echo "found oid : ".$oid."\n";
				}
			}
			else
			{	// create
				// First get mib :
				$return=exec($this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.
				' '.$oid,$return_all,$retVal);
				if ($retVal!=0)
				{
					$this->trapLog('error executing snmptranslate '.$return .':'.$this->snmptranslate . ' -m ALL -M '.$this->snmptranslate_dirs.' '.$oid,1,'');
				}
				if (!preg_match('/^(.*)::/',$return,$match2))
				{
					$this->trapLog('error finding mib '.$return,2,'');
					continue;
				}
				$mib=$match2[1];
				
				// Insert data
				$sqlT='oid,mib,name,type';
				$sqlV="'".$oid."' , '".$mib."' , '".$name."' , '".$type."'";
				if ($textConv != null) 
				{
					$sqlT.=',textual_convention';
					$sqlV.=",'".$textConv."'";
				}
				if ($dispHint != null) 
				{
					$sqlT.=',display_hint';
					$sqlV.=",'".$dispHint."'";
				}				
				$sql='INSERT INTO '.$this->db_prefix.'mib_cache '.
				'('.$sqlT.') VALUES ('.$sqlV.');';
				if (($ret_code=$db_conn->query($sql)) == FALSE) {
					$this->trapLog('Error in query : ' . $sql,1,'');
				}					
			}
		}
		
		
		// Timing ends
		$timeTaken=microtime(true) - $timeTaken;
		if ($display_progress)
		{
			echo "  : TIME : ".$timeTaken."\n";
		}
	}
	
}

?>