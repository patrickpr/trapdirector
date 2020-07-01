<?php

namespace Trapdirector;

use Icinga\Module\Trapdirector\Icinga2API;
use Exception;
use stdClass as stdClass;
use PDO;

/**
 * Main Trapdirector trap manager 
 * 
 * @license GPL
 * @author Patrick Proy
 * @package Trapdirector
 * @subpackage Processing
 */
class Trap
{
    use TrapConfig;
    
    // Configuration files and dirs
    /** @var string Icinga etc path */
    protected $icingaweb2Etc;
    /** @var string $trapModuleConfig config.ini of module */
    protected $trapModuleConfig;
    /** @var string $icingaweb2Ressources resources.ini of icingaweb2 */
    protected $icingaweb2Ressources;
    // Options from config.ini (default values)
    /** @var string $snmptranslate */
    protected $snmptranslate='/usr/bin/snmptranslate';
    /** @var string $snmptranslate_dirs */
    protected $snmptranslate_dirs='/usr/share/icingaweb2/modules/trapdirector/mibs';
    /** @var string $icinga2cmd */
    protected $icinga2cmd='/var/run/icinga2/cmd/icinga2.cmd';
    /** @var string $dbPrefix */
    protected $dbPrefix='traps_';
    
    // API
    /** @var boolean $apiUse */
    protected $apiUse=false;
    /** @var Icinga2API $icinga2api */
    protected $icinga2api=null;
    /** @var string $apiHostname */
    protected $apiHostname='';
    /** @var integer $apiPort */
    protected $apiPort=0;
    /** @var string $apiUsername */
    protected $apiUsername='';
    /** @var string $apiPassword */
    protected $apiPassword='';
    
    // Logs
    /** @var Logging Logging class. */
    public $logging;    //< Logging class.
    /** @var bool true if log was setup in constructor */
    protected $logSetup;   //< bool true if log was setup in constructor
    
    // Databases
    /** @var Database $trapsDB  Database class*/
    public $trapsDB = null;
    
    // Trap received data
    protected $receivingHost;
    /** @var array	Main trap data (oid, source...) */
    public $trapData=array();
    /** @var array $trapDataExt Additional trap data objects (oid/value).*/
    public $trapDataExt=array(); 
    /** @var int $trapId trap_id after sql insert*/
    public $trapId=null;
    /** @var string $trapAction trap action for final write*/
    public $trapAction=null;
    /** @var boolean $trapToDb log trap to DB */
    protected $trapToDb=true;
    
    /** @var Mib mib class */
    public $mibClass = null;
    
    /** @var Rule rule class */
    public $ruleClass = null;
    
    /** @var Plugins plugins manager **/
    public $pluginClass = null;
    
    /** @var TrapApi $trapApiClass */
    public $trapApiClass = null;
    
    function __construct($etcDir='/etc/icingaweb2',$baseLogLevel=null,$baseLogMode='syslog',$baseLogFile='')
    {
        // Paths of ini files
        $this->icingaweb2Etc=$etcDir;
        $this->trapModuleConfig=$this->icingaweb2Etc."/modules/trapdirector/config.ini";
        $this->icingaweb2Ressources=$this->icingaweb2Etc."/resources.ini";

        //************* Setup logging
        $this->logging = new Logging();
        if ($baseLogLevel != null)
        {
            $this->logging->setLogging($baseLogLevel, $baseLogMode,$baseLogFile);
            $this->logSetup=true;
        }
        else
        {
            $this->logSetup=false;
        }
        $this->logging->log('Loggin started', INFO);
        
        
        // Create distributed API object
        
        $this->trapApiClass = new TrapApi($this->logging);
        
        //*************** Get options from ini files
        if (! is_file($this->trapModuleConfig))
        {
            throw new Exception("Ini file ".$this->trapModuleConfig." does not exists");
        }
        $trapConfig=parse_ini_file($this->trapModuleConfig,true);
        if ($trapConfig == false)
        {
            $this->logging->log("Error reading ini file : ".$this->trapModuleConfig,ERROR,'syslog');
            throw new Exception("Error reading ini file : ".$this->trapModuleConfig);
        }
        $this->getMainOptions($trapConfig); // Get main options from ini file
        
        //*************** Setup database class & get options
        $this->setupDatabase($trapConfig);
        
        $this->getDatabaseOptions(); // Get options in database
        
        //*************** Setup API
        if ($this->apiUse === true) $this->getAPI(); // Setup API
        
        //*************** Setup MIB
        $this->mibClass = new Mib($this->logging,$this->trapsDB,$this->snmptranslate,$this->snmptranslate_dirs); // Create Mib class
        
        //*************** Setup Rule
        $this->ruleClass = new Rule($this); //< Create Rule class
        
        $this->trapData=array(  // TODO : put this in a reset function (DAEMON_MODE)
            'source_ip'	=> 'unknown',
            'source_port'	=> 'unknown',
            'destination_ip'	=> 'unknown',
            'destination_port'	=> 'unknown',
            'trap_oid'	=> 'unknown'
        );
        
        //*************** Setup Plugins
        //Create plugin class. Plugins are not loaded here, but by calling registerAllPlugins
        $this->pluginClass = new Plugins($this);
            
            
    }

    /** @return \Trapdirector\Logging   */
    public function getLogging()
    {
        return $this->logging;
    }

    /** @return \Trapdirector\TrapApi   */
    public function getTrapApi()
    {
        return $this->trapApiClass;
    }
    
    /** @return \Trapdirector\Database */
    public function getTrapsDB()
    {
        return $this->trapsDB;
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
    
    /**
     * Returns or create new IcingaAPI object
     * @return \Icinga\Module\Trapdirector\Icinga2API
     */
    protected function getAPI()
    {
        if ($this->icinga2api == null)
        {
            $this->icinga2api = new Icinga2API($this->apiHostname,$this->apiPort);
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
            $this->trapData['source_ip']=$matches[1];
            $this->trapData['destination_ip']=$matches[3];
            $this->trapData['source_port']=$matches[2];
            $this->trapData['destination_port']=$matches[4];
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
                    $this->trapData['trap_oid']=$matches[2];
                }
                else
                {
                    $object= new stdClass;
                    $object->oid =$matches[1];
                    $object->value = $matches[2];
                    array_push($this->trapDataExt,$object);
                }
            }
        }
        
        if ($this->trapData['trap_oid']=='unknown')
        {
            $this->writeTrapErrorToDB("No trap oid found : check snmptrapd configuration (code 3/OID)",$this->trapData['source_ip']);
            $this->logging->log('no trap oid found',ERROR,'');
        }
        
        // Translate oids.
        
        $retArray=$this->translateOID($this->trapData['trap_oid']);
        if ($retArray != null)
        {
            $this->trapData['trap_name']=$retArray['trap_name'];
            $this->trapData['trap_name_mib']=$retArray['trap_name_mib'];
        }
        foreach ($this->trapDataExt as $key => $val)
        {
            $retArray=$this->translateOID($val->oid);
            if ($retArray != null)
            {
                $this->trapDataExt[$key]->oid_name=$retArray['trap_name'];
                $this->trapDataExt[$key]->oid_name_mib=$retArray['trap_name_mib'];
            }
        }
        
        
        $this->trapData['status']= 'waiting';
        
        return $this->trapData;
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
        
        $sql='SELECT mib,name from '.$this->dbPrefix.'mib_cache WHERE oid=\''.$oid.'\';';
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
        
        $sql='SELECT mib,name from '.$this->dbPrefix.'mib_cache WHERE oid=\''.$oid_instance.'\';';
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
        $sql= 'delete from '.$this->dbPrefix.'received where date_received < \''.date("Y-m-d H:i:s",$daysago).'\';';
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
        
        $sql= 'INSERT INTO '.$this->dbPrefix.'received (' . $insert_col . ') VALUES ('.$insert_val.')';
        
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
                $this->trapId=$inserted_id_ret['id'];
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
                $this->trapId=$inserted_id;
                break;
            default:
                $this->logging->log('Error SQL type unknown  : '.$this->trapsDB->trapDBType,1,'');
        }
        
        $this->logging->log('id found: '. $this->trapId,INFO );
    }
    
    /** Write trap data to trap database
     */
    public function writeTrapToDB()
    {
        
        // If action is ignore -> don't send t DB
        if ($this->trapToDb === false) return;
        
        
        $db_conn=$this->trapsDB->db_connect_trap();
        
        $insert_col='';
        $insert_val='';
        // add date time
        $this->trapData['date_received'] = date("Y-m-d H:i:s");
        
        $firstcol=1;
        foreach ($this->trapData as $col => $val)
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
        
        $sql= 'INSERT INTO '.$this->dbPrefix.'received (' . $insert_col . ') VALUES ('.$insert_val.')';
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
                $this->trapId=$inserted_id_ret['id'];
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
                $this->trapId=$inserted_id;
                break;
            default:
                $this->logging->log('Error SQL type unknown : '.$this->trapsDB->trapDBType,ERROR,'');
        }
        $this->logging->log('id found: '.$this->trapId,INFO );
        
        // Fill trap extended data table
        foreach ($this->trapDataExt as $value) {
            // TODO : detect if trap value is encoded and decode it to UTF-8 for database
            $firstcol=1;
            $value->trap_id = $this->trapId;
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
            
            $sql= 'INSERT INTO '.$this->dbPrefix.'received_data (' . $insert_col . ') VALUES ('.$insert_val.');';
            
            if ($db_conn->query($sql) === false) {
                $this->logging->log('Erreur insertion data : ' . $sql,WARN,'');
            }
        }
    }
    
    /** Get rules from rule database with ip and oid
     *	@param $ip string ipv4 or ipv6
     *	@param $oid string oid in numeric
     *	@return mixed|boolean : PDO object or false
     */
    protected function getRules($ip,$oid)
    {
        $db_conn=$this->trapsDB->db_connect_trap();
        // fetch rules based on IP in rule and OID
        $sql='SELECT * from '.$this->dbPrefix.'rules WHERE trap_oid=\''.$oid.'\' ';
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
        $sql="UPDATE ".$this->dbPrefix."rules SET num_match = '".$set."' WHERE (id = '".$id."');";
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
        if ($this->apiUse === false)
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
            // Get perfdata if found
            $matches=array();
            if (preg_match('/(.*)\|(.*)/',$display,$matches) == 1)
            {
                $display=$matches[1];
                $perfdata=$matches[2];
            }
            else
            {
                $perfdata='';
            }
            
            $api = $this->getAPI();
            $api->setCredentials($this->apiUsername, $this->apiPassword);
            list($retcode,$retmessage)=$api->serviceCheckResult($host,$service,$state,$display,$perfdata);
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
        $api->setCredentials($this->apiUsername, $this->apiPassword);
        return $api->getHostByIP($ip);
    }
    
    /** Resolve display.
     *	Changes _OID(<oid>) to value if found or text "<not in trap>"
     *	@param $display string
     *	@return string display
     */
    protected function applyDisplay($display)
    {
        $matches=array();
        while (preg_match('/_OID\(([0-9\.\*]+)\)/',$display,$matches) == 1)
        {
            $oid=$matches[1];
            $found=0;
            // Test and transform regexp
            $oidR = $this->ruleClass->regexp_eval($oid);
            
            foreach($this->trapDataExt as $val)
            {
                if (preg_match("/^$oidR$/",$val->oid) == 1)
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
        $rules = $this->getRules($this->trapData['source_ip'],$this->trapData['trap_oid']);
        
        if ($rules===false || count($rules)==0)
        {
            $this->logging->log('No rules found for this trap',INFO );
            $this->trapData['status']='unknown';
            $this->trapToDb=true;
            return;
        }
        //print_r($rules);
        // Evaluate all rules in sequence
        $this->trapAction=null;
        foreach ($rules as $rule)
        {
            
            $host_name=$rule['host_name'];
            $service_name=$rule['service_name'];
            
            $display=$this->applyDisplay($rule['display']);
            $this->trapAction = ($this->trapAction==null)? '' : $this->trapAction . ', ';
            try
            {
                $this->logging->log('Rule to eval : '.$rule['rule'],INFO );
                $evalr=$this->ruleClass->eval_rule($rule['rule'], $this->trapDataExt) ;
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
                            $this->trapAction.='Error sending status : check cmd/API';
                        }
                        else
                        {
                            $this->add_rule_match($rule['id'],$rule['num_match']+1);
                            $this->trapAction.='Status '.$action.' to '.$host_name.'/'.$service_name;
                        }
                    }
                    else
                    {
                        $this->add_rule_match($rule['id'],$rule['num_match']+1);
                    }
                    $this->trapToDb=($action==-2)?false:true;
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
                            $this->trapAction.='Error sending status : check cmd/API';
                        }
                        else
                        {
                            $this->add_rule_match($rule['id'],$rule['num_match']+1);
                            $this->trapAction.='Status '.$action.' to '.$host_name.'/'.$service_name;
                        }
                    }
                    else
                    {
                        $this->add_rule_match($rule['id'],$rule['num_match']+1);
                    }
                    $this->trapToDb=($action==-2)?false:true;
                }
                // Put name in source_name
                if (!isset($this->trapData['source_name']))
                {
                    $this->trapData['source_name']=$rule['host_name'];
                }
                else
                {
                    if (!preg_match('/'.$rule['host_name'].'/',$this->trapData['source_name']))
                    { // only add if not present
                        $this->trapData['source_name'].=','.$rule['host_name'];
                    }
                }
            }
            catch (Exception $e)
            {
                $this->logging->log('Error in rule eval : '.$e->getMessage(),WARN,'');
                $this->trapAction.=' ERR : '.$e->getMessage();
                $this->trapData['status']='error';
            }
            
        }
        if ($this->trapData['status']=='error')
        {
            $this->trapToDb=true; // Always put errors in DB for the use can see
        }
        else
        {
            $this->trapData['status']='done';
        }
    }
    
    /** Add Time a action to rule
     *	@param string $time : time to process to insert in SQL
     */
    public function add_rule_final($time)
    {
        $db_conn=$this->trapsDB->db_connect_trap();
        if ($this->trapAction==null)
        {
            $this->trapAction='No action';
        }
        $sql="UPDATE ".$this->dbPrefix."received SET process_time = '".$time."' , status_detail='".$this->trapAction."'  WHERE (id = '".$this->trapId."');";
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
        $sql_query="SELECT host_name, service_name, revert_ok FROM ".$this->dbPrefix."rules where revert_ok != 0;";
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