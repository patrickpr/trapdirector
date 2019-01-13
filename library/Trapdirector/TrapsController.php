<?php

namespace Icinga\Module\Trapdirector;

use Icinga\Web\Controller;
use Icinga\Web\Url;

use Icinga\Data\Db;
use Icinga\Data\Paginatable;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;

use Icinga\Application\Modules\Module;

use Exception;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Exception\ProgrammingError;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;
use Icinga\Module\Trapdirector\Tables\TrapTableList;
use Icinga\Module\Trapdirector\Tables\HandlerTableList;
use Icinga\Module\Trapdirector\Config\MIBLoader;

use Zend_Db_Expr;
use Zend_Db_Select;

class TrapsController extends Controller
{
	protected $moduleConfig;  	//< TrapModuleConfig instance
	protected $trapTableList; 	//< TrapTableList 
	protected $handlerTableList; 	//< HandlerTableList instance
	protected $trapDB;			//< Trap database
	protected $icingaDB;		//< Icinga IDO database;
	protected $MIBData; 		//< MIBLoader class
		
	/** Get instance of TrapModuleConfig class
	*	@return TrapModuleConfig
	*/
	public function getModuleConfig() {
		if ($this->moduleConfig == Null) {
			$db_prefix=$this->Config()->get('config', 'database_prefix');
			if ($db_prefix === null) 
			{
				$this->redirectNow('trapdirector/settings?message=No database prefix');
			}
			$this->moduleConfig = new TrapModuleConfig($db_prefix);
		}
		return $this->moduleConfig;
	}
	
	public function getTrapListTable() {
		if ($this->trapTableList == Null) {
			$this->trapTableList = new TrapTableList();
			$this->trapTableList->setConfig($this->getModuleConfig());
		}
		return $this->trapTableList;
	}
	
	public function getHandlerListTable() {
		if ($this->handlerTableList == Null) {
			$this->handlerTableList = new HandlerTableList();
			$this->handlerTableList->setConfig($this->getModuleConfig());
		}
		return $this->handlerTableList;
	}	
	
	/**	Get Database connexion
	*	@param $DBname string DB name in resource.ini_ge
	*	@param $test bool if set to true, returns error code and not database
	*	@param $test_version	bool if set to flase, does not test database version of trapDB
	*	@return IcingaDbConnection or int
	*/
	public function getDbByName($DBname,$test=false,$test_version=true)
	{
		try 
		{
			$dbconn = IcingaDbConnection::fromResourceName($DBname);
		} 
		catch (Exception $e)
		{
			if ($test) return array(2,$DBname);
			$this->redirectNow('trapdirector/settings?dberror=2');
			return null;
		}
		if ($test_version == true) {
			try 
			{
				$db=$dbconn->getConnection();
			}
			catch (Exception $e) 
			{
				if ($test) return array(3,$DBname,$e->getMessage());
				$this->redirectNow('trapdirector/settings?dberror=3');
				return null;
			}
			try
			{
				$query = $db->select()
					->from($this->getModuleConfig()->getDbConfigTableName(),'value')
					->where('name=\'db_version\'');
				$version=$db->fetchRow($query);
				if ( ($version == null) || ! property_exists($version,'value') )
				{
					if ($test) return array(4,$DBname);
					$this->redirectNow('trapdirector/settings?dberror=4');
					return null;
				}
				if ($version->value < $this->getModuleConfig()->getDbMinVersion()) 
				{
					if ($test) return array(5,$version->value,$this->getModuleConfig()->getDbMinVersion());
					$this->redirectNow('trapdirector/settings?dberror=5');
					return null;
				}
			}
			catch (Exception $e) 
			{
				if ($test) return array(3,$DBname,$e->getMessage());
				$this->redirectNow('trapdirector/settings?dberror=4');
				return null;
			}
		}
		if ($test) return array(0,'');
		return $dbconn;
	}

	public function getDb($test=false)
	{
		if ($this->trapDB != null && $test = false) return $this->trapDB;
		
		$dbresource=$this->Config()->get('config', 'database');
		
		if ( ! $dbresource )
		{	
			if ($test) return array(1,'');
			$this->redirectNow('trapdirector/settings?dberror=1');
			return null;
		}
		$retDB=$this->getDbByName($dbresource,$test,true);
		if ($test == true) return $retDB;
		$this->trapDB=$retDB;
		return $this->trapDB;
	}
	
	public function getIdoDb($test=false)
	{
		if ($this->icingaDB != null && $test = false) return $this->icingaDB;
		// TODO : get ido database by config or directly in icingaweb2 config
		$dbresource=$this->Config()->get('config', 'IDOdatabase');;

		$this->icingaDB=$this->getDbByName($dbresource,$test,false);
		if ($test == true) return 0;
		return $this->icingaDB;
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
	
	protected function checkModuleConfigPermission()
	{
        if (! $this->Auth()->hasPermission('trapdirector/module_config')) {
            $this->displayExitError('Permissions','No permission fo configure module');
        }		
	}

	/************************** MIB related **************************/
	
	/** Get MIBLoader class
	*	@return MIBLoader class
	*/
	protected function getMIB()
	{
		if ($this->MIBData == null)
		{
			//TODO : path in config module 
			$this->MIBData=new MIBLoader(
				$this->Module()->getBaseDir().'/mibs/traplist.txt',
				$this->Config()->get('config', 'snmptranslate'),
				$this->Config()->get('config', 'snmptranslate_dirs')
			);
		}
		return $this->MIBData;
	}	
	
	/**************************  Database queries *******************/
	
	/** Get host(s) by IP (v4 or v6) or by name in IDO database
	*	does not catch exceptions
	*	@return array of objects ( name, id (object_id), display_name)
	*/
	protected function getHostByIP($ip) 
	{
		// select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
		$db = $this->getIdoDb()->getConnection();
		// TODO : check for SQL injections
		$query=$db->select()
				->from(
					array('a' => 'icinga_objects'),
					array('name' => 'a.name1','id' => 'object_id'))
				->join(
					array('b' => 'icinga_hosts'),
					'b.host_object_id=a.object_id',
					array('display_name' => 'b.display_name'))
				->where("(b.address LIKE '%".$ip."%' OR b.address6 LIKE '%".$ip."%' OR a.name1 LIKE '%".$ip."%' OR b.display_name LIKE '%".$ip."%') and a.is_active = 1");
		return $db->fetchAll($query);
	}

	/** Get host groups by  name in IDO database
	*	does not catch exceptions
	*	@return array of objects ( name, id (object_id), display_name)
	*/
	protected function getHostGroupByName($ip) 
	{
		// select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
		$db = $this->getIdoDb()->getConnection();
		// TODO : check for SQL injections
		$query=$db->select()
				->from(
					array('a' => 'icinga_objects'),
					array('name' => 'a.name1','id' => 'object_id'))
				->join(
					array('b' => 'icinga_hostgroups'),
					'b.hostgroup_object_id=a.object_id',
					array('display_name' => 'b.alias'))
				->where("(a.name1 LIKE '%".$ip."%' OR b.alias LIKE '%".$ip."%') and a.is_active = 1");
		return $db->fetchAll($query);
	}

	
	/** Get host IP (v4 and v6) by name in IDO database
	*	does not catch exceptions
	*	@return array ( name, display_name, ip4, ip6)
	*/
	protected function getHostInfoByID($id) 
	{
		if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
		$db = $this->getIdoDb()->getConnection();
		$query=$db->select()
				->from(
					array('a' => 'icinga_objects'),
					array('name' => 'a.name1'))
				->join(
					array('b' => 'icinga_hosts'),
					'b.host_object_id=a.object_id',
					array('ip4' => 'b.address', 'ip6' => 'b.address6', 'display_name' => 'b.display_name'))
				->where("a.object_id = '".$id."'");
		return $db->fetchRow($query);
	}

	
	/** Get host by objectid  in IDO database
	*	does not catch exceptions
	*	@return array of objects ( id, name, display_name, ip, ip6,  )
	*/
	protected function getHostByObjectID($id) // TODO : duplicate of getHostInfoByID above
	{
		if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
		$db = $this->getIdoDb()->getConnection();
		$query=$db->select()
				->from(
					array('a' => 'icinga_objects'),
					array('name' => 'a.name1','id' => 'a.object_id'))
				->join(
					array('b' => 'icinga_hosts'),
					'b.host_object_id=a.object_id',
					array('display_name' => 'b.display_name' , 'ip' => 'b.address', 'ip6' => 'b.address6'))
				->where('a.object_id = ?',$id);
		return $db->fetchRow($query);
	}	
	
	/** Get services from object ( host_object_id) in IDO database
	*	does not catch exceptions
	*	@param $id	int object_id
	*	@return array display_name (of service), service_object_id
	*/
	protected function getServicesByHostid($id) 
	{
		// select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
		if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
		$db = $this->getIdoDb()->getConnection();
		if ($id != null)
		{
			$query=$db->select()
					->from(
						array('s' => 'icinga_services'),
						array('name' => 's.display_name','id' => 's.service_object_id'))
					->join(
						array('a' => 'icinga_objects'),
						's.service_object_id=a.object_id',
						array('is_active'=>'a.is_active','name2'=>'a.name2'))
					->where('s.host_object_id='.$id.' AND a.is_active = 1');
		}

		return $db->fetchAll($query);
	}	
	
	/** Get services from hostgroup object id ( hostgroup_object_id) in IDO database
	* 	gets all hosts in hostgroup and return common services
	*	TODO : problem id are different.... way to aggregate ?
	*	does not catch exceptions
	*	@param $id	int object_id
	*	@return array display_name (of service), service_object_id
	*/
	protected function getServicesByHostGroupid($id) 
	{		
		if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
		$db = $this->getIdoDb()->getConnection();
		$query=$db->select()
				->from(
					array('s' => 'icinga_hostgroup_members'),
					array('host_object_id' => 's.host_object_id'))
				->join(
					array('a' => 'icinga_hostgroups'),
					's.hostgroup_id=a.hostgroup_id',
					'hostgroup_object_id')
				->where('a.hostgroup_object_id='.$id);
		$hosts=$db->fetchAll($query);
		
		$common_services=array();
		foreach ($hosts as $key => $host)
		{ // For each host, get all services and add in common_services if not found
			$host_services=$this->getServicesByHostid($host->host_object_id);
			foreach($host_services as $skey=>$service)
			{
				if (isset($common_services[$service->name2]['num']))
				{
					$common_services[$service->name2]['num'] +=1;
				}
				else
				{
					$common_services[$service->name2]['num']=1;
					$common_services[$service->name2]['name']=$service->name;
				}
			}
		}
		$result=array();
		
		//print_r($common_services);
		foreach ($common_services as $key=>$val)
		{
			if ($common_services[$key]['num'] > 1)
			{
				array_push($result,array($key,$common_services[$key]['name']));
			}
		}
		
		return $result;
	}	

	/** Get services object id by name in IDO database
	*	does not catch exceptions
	*	@param $name service name
	*	@return int  service id
	*/
	protected function getServiceIDByName($name) 
	{
		$db = $this->getIdoDb()->getConnection();
		if ($name != null)
		{
			$query=$db->select()
					->from(
						array('s' => 'icinga_services'),
						array('name' => 's.display_name','id' => 's.service_object_id'))
					->join(
						array('a' => 'icinga_objects'),
						's.service_object_id=a.object_id',
						'is_active')
					->where('a.name2=\''.$name.'\' AND a.is_active = 1');
		}
		return $db->fetchAll($query);
	}
	
	/** Get object name from object_id  in IDO database
	*	does not catch exceptions
	*	@param $id	int object_id (default to null, used first if not null)
	*	@return array name1 (host) name2 (service)
	*/
	protected function getObjectNameByid($id) 
	{
		// select a.name1, b.display_name from icinga.icinga_objects AS a , icinga.icinga_hosts AS b WHERE (b.address = '192.168.56.101' OR b.address6= '123456') and b.host_object_id=a.object_id
		if (!preg_match('/^[0-9]+$/',$id)) { throw new Exception('Invalid id');  }
		$db = $this->getIdoDb()->getConnection();
		$query=$db->select()
				->from(
					array('a' => 'icinga_objects'),
					array('name1' => 'a.name1','name2' => 'a.name2'))
				->where('a.object_id='.$id.' AND a.is_active = 1');

		return $db->fetchRow($query);
	}		

	/** Add handler rule in traps DB
	*	@param array(<db item>=><value>)
	*	@return int inserted id
	*/
	protected function addHandlerRule($params)
	{
		// TODO Check for rule consistency and get user name
		$db = $this->getDb()->getConnection();
		// Add last modified date = creation date and username
		$params['created'] = new Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modified'] = new 	Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modifier'] ='me' ;
		
		$query=$db->insert(
			$this->getModuleConfig()->getTrapRuleName(),
			$params
		);
		return $db->lastInsertId();
	}	

	/** Update handler rule in traps DB
	*	@param array(<db item>=><value>)
	*	@return affected rows
	*/
	protected function updateHandlerRule($params,$ruleID)
	{
		// TODO Check for rule consistency and get user name
		$db = $this->getDb()->getConnection();
		// Add last modified date = creation date and username
		$params['modified'] = new 	Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modifier'] ='me' ;
		
		$numRows=$db->update(
			$this->getModuleConfig()->getTrapRuleName(),
			$params,
			'id='.$ruleID
		);
		return $numRows;
	}	
	/** Delete rule by id
	*	@param int rule id
	*/
	protected function deleteRule($ruleID)
	{
		if (!preg_match('/^[0-9]+$/',$ruleID)) { throw new Exception('Invalid id');  }
		$db = $this->getDb()->getConnection();
		
		$query=$db->delete(
			$this->getModuleConfig()->getTrapRuleName(),
			'id='.$ruleID
		);
		return $query;		
	}

	/** get configuration value
	*	@param configuration name in db
	*/
	protected function getDBConfigValue($element)
	{
	
		$db = $this->getDb()->getConnection();
		
		$query=$db->select()
			->from(
				$this->getModuleConfig()->getDbConfigTableName(),
				array('value'=>'value'))
			->where('name=?',$element);
		$return_row=$db->fetchRow($query);
		if ($return_row==null)  // value does not exists
		{
			$default=$this->getModuleConfig()->getDBConfigDefaults();
			if ( ! isset($default[$element])) return null; // no default and not value
			
			$this->addDBConfigValue($element,$default[$element]);
			return $default[$element];
		}
		if ($return_row->value == null) // value id empty
		{
			$default=$this->getModuleConfig()->getDBConfigDefaults();
			if ( ! isset($default[$element])) return null; // no default and not value
			$this->setDBConfigValue($element,$default[$element]);
			return $default[$element];			
		}
		return $return_row->value;		
	}

	/** add configuration value
	*	@param name value
	*/
	protected function addDBConfigValue($element,$value)
	{
	
		$db = $this->getDb()->getConnection();
		
		$query=$db->insert(
				$this->getModuleConfig()->getDbConfigTableName(),
				array(
					'name' => $element,
					'value'=>$value
				)
		);
		return $query;		
	}

	/** set configuration value
	*	@param name value
	*/
	protected function setDBConfigValue($element,$value)
	{
	
		$db = $this->getDb()->getConnection();
		$query=$db->update(
				$this->getModuleConfig()->getDbConfigTableName(),
				array('value'=>$value),
				'name=\''.$element.'\''
		);
		return $query;		
	}
	
	/** Check if director is installed
	*	@return bool true/false
	*/
	protected function isDirectorInstalled()
	{
		$modules=exec('icingacli module list',$output);
		foreach ($ouput as $line)
		{
			if (preg_match('/^director .*enabled/',$line))
			{
				return true;
			}
		}
		return false;
	}
	
}

