<?php

namespace Icinga\Module\Trapdirector;

use Icinga\Web\Controller;

use Icinga\Data\Paginatable;

//use Exception;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;
use Icinga\Module\Trapdirector\Tables\TrapTableList;
use Icinga\Module\Trapdirector\Tables\TrapTableHostList;
use Icinga\Module\Trapdirector\Tables\HandlerTableList;
use Icinga\Module\Trapdirector\Config\MIBLoader;
use Icinga\Module\Trapdirector\TrapsActions\UIDatabase;

use Trapdirector\Trap;

use Icinga\Data\ConfigObject;

class TrapsController extends Controller
{
	/** @var TrapModuleConfig $moduleConfig TrapModuleConfig instance */
	protected $moduleConfig;
	/** @var TrapTableList $trapTableList (by date)*/
	protected $trapTableList;
	/** @var TrapTableHostList $trapTableHostList TrapTableList (by hosts)*/
	protected $trapTableHostList;
	/** @var HandlerTableList $handlerTableList HandlerTableList instance*/
	protected $handlerTableList;
	/** @var ConfigObject $trapDB Trap database */
	protected $trapDB;
	/** @var ConfigObject $icingaDB Icinga IDO database */
	protected $icingaDB;
	/** @var MIBLoader $MIBData MIBLoader class */
	protected $MIBData;
	/** @var Trap $trapClass Trap class for bin/trap_class.php */
	protected $trapClass;
	/** @var UIDatabase $UIDatabase */
	protected $UIDatabase;
	
	
	
	/** Get instance of TrapModuleConfig class
	*	@return TrapModuleConfig
	*/
	public function getModuleConfig() 
	{
		if ($this->moduleConfig == Null) 
		{
			$db_prefix=$this->Config()->get('config', 'database_prefix');
			if ($db_prefix === null) 
			{
				$this->redirectNow('trapdirector/settings?message=No database prefix');
			}
			$this->moduleConfig = new TrapModuleConfig($db_prefix);
		}
		return $this->moduleConfig;
	}
	
	/**
	 * Get instance of TrapTableList
	 * @return \Icinga\Module\Trapdirector\Tables\TrapTableList
	 */
	public function getTrapListTable() {
		if ($this->trapTableList == Null) {
			$this->trapTableList = new TrapTableList();
			$this->trapTableList->setConfig($this->getModuleConfig());
		}
		return $this->trapTableList;
	}
	
	/**
	 * @return \Icinga\Module\Trapdirector\Tables\TrapTableHostList
	 */
	public function getTrapHostListTable()
	{
	    if ($this->trapTableHostList == Null) 
		{
	        $this->trapTableHostList = new TrapTableHostList();
	        $this->trapTableHostList->setConfig($this->getModuleConfig());
	    }
	    return $this->trapTableHostList;
	}
	
	/**
	 * @return \Icinga\Module\Trapdirector\Tables\HandlerTableList
	 */
	public function getHandlerListTable() 
	{
		if ($this->handlerTableList == Null) 
		{
			$this->handlerTableList = new HandlerTableList();
			$this->handlerTableList->setConfig($this->getModuleConfig());
		}
		return $this->handlerTableList;
	}	

	/**
	 * @return UIDatabase
	 */
	public function getUIDatabase()
	{
	    if ($this->UIDatabase == Null)
	    {
	        $this->UIDatabase = new UIDatabase($this);
	       
	    }
	    return $this->UIDatabase;
	}
	
    protected function applyPaginationLimits(Paginatable $paginatable, $limit = 25, $offset = null)
    {
        $limit = $this->params->get('limit', $limit);
        $page = $this->params->get('page', $offset);

        $paginatable->limit($limit, $page > 0 ? ($page - 1) * $limit : 0);

        return $paginatable;
    }	
	
	public function displayExitError($source,$message)
	{	// TODO : check better ways to transmit data (with POST ?)
		$this->redirectNow('trapdirector/error?source='.$source.'&message='.$message);
	}
	
	protected function checkReadPermission()
	{
        if (! $this->Auth()->hasPermission('trapdirector/view')) {
            $this->displayExitError('Permissions','No permission fo view content');
        }		
	}

	protected function checkConfigPermission()
	{
        if (! $this->Auth()->hasPermission('trapdirector/config')) {
            $this->displayExitError('Permissions','No permission fo configure');
        }		
	}
	
    /**
     * Check if user has write permission
     * @param number $check optional : if set to 1, return true (user has permission) or false instead of displaying error page
     * @return boolean : user has permission
     */
	protected function checkModuleConfigPermission($check=0)
	{
        if (! $this->Auth()->hasPermission('trapdirector/module_config')) {
            if ($check == 0)
            {
                $this->displayExitError('Permissions','No permission fo configure module');
            }
            return false;
        }
        return true;
	}

	/*************************  Trap class get **********************/
	public function getTrapClass()
	{ // TODO : try/catch here ? or within caller
		if ($this->trapClass == null)
		{
			require_once($this->Module()->getBaseDir() .'/bin/trap_class.php');
			$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
			//$debug_level=4;
			$this->trapClass = new Trap($icingaweb2_etc);
			//$Trap->setLogging($debug_level,'syslog');
		}
		return $this->trapClass;
	}
	
	/************************** MIB related **************************/
	
	/** Get MIBLoader class
	*	@return MIBLoader class
	*/
	protected function getMIB()
	{
		if ($this->MIBData == null)
		{
		    $dbConn = $this->getUIDatabase()->getDbConn();
		    if ($dbConn === null) throw new \ErrorException('uncatched db error');
			$this->MIBData=new MIBLoader(
				$this->Config()->get('config', 'snmptranslate'),
				$this->Config()->get('config', 'snmptranslate_dirs'),
			    $dbConn,
				$this->getModuleConfig()
			);
		}
		return $this->MIBData;
	}	
	
	/**************************  Database queries *******************/		
	
	/** Check if director is installed
	*	@return bool true/false
	*/
	protected function isDirectorInstalled()
	{
	    $output=array();
	    exec('icingacli module list',$output);
	    foreach ($output as $line)
		{
			if (preg_match('/^director .*enabled/',$line))
			{
				return true;
			}
		}
		return false;
	}
	
}

