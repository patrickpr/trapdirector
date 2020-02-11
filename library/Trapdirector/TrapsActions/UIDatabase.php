<?php

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;

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
     * @return NULL|array 
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
                if ($test) return array(4,$DBname);
                $this->trapController->redirectNow('trapdirector/settings?dberror=4');
                return null;
            }
            if ($version->value < $min)
            {
                if ($test) return array(5,$version->value,$min);
                $this->trapController->redirectNow('trapdirector/settings?dberror=5');
                return null;
            }
        }
        catch (Exception $e)
        {
            if ($test) return array(3,$DBname,$e->getMessage());
            $this->trapController->redirectNow('trapdirector/settings?dberror=4');
        }
        return null;
    }
    
    /**	Get Database connexion
     *	@param $DBname string DB name in resource.ini_ge
     *	@param $test bool if set to true, returns error code and not database
     *	@param $test_version bool if set to flase, does not test database version of trapDB
     *	@return Zend_Db_Adapter_Abstract|array|null : if test=false, returns DB connexion, else array(error_num,message) or null on error.
     */
    protected function getDbByName($DBname,$test=false,$test_version=true)
    {
        try
        {
            $dbconn = IcingaDbConnection::fromResourceName($DBname);
        }
        catch (Exception $e)
        {
            if ($test) return array(2,$DBname);
            $this->trapController->redirectNow('trapdirector/settings?dberror=2');
            return null;
        }

        try
        {
            $dbAdapter=$dbconn->getDbAdapter();
            
        }
        catch (Exception $e)
        {
            if ($test) return array(3,$DBname,$e->getMessage());
            $this->trapController->redirectNow('trapdirector/settings?dberror=3');
            return null;
        }
        
        if ($test_version == true) {
            $testRet=$this->testDbVersion($dbAdapter, $this->trapController->getModuleConfig()->getDbMinVersion(), $test, $DBname);
            if ($testRet != null) 
            {
                return $testRet;
            }
        }
        if ($test) return array(0,'');
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
            if ($test) return array(1,'');
            $this->redirectNow('trapdirector/settings?dberror=1');
            return null;
        }
        $retDB=$this->getDbByName($dbresource,$test,true);
        
        if ($test === true) return $retDB;
        
        $this->trapDB=$retDB;
        return $this->trapDB;
    }
    
    /**
     * Get IDO Database 
     * @param boolean $test
     * @return Zend_Db_Adapter_Abstract|array]|NULL if test=false, returns DB connexion, else array(error_num,message) or null on error.
     */
    public function getIdoDb($test=false)
    {
        if ($this->idoDB != null && $test = false) return $this->idoDB;
        // TODO : get ido database directly from icingaweb2 config -> (or not if using only API)
        $dbresource=$this->Config()->get('config', 'IDOdatabase');;
        
        if ( ! $dbresource )
        {
            if ($test) return array(1,'No database in config.ini');
            $this->redirectNow('trapdirector/settings?idodberror=1');
            return null;
        }
        
        try
        {
            $dbconn = IcingaDbConnection::fromResourceName($dbresource);
        }
        catch (Exception $e)
        {
            if ($test) return array(2,"Database $dbresource does not exists in IcingaWeb2");
            $this->redirectNow('trapdirector/settings?idodberror=2');
            return null;
        }
        
        if ($test == false)
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
            return array(3,"Error connecting to $dbresource : " . $e->getMessage());
        }
        
        return array(0,'');
    }
    
}