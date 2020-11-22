<?php

namespace Trapdirector;

use Exception;


class RuleObject
{
 
    /** @var Logging $logging logging class */
    public $logging;
    /** @var Database $trapsDB Database class */
    public $trapsDB;
    
    /** @var string $oid oid to select rules */
    protected $oid;
    /** @var string $ip source ip to select rules */
    protected $ip;
    
    /** @var array $trapExtensions */
    protected $trapExtensions;
    
    
    /**
     * Setup RuleObject Class
     * @param Logging $logClass : where to log
     * @param Database $dbClass : Database
     */
    function __construct(Logging $logClass,Database $dbClass,string $oid,string $ip, array $trapExtensions)
    {
        $this->logging = $logClass;
        $this->trapsDB = $dbClass;
        $this->oid = $oid;
        $this->ip = $ip;
        $this->trapExtensions = $trapExtensions;
    }
    
    
    
    
}