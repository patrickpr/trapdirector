<?php

namespace Icinga\Module\Trapdirector;

use Icinga\Web\Controller;

use Icinga\Data\Paginatable;
use Icinga\Data\Db\DbConnection as IcingaDbConnection;


use Exception;

use Icinga\Module\Trapdirector\Config\TrapModuleConfig;
use Icinga\Module\Trapdirector\Tables\TrapTableList;
use Icinga\Module\Trapdirector\Tables\TrapTableHostList;
use Icinga\Module\Trapdirector\Tables\HandlerTableList;
use Icinga\Module\Trapdirector\Config\MIBLoader;

use Trap;

use Zend_Db_Expr;

class TrapsController extends Controller
{
	protected $moduleConfig;  	//< TrapModuleConfig instance
	protected $trapTableList; 	//< TrapTableList (by date)
	protected $trapTableHostList; 	//< TrapTableList (by hosts)
	protected $handlerTableList; 	//< HandlerTableList instance
	protected $trapDB;			//< Trap database
	protected $icingaDB;		//< Icinga IDO database;
	protected $MIBData; 		//< MIBLoader class
	protected $trapClass;		//< Trap class for bin/trap_class.php
		
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
	
	public function getTrapHostListTable()
	{
	    if ($this->trapTableHostList == Null) {
	        $this->trapTableHostList = new TrapTableHostList();
	        $this->trapTableHostList->setConfig($this->getModuleConfig());
	    }
	    return $this->trapTableHostList;
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
		
		if ($test == false) { $this->icingaDB = $dbconn; return $this->icingaDB;}
		
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
		    $this->redirectNow('trapdirector/settings?dberror=4');
		    return null;
		}
		
		return array(0,'');
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
			$this->MIBData=new MIBLoader(
				$this->Config()->get('config', 'snmptranslate'),
				$this->Config()->get('config', 'snmptranslate_dirs'),
				$this->getDb(),
				$this->getModuleConfig()
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

	/** Get host(s) by name in IDO database
	*	does not catch exceptions
	*	@return array of objects ( name, id (object_id), display_name)
	*/
	protected function getHostByName($name) 
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
				->where("a.name1 = '$name'");
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
		$num_hosts=count($hosts);
		foreach ($hosts as $key => $host)
		{ // For each host, get all services and add in common_services if not found or add counter
			$host_services=$this->getServicesByHostid($host->host_object_id);
			foreach($host_services as $service)
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
		foreach (array_keys($common_services) as $key)
		{
		    if ($common_services[$key]['num'] == $num_hosts)
			{
				array_push($result,array($key,$common_services[$key]['name']));
			}
		}
		
		return $result;
	}	

	/** Get services object id by host name / service name in IDO database
	*	does not catch exceptions
	*	@param $hostname string host name
	*	@param $name string service name
	*	@return int  service id
	*/
	protected function getServiceIDByName($hostname,$name) 
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
					->where('a.name2=\''.$name.'\' AND a.name1=\''.$hostname.'\' AND a.is_active = 1');
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
		// TODO Check for rule consistency
		$db = $this->getDb()->getConnection();
		// Add last modified date = creation date and username
		$params['created'] = new Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modified'] = new 	Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modifier'] = $this->Auth()->getUser()->getUsername();
		
		$query=$db->insert(
			$this->getModuleConfig()->getTrapRuleName(),
			$params
		);
		if($query==false)
		{
		  return null;
		}
		return $db->lastInsertId();
	}	

	/** Update handler rule in traps DB
	*	@param array(<db item>=><value>)
	*	@return array affected rows
	*/
	protected function updateHandlerRule($params,$ruleID)
	{
		// TODO Check for rule consistency
		$db = $this->getDb()->getConnection();
		// Add last modified date = creation date and username
		$params['modified'] = new 	Zend_Db_Expr('CURRENT_TIMESTAMP()');
		$params['modifier'] = $this->Auth()->getUser()->getUsername();
		
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

	/** Delete trap by ip & oid
	*	@param $ip string source IP (v4 or v6)
	*	@param $oid string oid
	*/
	protected function deleteTrap($ip,$oid)
	{
		
		$db = $this->getDb()->getConnection();
		$condition=null;
		if ($ip != null)
		{
			$condition="source_ip='$ip'";
		}
		if ($oid != null)
		{
			$condition=($condition==null)?'':$condition.' AND ';
			$condition.="trap_oid='$oid'";
		}
		if($condition ==null) return null;
		$query=$db->delete(
			$this->getModuleConfig()->getTrapTableName(),
			$condition
		);
		// TODO test ret code etc...
		return $query;
	}
   

	/** count trap by ip & oid
	*	@param $ip string source IP (v4 or v6)
	*	@param $oid string oid
	*/
	protected function countTrap($ip,$oid)
	{
		
		$db = $this->getDb()->getConnection();
		$condition=null;
		if ($ip != null)
		{
			$condition="source_ip='$ip'";
		}
		if ($oid != null)
		{
			$condition=($condition==null)?'':$condition.' AND ';
			$condition.="trap_oid='$oid'";
		}
		if($condition ==null) return 0;
		$query=$db->select()
			->from(
				$this->getModuleConfig()->getTrapTableName(),
				array('num'=>'count(*)'))
			->where($condition);
		$return_row=$db->fetchRow($query);
		return $return_row->num;
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

