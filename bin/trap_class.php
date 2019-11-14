<?php


//use FontLib\EOT\File;

include (dirname(__DIR__).'/library/Trapdirector/Icinga2Api.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Logging.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Database.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Mib.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Rule.php');

use Icinga\Module\Trapdirector\Icinga2API;
use Trapdirector\Logging;
use Trapdirector\Database;
use Trapdirector\Mib;
use Trapdirector\Rule;

class Trap
{
	// Configuration files and dirs
	protected $icingaweb2_etc; //< Icinga etc path	
	protected $trap_module_config; //< config.ini of module	
	protected $icingaweb2_ressources; //< resources.ini of icingaweb2
	// Options from config.ini 
	protected $snmptranslate='/usr/bin/snmptranslate';
	protected $snmptranslate_dirs='/usr/share/icingaweb2/modules/trapdirector/mibs';
	protected $icinga2cmd='/var/run/icinga2/cmd/icinga2.cmd';
	protected $db_prefix='traps_';

	// API
	protected $api_use=false;
	protected $icinga2api=null;
	protected $api_hostname='';
	protected $api_port=0;
	protected $api_username='';
	protected $api_password='';

	// Logs 
	protected $logging;    //< Logging class.
	protected $logSetup;   //< bool true if log was setup in constructor
	
	// Databases
	public $trapsDB; //< Database class
	
	// Trap received data
	protected $receivingHost;
	public $trap_data=array(); //< Main trap data (oid, source...)
	public $trap_data_ext=array(); //< Additional trap data objects (oid/value).
	public $trap_id=null; //< trap_id after sql insert
	public $trap_action=null; //< trap action for final write
	protected $trap_to_db=true; //< log trap to DB
	
	// Mib update data
	public $mibClass; //< Mib class
	
	// Rule evaluation 
	public $ruleClass; // Rule class
	
	function __construct($etc_dir='/etc/icingaweb2',$baseLogLevel=null,$baseLogMode='syslog',$baseLogFile='')
	{
	    // Paths of ini files
		$this->icingaweb2_etc=$etc_dir;
		$this->trap_module_config=$this->icingaweb2_etc."/modules/trapdirector/config.ini";		
		$this->icingaweb2_ressources=$this->icingaweb2_etc."/resources.ini";

		// Setup logging
		$this->logging = new Logging();
		if ($baseLogLevel != null)
		{
		    $this->logging->setLogging($baseLogLevel, $baseLogMode,$baseLogFile);
		    $this->logSetup=true;
		}
		else
		    $this->logSetup=false;
		$this->logging->log('Loggin started', INFO);

		// Get options from ini files
		$trapConfig=parse_ini_file($this->trap_module_config,true);
		if ($trapConfig == false)
		{
		    $this->logging->log("Error reading ini file : ".$this->trap_module_config,ERROR,'syslog');
		}
		$this->getMainOptions($trapConfig); // Get main options from ini file
		$this->setupDatabase($trapConfig); // Setup database class
		
		$this->getDatabaseOptions(); // Get options in database
		if ($this->api_use === true) $this->getAPI(); // Setup API
		
		$this->mibClass = new Mib($this->logging,$this->trapsDB,$this->snmptranslate,$this->snmptranslate_dirs); // Create Mib class
		
		$this->ruleClass = new Rule($this->logging); //< Create Rule class
		
		$this->trap_data=array(
			'source_ip'	=> 'unknown',
			'source_port'	=> 'unknown',
			'destination_ip'	=> 'unknown',
			'destination_port'	=> 'unknown',
			'trap_oid'	=> 'unknown',
		);
		
	}
	
	/**
	 * Get option from array of ini file, send message if empty
	 * @param string $option_array Array of ini file
	 * @param string $option_category category in ini file
	 * @param string $option_name name of option in category
	 * @param resource $option_var variable to fill if found, left untouched if not found
	 * @param integer $log_level default 2 (warning)
	 * @param string $message warning message if not found
	 * @return boolean true if found, or false
	 */
	protected function getOptionIfSet($option_array,$option_category,$option_name, &$option_var, $log_level = 2, $message = null)
	{
	    if (!isset($option_array[$option_category][$option_name]))
	    {
	        if ($message === null)
	        {
	            $message='No ' . $option_name . ' in config file: '. $this->trap_module_config;
	        }
	        $this->logging->log($message,$log_level,'syslog');
	        return false;
	    }
	    else
	    {
	        $option_var=$option_array[$option_category][$option_name];
	        return true;
	    }
	}
	
	/** 
	 * Get options from ini file
	 * @param array $trap_config : ini file array
	*/
	protected function getMainOptions($trapConfig)
	{

		// Snmptranslate binary path
		$this->getOptionIfSet($trapConfig,'config','snmptranslate', $this->snmptranslate);

		// mibs path
		$this->getOptionIfSet($trapConfig,'config','snmptranslate_dirs', $this->snmptranslate_dirs);

		// icinga2cmd path
		$this->getOptionIfSet($trapConfig,'config','icingacmd', $this->icinga2cmd);
		
		// table prefix
		$this->getOptionIfSet($trapConfig,'config','database_prefix', $this->db_prefix);

		// API options
		if ($this->getOptionIfSet($trapConfig,'config','icingaAPI_host', $this->api_hostname))
		{
		    $this->api_use=true;
		    $this->getOptionIfSet($trapConfig,'config','icingaAPI_port', $this->api_port);
		    $this->getOptionIfSet($trapConfig,'config','icingaAPI_user', $this->api_username);
		    $this->getOptionIfSet($trapConfig,'config','icingaAPI_password', $this->api_password);
		}
	}
	
	/**
	 * Create and setup database class for trap & ido (if no api) db
	 * @param array $trap_config : ini file array
	 */
	protected function setupDatabase($trapConfig)
	{
	    // Trap database
	    if (!array_key_exists('database',$trapConfig['config']))
        {
            $this->logging->log("No database in config file: ".$this->trap_module_config,ERROR,'');
            return;
        }
        $dbTrapName=$trapConfig['config']['database'];
        $this->logging->log("Found database in config file: ".$dbTrapName,INFO );
	    
	   if ( ($dbConfig=parse_ini_file($this->icingaweb2_ressources,true)) === false)
	    {
	        $this->logging->log("Error reading ini file : ".$this->icingaweb2_ressources,ERROR,'');
	        return;
	    }
	    if (!array_key_exists($dbTrapName,$dbConfig))
	    {
	        $this->logging->log("No database '.$dbTrapName.' in config file: ".$this->icingaweb2_ressources,ERROR,'');
	        return;
	    }
	    
	    $this->trapsDB = new Database($this->logging,$dbConfig[$dbTrapName],$this->db_prefix);
	    
	    if ($this->api_use === true) return; // In case of API use, no IDO is necessary
        
	    // IDO Database
	    if (!array_key_exists('IDOdatabase',$trapConfig['config']))
	    {
	        $this->logging->log("No IDOdatabase in config file: ".$this->trap_module_config,ERROR,'');
	    }
	    $dbIdoName=$trapConfig['config']['IDOdatabase'];		

	    $this->logging->log("Found IDO database in config file: ".$dbIdoName,INFO );
        if (!array_key_exists($dbIdoName,$dbConfig))
	    {
	        $this->logging->log("No database '.$dbIdoName.' in config file: ".$this->icingaweb2_ressources,ERROR,'');
	        return;
	    }
	    
	    $this->trapsDB->setupIDO($dbConfig[$dbIdoName]);
	}
	
	/**
	 * Get options in database
	 */
	protected function getDatabaseOptions()
	{
		// Database options
		if ($this->logSetup === false) // Only if logging was no setup in constructor
		{
    		$this->getDBConfigIfSet('log_level',$this->logging->debugLevel);
    		$this->getDBConfigIfSet('log_destination',$this->logging->outputMode);
    		$this->getDBConfigIfSet('log_file',$this->logging->outputFile);
		}
	}

	protected function getDBConfigIfSet($element,&$variable)
	{
		$value=$this->getDBConfig($element);
		if ($value != 'null') $variable=$value;
	}
	
	/** 
	*   Get data from db_config
	*	@param $element string name of param
	*	@return mixed : value (or null)
	*/	
	protected function getDBConfig($element)
	{
		$db_conn=$this->trapsDB->db_connect_trap();
		$sql='SELECT value from '.$this->db_prefix.'db_config WHERE ( name=\''.$element.'\' )';
		if (($ret_code=$db_conn->query($sql)) === false) {
			$this->logging->log('No result in query : ' . $sql,WARN,'');
			return null;
		}
		$value=$ret_code->fetch();
		if ($value != null && isset($value['value']))
		{
			return $value['value'];
		}
		return null;
	}
	
	/** OBSOLETE Send log. Throws exception on critical error
	*	@param	string $message Message to log
	*	@param	int $level 1=critical 2=warning 3=trace 4=debug
	*	@param  string $destination file/syslog/display
	*	@return void
	**/	
	public function trapLog( $message, $level, $destination ='') // OBSOLETE
	{	
		// TODO : replace ref with $this->logging->log 
	    $this->logging->log($message, $level, $destination);
	}
	
	public function setLogging($debugLvl,$outputType,$outputOption=null)  // OBSOLETE
	{
		$this->logging->setLogging($debugLvl, $outputType,$outputOption);
	}
	
	protected function getAPI()
	{
	    if ($this->icinga2api == null)
	    {
	        $this->icinga2api = new Icinga2API($this->api_hostname,$this->api_port);
	    }
	    return $this->icinga2api;
	}
	
	
	/** 
	 * read data from stream
	*	@param $stream string input stream, defaults to "php://stdin"
	*	@return mixed array trap data or exception with error
	*/
	public function read_trap($stream='php://stdin')
	{
		//Read data from snmptrapd from stdin
		$input_stream=fopen($stream, 'r');

		if ($input_stream === false)
		{
		    $this->writeTrapErrorToDB("Error reading trap (code 1/Stdin)");
			$this->logging->log("Error reading stdin !",ERROR,'');
			return null; // note : exception thrown by logging
		}

		// line 1 : host
		$this->receivingHost=chop(fgets($input_stream));
		if ($this->receivingHost === false)
		{
		    $this->writeTrapErrorToDB("Error reading trap (code 1/Line Host)");
			$this->logging->log("Error reading Host !",ERROR,''); 
		}
		// line 2 IP:port=>IP:port
		$IP=chop(fgets($input_stream));
		if ($IP === false)
		{
		    $this->writeTrapErrorToDB("Error reading trap (code 1/Line IP)");
			$this->logging->log("Error reading IP !",ERROR,''); 
		}
		$matches=array();
		$ret_code=preg_match('/.DP: \[(.*)\]:(.*)->\[(.*)\]:(.*)/',$IP,$matches);
		if ($ret_code===0 || $ret_code===false) 
		{
		    $this->writeTrapErrorToDB("Error parsing trap (code 2/IP)");
			$this->logging->log('Error parsing IP : '.$IP,ERROR,'');
		} 
		else 
		{		
			$this->trap_data['source_ip']=$matches[1];
			$this->trap_data['destination_ip']=$matches[3];
			$this->trap_data['source_port']=$matches[2];
			$this->trap_data['destination_port']=$matches[4];
		}

		while (($vars=fgets($input_stream)) !==false)
		{
			$vars=chop($vars);
			$ret_code=preg_match('/^([^ ]+) (.*)$/',$vars,$matches);
			if ($ret_code===0 || $ret_code===false) 
			{
				$this->logging->log('No match on trap data : '.$vars,WARN,'');
			}
			else 
			{
			    if (($matches[1]=='.1.3.6.1.6.3.1.1.4.1.0') || ($matches[1]=='.1.3.6.1.6.3.1.1.4.1'))
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
		    $this->writeTrapErrorToDB("No trap oid found : check snmptrapd configuration (code 3/OID)",$this->trap_data['source_ip']);
			$this->logging->log('no trap oid found',ERROR,'');
		} 

		// Translate oids.
		
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

	/** 
	 * Translate oid into array(MIB,Name)
	* @param $oid string oid to translate
	* @return mixed : null if not found or array(MIB,Name)
	*/
	public function translateOID($oid)
	{
		// try from database
		$db_conn=$this->trapsDB->db_connect_trap();
		
		$sql='SELECT mib,name from '.$this->db_prefix.'mib_cache WHERE oid=\''.$oid.'\';';
		$this->logging->log('SQL query : '.$sql,DEBUG );
		if (($ret_code=$db_conn->query($sql)) === false) {
			$this->logging->log('No result in query : ' . $sql,ERROR,'');
		}
		$name=$ret_code->fetch();
		if ($name['name'] != null)
		{
			return array('trap_name_mib'=>$name['mib'],'trap_name'=>$name['name']);
		}
		
		// Also check if it is an instance of OID
		$oid_instance=preg_replace('/\.[0-9]+$/','',$oid);
		
		$sql='SELECT mib,name from '.$this->db_prefix.'mib_cache WHERE oid=\''.$oid_instance.'\';';
		$this->logging->log('SQL query : '.$sql,DEBUG );
		if (($ret_code=$db_conn->query($sql)) === false) {
			$this->logging->log('No result in query : ' . $sql,ERROR,'');
		}
		$name=$ret_code->fetch();
		if ($name['name'] != null)
		{
			return array('trap_name_mib'=>$name['mib'],'trap_name'=>$name['name']);
		}
		
		// Try to get oid name from snmptranslate
		$translate=exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
		    ' '.$oid);
		$matches=array();
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code===0 || $ret_code === false) {
			return NULL;
		} else {
			$this->logging->log('Found name with snmptrapd and not in DB for oid='.$oid,INFO);
			return array('trap_name_mib'=>$matches[1],'trap_name'=>$matches[2]);
		}	
	}
	
	/** 
	 * Erase old trap records 
	*	@param integer $days : erase traps when more than $days old
	*	@return integer : number of lines deleted
	**/
	public function eraseOldTraps($days=0)
	{
		if ($days==0)
		{
			if (($days=$this->getDBConfig('db_remove_days')) == null)
			{
				$this->logging->log('No days specified & no db value : no tap erase' ,WARN,'');
				return;
			}
		}
		$db_conn=$this->trapsDB->db_connect_trap();
		$daysago = strtotime("-".$days." day");
		$sql= 'delete from '.$this->db_prefix.'received where date_received < \''.date("Y-m-d H:i:s",$daysago).'\';';
		if ($db_conn->query($sql) === false) {
			$this->logging->log('Error erasing traps : '.$sql,ERROR,'');
		}
		$this->logging->log('Erased traps older than '.$days.' day(s) : '.$sql,INFO);
	}

	/** Write error to received trap database
	 */
	public function writeTrapErrorToDB($message,$sourceIP=null,$trapoid=null)
	{
	    
	    $db_conn=$this->trapsDB->db_connect_trap();
	    
	    // add date time
	    $insert_col ='date_received,status';
	    $insert_val = "'" . date("Y-m-d H:i:s")."','error'";
        
	    if ($sourceIP !=null)
	    {
	        $insert_col .=',source_ip';
	        $insert_val .=",'". $sourceIP ."'";
	    }
	    if ($trapoid !=null)
	    {
	        $insert_col .=',trap_oid';
	        $insert_val .=",'". $trapoid ."'";
	    }
	    $insert_col .=',status_detail';
	    $insert_val .=",'". $message ."'";
	    
	    $sql= 'INSERT INTO '.$this->db_prefix.'received (' . $insert_col . ') VALUES ('.$insert_val.')';
	    
	    switch ($this->trapsDB->trapDBType)
	    {
	        case 'pgsql':
	            $sql .= ' RETURNING id;';
	            $this->logging->log('sql : '.$sql,INFO);
	            if (($ret_code=$db_conn->query($sql)) === false) {
	                $this->logging->log('Error SQL insert : '.$sql,1,'');
	            }
	            $this->logging->log('SQL insertion OK',INFO );
	            // Get last id to insert oid/values in secondary table
	            if (($inserted_id_ret=$ret_code->fetch(PDO::FETCH_ASSOC)) === false) {
	                
	                $this->logging->log('Erreur recuperation id',1,'');
	            }
	            if (! isset($inserted_id_ret['id'])) {
	                $this->logging->log('Error getting id',1,'');
	            }
	            $this->trap_id=$inserted_id_ret['id'];
	            break;
	        case 'mysql':
	            $sql .= ';';
	            $this->logging->log('sql : '.$sql,INFO );
	            if ($db_conn->query($sql) === false) {
	                $this->logging->log('Error SQL insert : '.$sql,1,'');
	            }
	            $this->logging->log('SQL insertion OK',INFO );
	            // Get last id to insert oid/values in secondary table
	            $sql='SELECT LAST_INSERT_ID();';
	            if (($ret_code=$db_conn->query($sql)) === false) {
	                $this->logging->log('Erreur recuperation id',1,'');
	            }
	            
	            $inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
	            if ($inserted_id==false) throw new Exception("Weird SQL error : last_insert_id returned false : open issue");
	            $this->trap_id=$inserted_id;
	            break;
	        default:
	            $this->logging->log('Error SQL type unknown  : '.$this->trapsDB->trapDBType,1,'');
	    }
	    
	    $this->logging->log('id found: '. $this->trap_id,INFO );    
	}
	
	/** Write trap data to trap database
	*/
	public function writeTrapToDB()
	{
		
		// If action is ignore -> don't send t DB
		if ($this->trap_to_db === false) return;
		
		
		$db_conn=$this->trapsDB->db_connect_trap();
		
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
			$insert_col .= $col ;
			$insert_val .= ($val==null)? 'NULL' : $db_conn->quote($val);
			$firstcol=0;
		}
		
		$sql= 'INSERT INTO '.$this->db_prefix.'received (' . $insert_col . ') VALUES ('.$insert_val.')';
		switch ($this->trapsDB->trapDBType)
		{
			case 'pgsql': 
				$sql .= ' RETURNING id;';
				$this->logging->log('sql : '.$sql,INFO );
				if (($ret_code=$db_conn->query($sql)) === false) {
					$this->logging->log('Error SQL insert : '.$sql,ERROR,'');
				}
				$this->logging->log('SQL insertion OK',INFO );
				// Get last id to insert oid/values in secondary table
				if (($inserted_id_ret=$ret_code->fetch(PDO::FETCH_ASSOC)) === false) {
														   
					$this->logging->log('Erreur recuperation id',ERROR,'');
				}
				if (! isset($inserted_id_ret['id'])) {
					$this->logging->log('Error getting id',ERROR,'');
				}
				$this->trap_id=$inserted_id_ret['id'];
			break;
			case 'mysql': 
				$sql .= ';';
				$this->logging->log('sql : '.$sql,INFO );
				if ($db_conn->query($sql) === false) {
					$this->logging->log('Error SQL insert : '.$sql,ERROR,'');
				}
				$this->logging->log('SQL insertion OK',INFO );
				// Get last id to insert oid/values in secondary table
				$sql='SELECT LAST_INSERT_ID();';
				if (($ret_code=$db_conn->query($sql)) === false) {
					$this->logging->log('Erreur recuperation id',ERROR,'');
				}

				$inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
				if ($inserted_id==false) throw new Exception("Weird SQL error : last_insert_id returned false : open issue");
				$this->trap_id=$inserted_id;
			break;
			default: 
				$this->logging->log('Error SQL type unknown : '.$this->trapsDB->trapDBType,ERROR,'');
		}
		$this->logging->log('id found: '.$this->trap_id,INFO );
		
		// Fill trap extended data table
		foreach ($this->trap_data_ext as $value) {			
			// TODO : detect if trap value is encoded and decode it to UTF-8 for database
			$firstcol=1;
			$value->trap_id = $this->trap_id;
			$insert_col='';
			$insert_val='';
			foreach ($value as $col => $val)
			{
				if ($firstcol==0) 
				{
					$insert_col .=',';
					$insert_val .=',';
				}
				$insert_col .= $col;
				$insert_val .= ($val==null)? 'NULL' : $db_conn->quote($val);
				$firstcol=0;
			}

			$sql= 'INSERT INTO '.$this->db_prefix.'received_data (' . $insert_col . ') VALUES ('.$insert_val.');';			

			if ($db_conn->query($sql) === false) {
				$this->logging->log('Erreur insertion data : ' . $sql,WARN,'');
			}	
		}	
	}

	/** Get rules from rule database with ip and oid
	*	@param $ip string ipv4 or ipv6
	*	@param $oid string oid in numeric
	*	@return mixed : PDO object or false
	*/	
	protected function getRules($ip,$oid)
	{
		$db_conn=$this->trapsDB->db_connect_trap();
		// fetch rules based on IP in rule and OID
		$sql='SELECT * from '.$this->db_prefix.'rules WHERE trap_oid=\''.$oid.'\' ';
		$this->logging->log('SQL query : '.$sql,DEBUG );
		if (($ret_code=$db_conn->query($sql)) === false) {
			$this->logging->log('No result in query : ' . $sql,WARN,'');
			return false;
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
				//TODO : get host name by API (and check if correct in rule).
				$rule_ret_key++;
				continue;
			}
			// TODO : get hosts IP by API
			if (isset($rule['host_group_name']) && $rule['host_group_name']!=null)
			{ // get ips of group members by oid
				$db_conn2=$this->trapsDB->db_connect_ido();
				$sql="SELECT m.host_object_id, a.address as ip4, a.address6 as ip6, b.name1 as host_name
						FROM icinga_objects as o
						LEFT JOIN icinga_hostgroups as h ON o.object_id=h.hostgroup_object_id
						LEFT JOIN icinga_hostgroup_members as m ON h.hostgroup_id=m.hostgroup_id
						LEFT JOIN icinga_hosts as a ON a.host_object_id = m.host_object_id
						LEFT JOIN icinga_objects as b ON b.object_id = a.host_object_id
						WHERE o.name1='".$rule['host_group_name']."';";
				if (($ret_code2=$db_conn2->query($sql)) === false) {
					$this->logging->log('No result in query : ' . $sql,WARN,'');
					continue;
				}
				$grouphosts=$ret_code2->fetchAll();
				//echo "rule grp :\n";print_r($grouphosts);echo "\n";
				foreach ( $grouphosts as $host)
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
	*	@param id int : rule id
	*   @param set int : value to set
	*/
	protected function add_rule_match($id, $set)
	{
		$db_conn=$this->trapsDB->db_connect_trap();
		$sql="UPDATE ".$this->db_prefix."rules SET num_match = '".$set."' WHERE (id = '".$id."');";
		if ($db_conn->query($sql) === false) {
			$this->logging->log('Error in update query : ' . $sql,WARN,'');
		}
	}
	
	/** Send SERVICE_CHECK_RESULT with icinga2cmd or API
	 * 
	 * @param string $host
	 * @param string $service
	 * @param integer $state numerical staus 
	 * @param string $display
	 * @returnn bool true is service check was sent without error
	*/
	public function serviceCheckResult($host,$service,$state,$display)
	{
	    if ($this->api_use === false)
	    {
    		$send = '[' . date('U') .'] PROCESS_SERVICE_CHECK_RESULT;' .
    			$host.';' .$service .';' . $state . ';'.$display;
    		$this->logging->log( $send." : to : " .$this->icinga2cmd,INFO );
    		
    		// TODO : file_put_contents & fopen (,'w' or 'a') does not work. See why. Or not as using API will be by default....
    		exec('echo "'.$send.'" > ' .$this->icinga2cmd);
    		return true;
	    }
	    else
	    {
	        $api = $this->getAPI();
	        $api->setCredentials($this->api_username, $this->api_password);
	        list($retcode,$retmessage)=$api->serviceCheckResult($host,$service,$state,$display);
	        if ($retcode == false)
	        {
	            $this->logging->log( "Error sending result : " .$retmessage,WARN,'');
	            return false;
	        }
	        else 
	        {
	            $this->logging->log( "Sent result : " .$retmessage,INFO );
	            return true;
	        }
	    }
	}
	
	public function getHostByIP($ip)
	{
	    $api = $this->getAPI();
	    $api->setCredentials($this->api_username, $this->api_password);
	    return $api->getHostByIP($ip);
	}
	
	/** Resolve display. 
	*	Changes OID(<oid>) to value if found or text "<not in trap>"
	*	@param $display string
	*	@return string display
	*/
	protected function applyDisplay($display)
	{
	    $matches=array();
	    while (preg_match('/_OID\(([0-9\.]+)\)/',$display,$matches) == 1)
		{
			$oid=$matches[1];
			$found=0;
			foreach($this->trap_data_ext as $val)
			{
				if ($oid == $val->oid)
				{
					$val->value=preg_replace('/"/','',$val->value);
					$rep=0;
					$display=preg_replace('/_OID\('.$oid.'\)/',$val->value,$display,-1,$rep);
					if ($rep==0)
					{
						$this->logging->log("Error in display",WARN,'');
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
					$this->logging->log("Error in display",WARN,'');
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
		
		if ($rules===false || count($rules)==0)
		{
			$this->logging->log('No rules found for this trap',INFO );
			$this->trap_data['status']='unknown';
			$this->trap_to_db=true;
			return;
		}
		//print_r($rules);
		// Evaluate all rules in sequence
		$this->trap_action=null;
		foreach ($rules as $rule)
		{
			
			$host_name=$rule['host_name'];
			$service_name=$rule['service_name'];
			
			$display=$this->applyDisplay($rule['display']);
			$this->trap_action = ($this->trap_action==null)? '' : $this->trap_action . ', ';
			try
			{
				$this->logging->log('Rule to eval : '.$rule['rule'],INFO );
				$evalr=$this->ruleClass->eval_rule($rule['rule'], $this->trap_data_ext) ;
				//->eval_rule($rule['rule']);
				
				if ($evalr == true)
				{
					//$this->logging->log('rules OOK: '.print_r($rule),INFO );
					$action=$rule['action_match'];
					$this->logging->log('action OK : '.$action,INFO );
					if ($action >= 0)
					{
						if ($this->serviceCheckResult($host_name,$service_name,$action,$display) == false)
						{
						    $this->trap_action.='Error sending status : check cmd/API';
						}
						else
						{
						    $this->add_rule_match($rule['id'],$rule['num_match']+1);
						    $this->trap_action.='Status '.$action.' to '.$host_name.'/'.$service_name;
						}
					}
					else
					{
						$this->add_rule_match($rule['id'],$rule['num_match']+1);
					}
					$this->trap_to_db=($action==-2)?false:true;
				}
				else
				{
					//$this->logging->log('rules KOO : '.print_r($rule),INFO );
					
					$action=$rule['action_nomatch'];
					$this->logging->log('action NOK : '.$action,INFO );
					if ($action >= 0)
					{
					    if ($this->serviceCheckResult($host_name,$service_name,$action,$display)==false)
					    {
					        $this->trap_action.='Error sending status : check cmd/API';
					    }
					    else
					    {
    						$this->add_rule_match($rule['id'],$rule['num_match']+1);
    						$this->trap_action.='Status '.$action.' to '.$host_name.'/'.$service_name;
					    }
					}
					else
					{
						$this->add_rule_match($rule['id'],$rule['num_match']+1);
					}
					$this->trap_to_db=($action==-2)?false:true;					
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
			    $this->logging->log('Error in rule eval : '.$e->getMessage(),WARN,'');
			    $this->trap_action.=' ERR : '.$e->getMessage();
			    $this->trap_data['status']='error';
			}
			
		}
		if ($this->trap_data['status']=='error')
		{
		  $this->trap_to_db=true; // Always put errors in DB for the use can see
		}
		else
		{
		  $this->trap_data['status']='done';
		}
	}

	/** Add Time a action to rule
	*	@param string $time : time to process to insert in SQL
	*/
	public function add_rule_final($time)
	{
		$db_conn=$this->trapsDB->db_connect_trap();
		if ($this->trap_action==null) 
		{
			$this->trap_action='No action';
		}
		$sql="UPDATE ".$this->db_prefix."received SET process_time = '".$time."' , status_detail='".$this->trap_action."'  WHERE (id = '".$this->trap_id."');";
		if ($db_conn->query($sql) === false) {
			$this->logging->log('Error in update query : ' . $sql,WARN,'');
		}
	}
	
	/*********** UTILITIES *********************/
	
	/** reset service to OK after time defined in rule
	*	TODO logic is : get all service in error + all rules, see if getting all rules then select services is better 
	*	@return integer : not in use
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
		$db_conn=$this->trapsDB->db_connect_ido();
		if (($services_db=$db_conn->query($sql_query)) === false) { // set err to 1 to throw exception.
			$this->logging->log('No result in query : ' . $sql_query,ERROR,'');
			return 0;
		}
		$services=$services_db->fetchAll();
		
		// Get all rules
		$sql_query="SELECT host_name, service_name, revert_ok FROM ".$this->db_prefix."rules where revert_ok != 0;";
		$db_conn2=$this->trapsDB->db_connect_trap();
		if (($rules_db=$db_conn2->query($sql_query)) === false) {
			$this->logging->log('No result in query : ' . $sql_query,ERROR,'');
			return 0;
		}
		$rules=$rules_db->fetchAll();
		
		$now=date('U');
		
		$numreset=0;
		foreach ($rules as $rule)
		{
			foreach ($services as $service)
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

	
}

?>