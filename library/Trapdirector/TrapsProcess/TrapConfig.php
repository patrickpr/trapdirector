<?php

namespace Trapdirector;


/**
 * Read configuration and options
 *
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage Processing
 *
 */
trait TrapConfig
{

    /** @return \Trapdirector\Logging   */
    abstract public function getLogging();
    /** @return \Trapdirector\TrapApi   */
    abstract public function getTrapApi();
    
    /**
     * Get option from array of ini file, send message if empty
     * @param string $option_array Array of ini file
     * @param string $option_category category in ini file
     * @param string $option_name name of option in category
     * @param mixed $option_var variable to fill if found, left untouched if not found
     * @param integer $log_level default 2 (warning)
     * @param string $message warning message if not found
     * @return boolean true if found, or false
     */
    protected function getOptionIfSet($option_array,$option_category,$option_name, &$option_var, $log_level = WARN, $message = null)
    {
        if (!isset($option_array[$option_category][$option_name]))
        {
            if ($message === null)
            {
                $message='No ' . $option_name . ' in config file: '. $this->trapModuleConfig;
            }
            $this->getLogging()->log($message,$log_level);
            return false;
        }
        else
        {
            $option_var=$option_array[$option_category][$option_name];
            return true;
        }
    }

    /**
     * Get options in database
     */
    protected function getDatabaseOptions()
    {
        // Database options
        if ($this->logSetup === false) // Only if logging was no setup in constructor
        {
            $this->getDBConfigIfSet('log_level',$this->getLogging()->debugLevel);
            $this->getDBConfigIfSet('log_destination',$this->getLogging()->outputMode);
            $this->getDBConfigIfSet('log_file',$this->getLogging()->outputFile);
        }
    }
        
    /** Set $variable to value if $element found in database config table
     * @param string $element
     * @param string $variable
     */
    protected function getDBConfigIfSet($element,&$variable)
    {
        $value=$this->getDBConfig($element);
        if ($value != null) $variable=$value;
    }
    
    /**
     *   Get data from db_config
     *	@param $element string name of param
     *	@return mixed : value (or null)
     */
    protected function getDBConfig($element)  // TODO : put this in DB class
    {
        $db_conn=$this->trapsDB->db_connect_trap();
        $sql='SELECT value from '.$this->dbPrefix.'db_config WHERE ( name=\''.$element.'\' )';
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
    
    /**
     * Get options from ini file
     * @param array $trap_config : ini file array
     */
    protected function getMainOptions($trapConfig)
    {
        
        $nodeStatus='';
        $this->getOptionIfSet($trapConfig,'config','node', $nodeStatus);
        if ($this->getTrapApi()->setStatus($nodeStatus) === FALSE)
        {
            $this->getLogging()->log('Unknown node status '.$nodeStatus.' : setting to MASTER',WARN);
            $this->getTrapApi()->setStatusMaster();
        }
        else 
        {
            if ($this->getTrapApi()->getStatus() != TrapApi::MASTER)
            {
                // Get options to connect to API
                $IP = $port = $user =  $pass = null;
                $this->getOptionIfSet($trapConfig,'config','masterIP', $IP, ERROR);
                $this->getOptionIfSet($trapConfig,'config','masterPort', $port, ERROR);
                $this->getOptionIfSet($trapConfig,'config','masterUser', $user, ERROR);
                $this->getOptionIfSet($trapConfig,'config','masterPass', $pass, ERROR);
                $this->getTrapApi()->setParams($IP, $port, $user, $pass);
                return;
            }
        }
        
        // Snmptranslate binary path
        $this->getOptionIfSet($trapConfig,'config','snmptranslate', $this->snmptranslate);
        
        // mibs path
        $this->getOptionIfSet($trapConfig,'config','snmptranslate_dirs', $this->snmptranslate_dirs);
        
        // icinga2cmd path
        $this->getOptionIfSet($trapConfig,'config','icingacmd', $this->icinga2cmd);
        
        // table prefix
        $this->getOptionIfSet($trapConfig,'config','database_prefix', $this->dbPrefix);
        
        // API options
        if ($this->getOptionIfSet($trapConfig,'config','icingaAPI_host', $this->apiHostname))
        {
            $this->apiUse=true;
            // Get API options or throw exception as not configured correctly
            $this->getOptionIfSet($trapConfig,'config','icingaAPI_port', $this->apiPort,ERROR);
            $this->getOptionIfSet($trapConfig,'config','icingaAPI_user', $this->apiUsername,ERROR);
            $this->getOptionIfSet($trapConfig,'config','icingaAPI_password', $this->apiPassword,ERROR);
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
            $this->logging->log("No database in config file: ".$this->trapModuleConfig,ERROR,'');
            return;
        }
        $dbTrapName=$trapConfig['config']['database'];
        $this->logging->log("Found database in config file: ".$dbTrapName,INFO );
        
        if ( ($dbConfig=parse_ini_file($this->icingaweb2Ressources,true)) === false)
        {
            $this->logging->log("Error reading ini file : ".$this->icingaweb2Ressources,ERROR,'');
            return;
        }
        if (!array_key_exists($dbTrapName,$dbConfig))
        {
            $this->logging->log("No database '.$dbTrapName.' in config file: ".$this->icingaweb2Ressources,ERROR,'');
            return;
        }
        
        $this->trapsDB = new Database($this->logging,$dbConfig[$dbTrapName],$this->dbPrefix);
        
        $this->logging->log("API Use : ".print_r($this->apiUse,true),DEBUG );
        
        //TODO enable this again when API queries are all done :
        //if ($this->apiUse === true) return; // In case of API use, no IDO is necessary
        
        // IDO Database
        if (!array_key_exists('IDOdatabase',$trapConfig['config']))
        {
            $this->logging->log("No IDOdatabase in config file: ".$this->trapModuleConfig,ERROR,'');
        }
        $dbIdoName=$trapConfig['config']['IDOdatabase'];
        
        $this->logging->log("Found IDO database in config file: ".$dbIdoName,INFO );
        if (!array_key_exists($dbIdoName,$dbConfig))
        {
            $this->logging->log("No database '.$dbIdoName.' in config file: ".$this->icingaweb2Ressources,ERROR,'');
            return;
        }
        
        $this->trapsDB->setupIDO($dbConfig[$dbIdoName]);
    }
    
}