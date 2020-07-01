<?php

namespace Trapdirector;

use Icinga\Module\Trapdirector\Icinga2API;
use Exception;
use stdClass as stdClass;

/**
 * Distributed status & API calls 
 * 
 * @license GPL
 * @author Patrick Proy
 * @package Trapdirector
 * @subpackage Processing
 */
class TrapApi
{

    // Constants
    public const MASTER=1;
    public const MASTERHA=2;
    public const SAT=3;
    public $stateArray = array('MASTER' => TrapApi::MASTER, 'MASTERHA' => TrapApi::MASTERHA , 'SAT' => TrapApi::SAT  );
    
    /** @var integer $whoami current server : MASTER MASTERHA or SAT */
    public $whoami = TrapApi::MASTER;
    /** @var string $masterIP ip of master if MASTERHA or SAT  */
    public $masterIP='';
    /** @var integer $masterPort port of master if MASTERHA or SAT  */
    public $masterPort=443;
    /** @var string $masterUser user to log in API  */
    public $masterUser='';
    /** @var string $masterPass password */
    public $masterPass='';
    
    /** @var Logging $logging logging class */
    protected $logging;
    
    /**
     * Create TrapApi class
     * @param Logging $logClass
     */
    function __construct($logClass)
    {
        $this->logging=$logClass;
    }

    /**
     * Return true if ode is master.
     * @return boolean
     */
    public function isMaster()
    {
        return ($this->whoami == MASTER);
    }

    /**
     * return status of node
     * @return number
     */
    public function getStatus()
    {
        return $this->whoami;    
    }
    
    /**
     * Set status os node to $status
     * @param string $status
     * @return boolean : true if $status is correct, or false.
     */
    public function setStatus(string $status)
    {
        if (! isset($this->stateArray[$status]))
        {
            return FALSE;
        }
        
        $this->logging->log('Setting status to : ' . $status, INFO);
        
        $this->whoami = $this->stateArray[$status];
        
        return TRUE;
    }
 
    public function setStatusMaster()
    {
        $this->whoami = TrapApi::MASTER;
    }
    
    /**
     * Set params for API connection
     * @param string $IP
     * @param int $port
     * @param string $user
     * @param string $pass
     * @return boolean true if params are OK
     */
    public function setParams(string $IP, int $port, string $user, string $pass)
    {
        $this->masterIP = $IP;
        $this->masterPort = $port;
        $this->masterUser = $user;
        $this->masterPass = $pass;
        
        return true;
    }
    
}