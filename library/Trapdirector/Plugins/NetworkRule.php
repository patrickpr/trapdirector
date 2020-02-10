<?php

namespace Trapdirector\Plugins;

use Trapdirector\PluginTemplate;
use Exception;

/**
 * Network functions plugin
 * This class is declaring functions : 
 * 	- inNetwork 
 * 	- 
 * If something goes wrong, just throw exception as it will be catched by caller
 * Logging is provided with $this->log(<message>,<level>) with level = DEBUG|INFO|WARN|CRIT.
 * A CRIT level throws an exception from the log function.
 * 
 * Default directory for plugins is : ../Plugins/
 *
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage plugins
 */
class NetworkRule extends PluginTemplate
{        
    /** @var string $description Description of plugin */
    public $description='Network functions to use into rules';
    
    /** @var array[] $functions Functions of this plugin for rule eval. 
     * If no functions are declared, set to empty array
     * $functions[<name>]['function'] : Name of the function to be called in this class
     * $functions[<name>]['params'] : Description of input parameters of function.
     * $functions[<name>]['description'] : Description. Can be multiline.
    */
    public $functions=array(
        'inNetwork' => array( // The name of the function in rules
            'function'      =>  'isInNetwork', // Name of the function 
            'params'        =>  '<IP to test>,<Network IP>,<Network mask (CIDR)>', // parameters description
            'description'   =>  'Test if IP is in network, ex : __inNetwork(192.168.123.5,192.168.123.0,24) returns true
Does not work with IPV6' // Description (can be multiline).
        ),
        'test' => array( // The name of the function in rules
            'function'      =>  'testParam', // Name of the function
            'params'        =>  '<boolean to return as string>', // parameters description
            'description'   =>  'Returns value passed as argument' // Description (can be multiline).
        )
    );
    
    /** @var boolean $catchAllTraps Set to true if all traps will be sent to the plugin NOT IMPLEMENTED */
    public $catchAllTraps=false;
    
    /** @var boolean $processTraps Set to true if plugins can handle traps NOT IMPLEMENTED */
    public $processTraps=false;
    
    /**
     * Constructor. Can throw exceptions on error, but no logging at this point.
     * @throws \Exception
     * @return \Trapdirector\Plugins\NetworkRule
     */
    function __construct()
    {
        $this->name=basename(__FILE__,'.php');
        return $this;
    }
    
    /**
     * Function called by trapdirector if found in rules
     * Parameters check has to be done in function.
     * @param array $params Function parameters
     * @throws Exception
     * @return bool Evaluation 
     */
    public function isInNetwork(array $params) : bool
    {
        // Check param numbers and thrown exception if not correct.
        if (count($params)!=3)
        {
            throw new Exception('Invalid number of parameters : ' . count($params));
        }
        
        $ip = $params[0];
        $net = $params[1];
        $masq = $params[2];
        
        
        $this->log('#'. $ip . '# / #' . $net . '# / #' . $masq,DEBUG);
        
        $ip2 = ip2long($ip);
        $net2 = ip2long($net);
        
        if ($ip2 === false )
        {
            $this->log('Invalid IP : #' . $ip.'#',WARN);
            throw new Exception('Invalid IP');
        }
        if ($net2 === false)
        {
            $this->log('Invalid network',WARN);
            throw new Exception('Invalid net');
        }
        if ($masq<1 || $masq > 32)
        {
            $this->log('Invalid masq',WARN);
            throw new Exception('Invalid net masq');
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24

        $masq = pow( 2, ( 32 - $masq ) ) - 1;
        $masq = ~ $masq;
        return ( ( $ip2 & $masq ) == ( $net2 & $masq ) );
        
    }
    
    public function testParam(array $param)
    {
        if (count($param)!=1)
        {
            throw new Exception('Invalid number of parameters : ' . count($param));
        }
        if ($param[0] == 'true') return true;
        return false;
    }
}


