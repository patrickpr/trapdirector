<?php

namespace Trapdirector;

use Exception as Exception;
use Throwable;
use stdClass;

/**
 * Plugins management class
 * Used to load and execute plugins 
 * Default directory for plugins is : ../Plugins/
 * 
 * @license GPL
 * @author Patrick Proy
 *
 */
class Plugins
{
    /** Array of plugin objects. Keys ar plugin name
     * @var PluginTemplate[] $pluginsList Plugins array with name as index
     * $pluginsList[plugin name]['object']  : plugin object (NULL of not loaded)
     * $pluginsList[plugin name]['allOID']  : bool true if plugin catches all oid
     * $pluginsList[plugin name]['target']  : bool true if plugin can be trap processing target
     * $pluginsList[plugin name]['enabled'] : bool true if plugin is in enabled list 
     **/
    protected $pluginsList = array();

    /** Array of functions names
     * @var array $functionList 
     * $functionList[name]['plugin'] : Plugin name
     * $functionList[name]['function'] : Plugin function to call (null if plugin not loaded)
    */
    protected $functionList=array();
    
    /** @var string[] $enabledPlugins list of enabled plugins */
    //public $enabledPlugins = array();

    
    /** @var Logging $logClass */
    protected $logClass;

    /** @var Trap $trapClass */
    protected $trapClass;
    
    /** @var string $pluginDir */
    protected $pluginDir;
    
    /** Setup class
     * @param Trap $logClass  the top trap class
     * @param string $plugin_dir optional plugin directory
     * @throws \Exception
     */
    function __construct(Trap $trapClass,string $pluginDir='')
    {
        if ($pluginDir == '')
        {
            $this->pluginDir=dirname(__DIR__).'/Plugins';
        }
        else 
        {
            $this->pluginDir=$pluginDir;
        }
        // Set and check Logging class
        $this->trapClass=$trapClass;
        if ($this->trapClass === null)
        {
            throw new Exception('Log class not loaded into trap class');
        }
        $this->logClass=$trapClass->logging;
        if ($this->logClass === null)
        {
            throw new Exception('Log class not loaded into trap class');
        }
        // check DB class and get plugins list.
        if ($this->trapClass->trapsDB === null)
        {
            throw new Exception('Database class not loaded into trap class');
        }
        $this->loadEnabledPlugins();
    }
    
    
    /**
     * Load enabled plugins from database config table.
     * Fills enabledPlugins and functionList properties
     * @throws \Exception
     */
    private function loadEnabledPlugins()
    {
        $PluginList = $this->trapClass->trapsDB->getDBConfig('enabled_plugins');
               
        if ($PluginList === null || $PluginList == '')
        {
            $this->logClass->log('No enabled plugins',DEBUG);
            return;
        }
        else
        {   // Saved config : <plugin name>;<Catch all OID ? 1|0>;<Trap target ? 1|0>;<func 1 name>|<func 2 name>... ,<plugin2 name>....
            $this->logClass->log('Enabled plugins = '.$PluginList,DEBUG);
            
            $pluginArray = explode(',', $PluginList);
            foreach ($pluginArray as $pluginElmt)
            {
                $pluginElmt = explode(';',$pluginElmt);
                if ($pluginElmt === false || count($pluginElmt) != 4)
                {
                    throw new \Exception('Invalid plugin configuration : '. $PluginList );
                }
                $pluginName=$pluginElmt[0];
                
                $pluginListElmt = array();
                $pluginListElmt['object'] = null; // class not loaded
                $pluginListElmt['allOID'] = ($pluginElmt[1]=='1') ? true : false;
                $pluginListElmt['target'] = ($pluginElmt[2]=='1') ? true : false;
                $pluginListElmt['enabled'] = true;
                
                $this->pluginsList[$pluginName] = $pluginListElmt;
                
                // deal with plugin functions
                $pluginFunctions = explode('|',$pluginElmt[3]);
                if ($pluginFunctions !== false)
                {
                    foreach ($pluginFunctions as $function)
                    {
                        $this->functionList[$function] = array(
                            'plugin'    =>   $pluginName,
                            'function'  =>  null
                        );
                    }
                }
            }

        }
        
    }

    /**
     * Save enabled plugin array in DB config
     * @return bool true if OK, or false (error logged by DB Class)
     */
    private function saveEnabledPlugins()
    {
        $saveString='';
        foreach ($this->pluginsList as $name => $value)
        {
            if ($value['enabled'] == false)
            {
                continue;
            }
            $functionString='';
            foreach ($this->functionList as $fName => $fvalue)
            {
                if ($fvalue['plugin'] != $name)
                {
                    continue;
                }
                $functionString .= ($functionString == '') ? '' : '|'; // add separator if not empty
                $functionString .= $fName;
            }
            $saveString .= ($saveString == '')?'':',' ;
            
            $allOID = ($value['allOID'] === true) ? 1 : 0;
            $target = ($value['target'] === true) ? 1 : 0;
            $saveString .= $name . ';' . $allOID . ';' . $target . ';' . $functionString ;
        }
        $this->logClass->log('Saving : ' . $saveString,DEBUG);
        return $this->trapClass->trapsDB->setDBConfig('enabled_plugins', $saveString);
    }
    
    /** Get enabled plugin list by name
     * @return array
     */
    public function getEnabledPlugins() : array
    {
        $retArray=array();
        foreach ($this->pluginsList as $name => $value)
        {
            if ($value['enabled'] == true)
            {
                array_push($retArray,$name);
            }
        }
        return $retArray;
    }

    /** Enable plugin (enabling an enabled plugin is OK, same for disabled).
     *  and save in DB config
     * @param string $pluginName
     * @param bool $enabled true to enable, false to disable
     * @return bool true if OK, or false (error logged)
     */
    public function enablePlugin(string $pluginName,bool $enabled)
    {
        if ($enabled === false)
        {
            // If plugin is defined set to disable
            if ( isset($this->pluginsList[$pluginName]))
            {
                $this->pluginsList[$pluginName]['enabled'] = false;
            }            
            return $this->saveEnabledPlugins();
        }
        // Check if plugin is loaded / exists
        if ( ! isset($this->pluginsList[$pluginName]) || 
                $this->pluginsList[$pluginName]['object'] === null)
        {
            try {
                $this->registerPlugin($pluginName);
            } catch (Exception $e) {
                $this->logClass->log('Cannot enable plugin : ' . $e->getMessage(),WARN);
                return false;
            }
        }
        $this->pluginsList[$pluginName]['enabled'] = true;
        // save in DB and return 
        return $this->saveEnabledPlugins();
    }
   
    /**
     * Destroy plugin objects and reload them with new enabled list.
     * TODO : Code this function (ref DAEMON_MODE)
     */
    public function reloadAllPlugins()
    {
        return;
    }
 
    /** Load plugin by name. Create entry if not in $pluginsList
     * @param string $pluginName Plugin name to load
     * @return bool true if created, false if already loaded
     * @throws Exception on error loading plugin
     */
    public function registerPlugin(string $pluginName)
    {
        if ( ! isset($this->pluginsList[$pluginName]) ) // Plugin isn't enable, create entry
        {
            $pluginListElmt = array();
            $pluginListElmt['object'] = null; // class not loaded
            $pluginListElmt['enabled'] = false;
            $this->pluginsList[$pluginName] = $pluginListElmt;
        }
        
        if ($this->pluginsList[$pluginName]['object'] !== null)
        {
            return false;
        }
        try {
            // Include plugin file
            include_once($this->pluginDir.'/' . $pluginName . '.php');
            
            // Create full class name with namespace
            $pluginClassName = __NAMESPACE__ . '\\Plugins\\' . $pluginName;
            
            // Create class
            $newClass = new $pluginClassName();
            
            // Set logging
            $newClass->setLoggingClass($this->logClass);
            
            // Add in plugin array
            $this->pluginsList[$pluginName]['object']=$newClass;
            $this->pluginsList[$pluginName]['allOID']=$newClass->catchAllTraps;
            $this->pluginsList[$pluginName]['target']=$newClass->processTraps;
            
            // Delete old functions
            foreach ($this->functionList as $fname => $fvalue)
            {
                if ($fvalue['plugin'] == $pluginName)
                {
                    unset($this->functionList[$fname]);
                }
            }
            // Add functions
            foreach ($newClass->functions as $fname => $function)
            {
                if (isset($this->functionList[$fname]))
                {
                    if ($this->functionList[$fname]['plugin'] != $pluginName )
                    {
                        throw new Exception('Duplicate function name '.$fname . ' in ' 
                            . $pluginName . ' and ' . $this->functionList[$fname]['plugin']);
                    }
                    
                }
                else
                {
                    $this->functionList[$fname]=array();
                    $this->functionList[$fname]['plugin'] = $pluginName;
                }
                $this->functionList[$fname]['function']=$function['function'];
            }
            $this->logClass->log('Registered plugin '.$pluginName,DEBUG);
            
        } catch (Exception $e) {
            unset($this->pluginsList[$pluginName]);
            $errorMessage = "Error registering plugin $pluginName : ".$e->getMessage();
            $this->logClass->log($errorMessage,WARN);
            // Disable the plugin
            $this->enablePlugin($pluginName, false);
            throw new \Exception($errorMessage);
        } catch (Throwable $t) {
            unset($this->pluginsList[$pluginName]);
            $errorMessage = $t->getMessage() . ' in file ' . $t->getFile() . ' line ' . $t->getLine();
            $this->logClass->log($errorMessage,WARN);
            // Disable the plugin
            $this->enablePlugin($pluginName, false);
            throw new \Exception($errorMessage);
        }
        return true;
    }
    
    /** Registers all plugins (check=false) or only those with name present in array (check=true)
     * @param bool $checkEnabled Check if plugin is enabled before loading it
     * @return string Errors encountered while registering plugins
     */
    public function registerAllPlugins(bool $checkEnabled=true)
    {
        $retDisplay='';
        // First load enabled plugins
        foreach (array_keys($this->pluginsList) as $pluginName)
        {
            try {
                $this->registerPlugin($pluginName);
            } catch (Exception $e) {
                $retDisplay .= $e->getMessage() . ' / ';
            }
        }
        if ($checkEnabled === false) // Load all php files in plugin dir
        {
            foreach (glob($this->pluginDir."/*.php") as $filename)
            {             
                $pluginName=basename($filename,'.php');
                if (!preg_match('/^[a-zA-Z0-9]+$/',$pluginName))
                {
                    $this->logClass->log("Invalid plugin name : ".$pluginName, WARN);
                    $retDisplay .= "Invalid plugin name : ".$pluginName . " / ";
                    break;
                }
                try { // Already registerd plugin will simply return false
                    $this->registerPlugin($pluginName);               
                } catch (Exception $e) {
                    $retDisplay .= $e->getMessage() . ' / ';
                }
            }
        }
        
        if ($retDisplay == '')
        {
            return 'All plugins loaded OK';
        }
        else
        {
            return $retDisplay;
        }
    }
    
    /**
     * Returns array of name of loaded plugins
     * @return array
     */
    public function pluginList() : array
    {
        return array_keys($this->pluginsList);    
    }

    /**
     * Get plugin details
     * @param string $name name of plugins
     * @return boolean|stdClass result as stdClass or false if plugin not found.
     * @throws \Exception if registering is not possible
     */
    public function pluginDetails(string $name)
    {
        if (!array_key_exists($name, $this->pluginsList))
        {
            return false;
        }
        if ($this->pluginsList[$name]['object'] === null)
        {
            $this->registerPlugin($name); // can throw exception handled by caller
        }
        $retObj = new stdClass();
        $retObj->name           = $name;
        $retObj->catchAllTraps  = $this->pluginsList[$name]['allOID'];
        $retObj->processTraps   = $this->pluginsList[$name]['target'];
        $retObj->description    = $this->pluginsList[$name]['object']->description;
        $functions=array();
        foreach ($this->functionList as $fName => $func)
        {
            if ($func['plugin'] == $name)
            {
                array_push($functions,$fName);
            }
        }
        $retObj->funcArray=$functions;
        return $retObj;
    }
       
    /**
     * Get plugin name from function name
     * @param string $funcName
     * @param string $pluginName
     * @return boolean returns plugin object of false;
     */
    public function getFunction($funcName,&$pluginName)
    {
        if (! isset($this->functionList[$funcName]) )
        {
            return false;
        }
        $pluginName = $this->functionList[$funcName]['plugin'];
        return true;
    }
    
    /**
     * Get functions params and description
     * @param string $funcName
     * @return boolean|stdClass false if not found or object (name,params,description)
     * @throws \Exception if registering is not possible
     */
    public function getFunctionDetails($funcName)
    {
        if (! isset($this->functionList[$funcName]) )
        {
            return false;
        }
        $pluginName = $this->functionList[$funcName]['plugin']; // plugin name
        $plugin = $this->pluginsList[$pluginName]['object']; // plugin object
        if ($plugin === null)
        {
            $this->registerPlugin($pluginName); // can throw exception handled by caller
        }
        $retObj = new stdClass();
        $retObj->name           = $funcName;
        $retObj->plugin         = $pluginName;
        $retObj->params         = $plugin->functions[$funcName]['params'];
        $retObj->description    = $plugin->functions[$funcName]['description'];
        return $retObj;
    }
    
    /**
     * Evaluate function with parameters
     * @param string $funcName
     * @param mixed $params
     * @throws Exception
     * @return bool
     */
    public function getFunctionEval(string $funcName,$params) : bool
    {
        if (! isset($this->functionList[$funcName]) )
        {
            throw new Exception($funcName . ' not found.');
        }
        $pluginName = $this->functionList[$funcName]['plugin']; // plugin name
        $plugin = $this->pluginsList[$pluginName]['object']; // plugin object

        if ($plugin === null)
        {
            $this->registerPlugin($pluginName); // can throw exception handled by caller
            $plugin = $this->pluginsList[$pluginName]['object'];
        }
        
        $propertyName = $this->functionList[$funcName]['function'];
        $this->logClass->log('Using property '. $propertyName . ' of class : '.$pluginName,DEBUG);
        
        return $plugin->{$propertyName}($params);        
    }
    
    public function evaluateFunctionString(string $functionString) : bool
    {
        $matches=array();
        // Cleanup spaces
        //$functionString = $this->trapClass->ruleClass->eval_cleanup($functionString);
        //$this->logClass->log('eval cleanup : '.$functionString,DEBUG);
        
        // Match function call
        $num=preg_match('/^__([a-zA-Z0-9]+)\((.+)\)$/', $functionString , $matches);
        if ($num !=1)
        {
            throw new \ErrorException('Function syntax error : ' . $functionString );
        }
        $this->logClass->log('Got function : '. $matches[1] . ', params : '.$matches[2],DEBUG);
        $funcName=$matches[1];
        
        // Get parameters comma separated
        $funcParams=str_getcsv($matches[2],',','"',"\\");
        $this->logClass->log('Function params : ' . print_r($funcParams,true),DEBUG);
        
        // return evaluation
        return $this->getFunctionEval($funcName, $funcParams);        
        
    }
    
}

abstract class PluginTemplate
{
    
    /** @var Logging $loggingClass */
    private $loggingClass;
    
    /** @var string $name Name of plugin */
    public $name;
    
    /** @var string $description Description of plugin */
    public $description='Default plugin description';
    
    /** @var array $functions Functions of this plugin for rule eval*/
    public $functions=array();
    
    /** @var boolean $catchAllTraps Set to true if all traps will be sent to the plugin */
    public $catchAllTraps=false;
    
    /** @var boolean $processTraps Set to true if plugins can handle traps */
    public $processTraps=false;
    
    /**
     * @param \Trapdirector\Logging $loggingClass
     */
    public function setLoggingClass($loggingClass)
    {
        $this->loggingClass = $loggingClass;
    }
    
    /**
     * 
     * @param string $message
     * @param int $level DEBUG/INFO/WARN/CRIT
     */
    public function log($message,$level)
    {
        $this->loggingClass->log('[ '.get_class($this).'] '. $message, $level);
    }
}