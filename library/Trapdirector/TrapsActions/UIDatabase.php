<?php

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;

/**
 * Exception for Database test
 *
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage UI
 *
 */
class DBException extends Exception
{
    /** @var array $returnArray */
    private $returnArray;
    
    /**
     * Buil DBException
     * @param array $retarray
     * @param string $message
     * @param int $code
     * @param Exception $previous
     */
    public function __construct(array $retarray, string $message = null, int $code = 0, Exception $previous = null)
    {
        parent::__construct($message,$code,$previous);
        $this->returnArray = $retarray;
    }
    
    /**
     * Get exception array
     * @return array
     */
    public function getArray()
    {
        return $this->returnArray;
    }
}

/**
 * Database functions for user interface
 * 
 * @license GPL
 * @author Patrick Proy
 * @package trapdirector
 * @subpackage UI
 *
 */
class UIDatabase
{
    
    /** @var TrapsController $trapController TrapController 'parent' class */
    private  $trapController;
    
    /** @var Zend_Db_Adapter_Abstract $trapDB Trap Database*/
    private $trapDB;
 
    /** @var Zend_Db_Adapter_Abstract $trapDB Icinga IDO database*/
    private $idoDB;
    
    /** @var array $testResult */
    private $testResult;
    
    /**
     * 
     * @param TrapsController $trapCtrl
     */
    function __construct(TrapsController $trapCtrl)
    {
        $this->trapController=$trapCtrl;
    }
    
    /**
     * Test if database version >= min database version
     * 
     * @param \Zend_Db_Adapter_Abstract $dbConn
     * @param int $min Minimum version
     * @param bool $test Test mode
     * @param string $DBname Name of DB
     * @return bool true if OK, false if version < min version
     * @throws Exception if error and test = true
     */
    protected function testDbVersion($dbAdapter,int $min,bool $test, string $DBname)
    {
        try
        {
            $query = $dbAdapter->select()
            ->from($this->trapController->getModuleConfig()->getDbConfigTableName(),'value')
            ->where('name=\'db_version\'');
            $version=$dbAdapter->fetchRow($query);
            if ( ($version == null) || ! property_exists($version,'value') )
            {
                if ($test === true) 
                {
                    $this->testResult = array(4,$DBname);
                    return false;
                }
                $this->trapController->redirectNow('trapdirector/settings?dberror=4');
                return false;
            }
            if ($version->value < $min)
            {
                if ($test === true) 
                {
                    $this->testResult = array(5,$version->value,$min);
                    return false;
                }
                $this->trapController->redirectNow('trapdirector/settings?dberror=5');
                return false;
            }
        }
        catch (Exception $e)
        {
            if ($test === true) 
            {
                $this->testResult = array(3,$DBname,$e->getMessage());
                return false;
            }
            $this->trapController->redirectNow('trapdirector/settings?dberror=4');
            return false;
        }
        return true;
    }
    
    /**	Get Database connexion
     *	@param $DBname string DB name in resource.ini_ge
     *	@param $test bool if set to true, returns error code and not database
     *	@param $test_version bool if set to flase, does not test database version of trapDB
     *  @throws DBException if test = true and error
     *	@return Zend_Db_Adapter_Abstract|null : if test=false, returns DB connexion, else array(error_num,message) or null on error.
     */
    protected function getDbByName($DBname , $test = false , $test_version = true)
    {
        try
        {
            $dbconn = IcingaDbConnection::fromResourceName($DBname);
        }
        catch (Exception $e)
        {
            if ($test === true) 
            {
                throw new DBException(array(2,$DBname));
            }
            $this->trapController->redirectNow('trapdirector/settings?dberror=2');
            return null;
        }

        try
        {
            $dbAdapter=$dbconn->getDbAdapter();
            
        }
        catch (Exception $e)
        {
            if ($test === true)
            {
                throw new DBException(array(3,$DBname,$e->getMessage()));
            }
            $this->trapController->redirectNow('trapdirector/settings?dberror=3');
            return null;
        }
        
        if ($test_version == true) {
            $testRet=$this->testDbVersion($dbAdapter, $this->trapController->getModuleConfig()->getDbMinVersion(), $test, $DBname);
            if ($testRet !== true) 
            {
                throw new DBException($this->testResult);
            }
        }
 
        return $dbAdapter;
    }

    /**
     * Get Trap database
     * @param boolean $test
     * @return Zend_Db_Adapter_Abstract|array|null : if test=false, returns DB connexion, else array(error_num,message) or null on error.
     */
    public function getDb($test=false)
    {
        if ($this->trapDB != null && $test = false) return $this->trapDB;
        
        $dbresource=$this->trapController->Config()->get('config', 'database');
        
        if ( ! $dbresource )
        {
            if ($test === true) 
            {
                throw new DBException(array(1,''));
            }
            $this->trapController->redirectNow('trapdirector/settings?dberror=1');
            return null;
        }

        $this->trapDB = $this->getDbByName($dbresource,$test,true);
        
        return $this->trapDB;
    }
    
    /**
     * Get IDO Database 
     * @param boolean $test
     * @return Zend_Db_Adapter_Abstract|array]|NULL if test=false, returns DB connexion, else array(error_num,message) or null on error.
     */
    public function getIdoDb($test=false)
    {
        if ($this->idoDB != null && $test === false) return $this->idoDB;
        // TODO : get ido database directly from icingaweb2 config -> (or not if using only API)
        $dbresource=$this->trapController->Config()->get('config', 'IDOdatabase');;
        
        if ( ! $dbresource )
        {
            if ($test === true) 
            {
                throw new DBException(array(1,'No database in config.ini'));
            }
            $this->redirectNow('trapdirector/settings?idodberror=1');
            return null;
        }
        
        try
        {
            $dbconn = IcingaDbConnection::fromResourceName($dbresource);
        }
        catch (Exception $e)
        {
            if ($test === true)
            {
                throw new DBException( array(2,"Database $dbresource does not exists in IcingaWeb2") );
            }
            $this->redirectNow('trapdirector/settings?idodberror=2');
            return null;
        }
        
        if ($test === false)
        {
            $this->idoDB = $dbconn->getDbAdapter();
            return $this->idoDB;
        }
        
        try
        {
            $query = $dbconn->select()
            ->from('icinga_dbversion',array('version'));
            $version=$dbconn->fetchRow($query);
            if ( ($version == null) || ! property_exists($version,'version') )
            {
                return array(4,"$dbresource does not look like an IDO database");
            }
        }
        catch (Exception $e)
        {
            throw new DBException( array(3,"Error connecting to $dbresource : " . $e->getMessage()));
        }
        
        return $this->idoDB;
    }
    
}