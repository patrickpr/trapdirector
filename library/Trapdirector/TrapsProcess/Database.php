<?php

namespace Trapdirector;

use Trapdirector\Logging;

use Exception;
use PDO;
use PDOException;

class Database
{

    // Databases
    protected $trapDB=null; //< trap database
    protected $idoDB=null; //< ido database
    public $trapDBType; //< Type of database for traps (mysql, pgsql)
    public $idoDBType; //< Type of database for ido (mysql, pgsql)
    
    protected $trapDSN; //< trap database connection params
    protected $trapUsername; //< trap database connection params
    protected $trapPass; //< trap database connection params
    
    protected $idoSet; //< bool true is ido database set
    protected $idoDSN; //< trap database connection params
    protected $idoUsername; //< trap database connection params
    protected $idoPass; //< trap database connection params
    
    // Logging function
    
    protected $logging; //< logging class
    
    /**
     * @param Logging $logClass : where to log
     * @param array $dbParam : array of named params  type,host,dbname,username,[port],[password]
     */
    function __construct($logClass,$dbParam)
    {
        $this->logging=$logClass;
        
        $this->trapDSN=$this->setupDSN($dbParam);
        $this->trapUsername = $dbParam['username'];
        $this->trapPass = (array_key_exists('password', $dbParam)) ? $dbParam['password']:'';
        $this->trapDBType=$dbParam['db'];
        $this->logging->log('DSN : '.$this->trapDSN. ';user '.$this->trapUsername,INFO);
        $this->db_connect_trap();
        
    }
    
    /**
     * Setup and connect to IDO database
     * @param array $dbParam : array of named params
     */
    public function setupIDO($dbParam)
    {
        $this->idoDSN=$this->setupDSN($dbParam);
        $this->idoUsername = $dbParam['username'];
        $this->idoPass = (array_key_exists('password', $dbParam)) ? $dbParam['password']:'';
        $this->logging->log('DSN : '.$this->idoDSN. ';user '.$this->idoUsername,INFO);
        $this->idoDBType=$dbParam['db'];
        $this->db_connect_ido();
    }
    
    /**
     * Connect to IDO database
     * @return \PDO
     */
    public function db_connect_ido()
    {
        if ($this->idoDB != null) {
            // Check if connection is still alive
            try {
                $this->idoDB->query('select 1')->fetchColumn();
                return $this->idoDB;
            } catch (Exception $e) {
                // select 1 failed, try to reconnect.
                $this->logging->log('Database IDO connection lost, reconnecting',WARN);
            }
        }
        try {
            $this->idoDB = new PDO($this->idoDSN,$this->idoUsername,$this->idoPass);
        } catch (PDOException $e) {
            $this->logging->log('Connection failed to IDO : ' . $e->getMessage(),ERROR,'');
        }
        return $this->idoDB;
    }
    
    /**
     * Connect to Trap database
     * @return \PDO
     */
    public function db_connect_trap()
    {
        
        if ($this->trapDB != null) {
            // Check if connection is still alive
            try {
                $this->trapDB->query('select 1')->fetchColumn();
                return $this->trapDB;
            } catch (Exception $e) {
                // select 1 failed, try to reconnect.
                $this->logging->log('Database connection lost, reconnecting',WARN);
            }           
        }       
        try {
            $this->trapDB = new PDO($this->trapDSN,$this->trapUsername,$this->trapPass);
        } catch (PDOException $e) {
            $this->logging->log('Connection failed : ' . $e->getMessage(),ERROR,'');
        }
        return $this->trapDB;
    }
    
    /**
     * Setup dsn and check parameters
     * @param array $configElmt
     * @return string
     */
    protected function setupDSN($configElmt)  
    {
        if (!array_key_exists('db',$configElmt) ||
            !array_key_exists('host',$configElmt) ||
            !array_key_exists('dbname',$configElmt) ||
            !array_key_exists('username',$configElmt))
        {
            $this->logging->log('Missing DB params',ERROR);
            return ''; 
        }
        
        //	$dsn = 'mysql:dbname=traps;host=127.0.0.1';
        $dsn= $configElmt['db'].':dbname='.$configElmt['dbname'].';host='.$configElmt['host'];
        
        if (array_key_exists('port', $configElmt))
        {
            $dsn .= ';port='.$configElmt['port'];
        }
        return $dsn;
    }
}