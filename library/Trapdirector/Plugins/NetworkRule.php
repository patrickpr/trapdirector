<?php

namespace Trapdirector\Plugins;

use Trapdirector\PluginTemplate;
use Exception;

/**
 * Network functions plugin
 * Used in rules to to load and execute plugins
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
    public $description='Network functions to use into rules
test test test';
    
    /** @var array[] $functions Functions of this plugin for rule eval. 
     * If no functions are declared, set to empty array
    */
    public $functions=array(
        'inNetwork' => array( // The name of the function 
            'function'      =>  'isInNetwork', // Name of the function in rules
            'params'        =>  '<IP to test>,<Network IP>,<Network mask (CIDR)>', // parameters description
            'description'   =>  'Test if IP is in network, ex : __inNetwork(192.168.123.5,192.168.123.0,24) returns true
Does not work with IPV6' // Description (can be multiline).
        )
    );
    
    /** @var boolean $catchAllTraps Set to true if all traps will be sent to the plugin */
    public $catchAllTraps=false;
    

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
     * 
     * @param array $params Function parameters
     * @throws Exception
     * @return bool Evaluation 
     */
    public function isInNetwork(array $params) : bool
    {
        $this->log('Function params : ' . print_r($params,true),DEBUG);
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
}


