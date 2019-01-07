<?php

namespace Icinga\Module\TrapDirector\Controllers;

use Icinga\Web\Url;

use Exception;

use Icinga\Module\Trapdirector\TrapsController;


//use Icinga\Web\Form as Form;
/**

*/
class HandlerController extends TrapsController
{

	
	public function indexAction()
	{	
		$this->checkReadPermission();
		$this->getTabs()->add('get',array(
			'active'	=> true,
			'label'		=> $this->translate('Traps'),
			'url'		=> Url::fromRequest()
		));
		$db = $this->getDb();
		$this->getHandlerListTable()->setConnection($db);
		
		// Apply pagination limits TODO : error in here, limits are not well set
		$this->view->table=$this->applyPaginationLimits($this->getHandlerListTable(),$this->getModuleConfig()->itemListDisplay());		
		
		// Set Filter
		$this->view->filterEditor = $this->getHandlerListTable()->getFilterEditor($this->getRequest());		
	
		//$this->displayExitError('Handler/indexAction','Not implemented');
	}

	public function addAction()
	{
		$this->checkConfigPermission();
		// set up tab
		$this->getTabs()->add('get',array(
			'active'	=> true,
			'label'		=> $this->translate('Add handler'),
			'url'		=> Url::fromRequest()
		));
		// variables to send to view
		$this->view->hostlist=array(); // host list to input datalist
		$this->view->hostname=''; // Host name in input text
		$this->view->serviceGet=false; // Set to true to get list of service if only one host set
		$this->view->serviceSet=''; // Set service among services (must have serviceGet=true).
		$this->view->mainoid=''; // Trap OID
		$this->view->mib=''; // Trap mib
		$this->view->name=''; // Trap name
		$this->view->trapListForMIB=array(); // Trap list if mib exists for trap
		$this->view->objectList=array(); // objects sent with trap
		$this->view->display=''; // Initial display
		$this->view->revertOK=''; // revert OK in seconds
		$this->view->hostid=-1; // normally set by javascript serviceGet()
		$this->view->ruleid=-1;
		$this->view->setToUpdate=false;
		// Get Mib List from file
		$this->view->mibList=$this->getMIB()->getMIBList();
		
		//$this->view->trapvalues=false; // Set to true to display 'value' colum in objects
		
		//print_r($this->getMIB()->getObjectList('.1.3.6.1.2.1.17.0.1'));
		//print_r($test->traps);echo '<br>';
		//print_r($test->mibList);echo '<br>';
		if ($this->params->get('fromid') !== null) { 
			/********** Setup from existing trap ***************/ 
			$trapid=$this->params->get('fromid');
			// Get the full trap info
			$trapDetail=$this->getTrapDetail($trapid);

			$hostfilter=$trapDetail->source_ip;

			// Get host
			try
			{
				$hosts=$this->getHostByIP($hostfilter);
			}
			catch (Exception $e)
			{
				$this->displayExitError('Add handler : get host by IP/Name ',$e->getMessage());
			}
			
			
			// if one unique host found -> put id text input
			if (count($hosts)==1) {
				$this->view->hostname=$hosts[0]->name;
				$hostid=$hosts[0]->id;
				// Tell JS to get services when page is loaded
				$this->view->serviceGet=true;
				
			}
			else
			{
				foreach($hosts as $key->$val)
				{
					array_push($this->view->hostlist,$hosts[$key]->name);
				}
			}
			
			// set up trap oid and objects received by the trap
					
			$this->view->mainoid=$trapDetail->trap_oid;
			if ($trapDetail->trap_name_mib != null)
			{
				$this->view->mib=$trapDetail->trap_name_mib; 
				$this->view->name=$trapDetail->trap_name;
				$this->view->trapListForMIB=$this->getMIB()
					->getTrapList($trapDetail->trap_name_mib);
			}
			
			// Get all objects that can be in trap from MIB
			$allObjects=$this->getMIB()->getObjectList($trapDetail->trap_oid);
			// Get all objects in current Trap
			$currentTrapObjects=$this->getTrapobjects($trapid);
			foreach ($currentTrapObjects as $key => $val)
			{
				$currentObjectType='Unknown';
				if (isset($allObjects[$val->oid]['type']))
				{
					$currentObjectType=$allObjects[$val->oid]['type'];
				}
				$currentObject=array(
					$val->oid,
					$val->oid_name_mib,
					$val->oid_name,
					$val->value,
					$currentObjectType
				);
				array_push($this->view->objectList,$currentObject);
				// set currrent object to null in allObjects
				if (isset($allObjects[$val->oid]))
				{
					$allObjects[$val->oid]=null;
				}
			}
			if ($allObjects!=null) // in case trap doesn't have objects or is not resolved
			{
				foreach ($allObjects as $key => $val)
				{
					if ($val==null) { continue; }
					array_push($this->view->objectList, array(
						$key,
						$allObjects[$key]['mib'],
						$allObjects[$key]['name'],
						'No val. in trap',
						$allObjects[$key]['type']
					));
				}
			}
			
			// Add a simple display
			$this->view->display='Trap '.$trapDetail->trap_name.' received';
			return;
		}
		
		
		if ($this->params->get('ruleid') !== null) {
			/************* Rule editing ***************/
			// TODO : issue warning if host or service doesn't exists anymore
			$ruleid=$this->params->get('ruleid');
			$this->view->ruleid=$ruleid;
			$this->view->setToUpdate=true;
			
			$ruleDetail=$this->getRuleDetail($ruleid);
			$this->view->hostname=$ruleDetail->host_name;
			$this->view->revertOK=$ruleDetail->revert_ok;
			// Tell JS to get services when page is loaded
			$this->view->serviceGet=true;
			$this->view->serviceSet=$ruleDetail->service_name;

			$this->view->mainoid=$ruleDetail->trap_oid;
			$oidName=$this->getMIB()->translateOID($ruleDetail->trap_oid);
			if ($oidName != null)  // oid is found in mibs
			{
				$this->view->mib=$oidName['mib']; 
				$this->view->name=$oidName['name'];
				$this->view->trapListForMIB=$this->getMIB()
					->getTrapList($oidName['mib']);				
			}
			// Create object list with : display & rules references (OID) and complete with all objects if found
			$display=$ruleDetail->display;
			$rule=$ruleDetail->rule;
			$curObjectList=array();
			$index=1; // TODO must make sure the index is the same than in display
			// check in display & rule for : OID(<oid>)
			while ( preg_match('/OID\(([\.0-9]+)\)/',$display,$matches) ||
					preg_match('/OID\(([\.0-9]+)\)/',$rule,$matches))
			{
				$curOid=$matches[1];
				if (($object=$this->getMIB()->translateOID($curOid)) != null)
				{
					array_push($curObjectList, array(
						$curOid,
						$object['mib'],
						$object['name'],
						'',
						$object['type']
					));
				}
				else
				{
					array_push($curObjectList, array(
						$curOid,
						'not found',
						'not found',
						'',
						'not found'
					));					
				}
				$display=preg_replace('/OID\('.$curOid.'\)/','\$'.$index,$display);
				$rule=preg_replace('/OID\('.$curOid.'\)/','\$'.$index,$display);
				$index++;
			}
			// set display
			$this->view->display=$display;
			$this->view->rule=$rule;
			
			$this->view->objectList=$curObjectList;
			//TODO : $ruleDetail->action_match  /  $ruleDetail->action_nomatch
			
		}


	}

	/** Validate form and output message to user  
	*	@param in postdata 
	* 	@return status : OK / <Message>
	**/
	protected function handlerformAction()
	{
		$postData=$this->getRequest()->getPost();
		//print_r($postData ).'<br>';
	
		$params=array(
			// id (also db) => 	array('post' => post id, 'val' => default val, 'db' => send to table)
			'db_rule'		=>	array('post' => 'db_rule','db'=>false),
			'hostid'		=>	array('post' => 'hostid','db'=>false),
			'host_name'		=>	array('post' => 'hostname','db'=>true),
			'serviceid'		=>	array('post' => 'serviceid','db'=>false),
			'service_name'	=>	array('post' => 'serviceName','db'=>true),
			'trap_oid'		=>	array('post' => 'oid','db'=>true),
			'revert_ok'		=>	array('post' => 'revertOK','val' => 0,'db'=>true),
			'display'		=>	array('post' => 'display','val' => '','db'=>true),
			'rule'			=>	array('post' => 'rule','val' => '','db'=>true),			
			'action_match'	=>	array('post' => 'ruleMatch','val' => -1,'db'=>true),
			'action_nomatch'=>	array('post' => 'ruleNoMatch','val' => -1,'db'=>true),					
			'ip4'			=>	array('post' => null,'val' => null,'db'=>true),
			'ip6'			=>	array('post' => null,'val' => null,'db'=>true),
			
		);
		foreach ($params as $key => $value)
		{
			if ($params[$key]['post']==null) continue; // data not sent in post vars
			if (! isset($postData[$params[$key]['post']]))
			{
				// should not happen as the js checks data
				$this->_helper->json(array('status'=>'No ' . $key));
			}
			else
			{
				$data=$postData[$params[$key]['post']];
				if ($data!=null && $data !="")
				{
					$params[$key]['val']=$postData[$params[$key]['post']];
				}
			}
		}
		
		try 
		{
			$hostAddr=$this->getHostInfoByID($params['hostid']['val']);
			$params['ip4']['val']=$hostAddr->ip4;
			$params['ip6']['val']=$hostAddr->ip6;
			$checkHostName=$hostAddr->name;
			if ($params['host_name']['val'] != $checkHostName) 
			{
				$this->_helper->json(array('status'=>"Invalid host id : Please re enter host name"));
				return;
			}
			$serviceName=$this->getObjectNameByid($params['serviceid']['val']);
			if ($params['service_name']['val'] != $serviceName->name2)
			{
				$this->_helper->json(array('status'=>"Invalid service id : Please re enter service"));
				return;
			}
			$dbparams=array();
			foreach ($params as $key=>$val)
			{
				if ($val['db']==true )
				{
					$dbparams[$key] = $val['val'];
				}
			}
			//echo '<br>';	print_r($dbparams);echo '<br>';
			if ($params['db_rule']['val'] == -1 ) 
			{
				$ruleID=$this->addHandlerRule($dbparams);
			}
			else
			{
				// TODO : check if one row modified only ?
				$this->updateHandlerRule($dbparams,$params['db_rule']['val']);
				$ruleID=$params['db_rule']['val'];
			}
		}
		catch (Exception $e)
		{
			$this->_helper->json(array('status'=>$e->getMessage()));
		}
		$this->_helper->json(array('status'=>'OK', 'id' => $ruleID));
		
	}

	/** Get trap detail by trapid. // TODO : push this in TrapsController.php
	*	@param $trapid int id of trap in received table
	*	@return array (objects)
	*/
	protected function getTrapDetail($trapid) 
	{
		if (!preg_match('/^[0-9]+$/',$trapid)) { throw new Exception('Invalid id');  }
		$queryArray=$this->getModuleConfig()->trapDetailQuery();
		
		$db = $this->getDb()->getConnection();
		// ***************  Get main data
		// extract columns and titles;
		$elmts=NULL;
		foreach ($queryArray as $key => $val) {
			$elmts[$key]=$val[1];
		}
		try
		{		
			$query = $db->select()
				->from($this->getModuleConfig()->getTrapTableName(),$elmts)
				->where('id=?',$trapid);
			$trapDetail=$db->fetchRow($query);
			if ( $trapDetail == null || count($trapDetail)==0) throw new Exception('No traps was found with id = '.$trapid);
		}
		catch (Exception $e)
		{
			$this->displayExitError('Add handler : get trap detail',$e->getMessage());
		}

		return $trapDetail;

	}

	/** Get trap objects
	*	@param : trap id
	* 	@return array of objects
	*/
	protected function getTrapobjects($trapid)
	{	
		if (!preg_match('/^[0-9]+$/',$trapid)) { throw new Exception('Invalid id');  }
		$queryArrayData=$this->getModuleConfig()->trapDataDetailQuery();
		
		$db = $this->getDb()->getConnection();
		// ***************  Get object data
		// extract columns and titles;
		$data_elmts=NULL;
		foreach ($queryArrayData as $key => $val) {
			$data_elmts[$key]=$val[1];
		}
		try
		{		
			$query = $db->select()
				->from($this->moduleConfig->getTrapDataTableName(),$data_elmts)
				->where('trap_id=?',$trapid);
			$trapDetail=$db->fetchAll($query);
			// if ( $trapDetail == null ) throw new Exception('No traps was found with id = '.$trapid);
		}
		catch (Exception $e)
		{
			$this->displayExitError('Add handler : get trap data detail',$e->getMessage());
		}

		return $trapDetail;
	}

	/** Get rule detail by ruleid. // TODO : push this in TrapsController.php
	*	@param $ruleid int id of rule in rule table
	*	@return array (objects)
	*/
	protected function getRuleDetail($ruleid) 
	{
		if (!preg_match('/^[0-9]+$/',$ruleid)) { throw new Exception('Invalid id');  }
		$queryArray=$this->getModuleConfig()->ruleDetailQuery();
		
		$db = $this->getDb()->getConnection();
		// ***************  Get main data
		try
		{		
			$query = $db->select()
				->from($this->getModuleConfig()->getTrapRuleName(),$queryArray)
				->where('id=?',$ruleid);
			$ruleDetail=$db->fetchRow($query);
			if ( $ruleDetail == null || count($ruleDetail)==0) throw new Exception('No rule was found with id = '.$trapid);
		}
		catch (Exception $e)
		{
			$this->displayExitError('Update handler : get rule detail',$e->getMessage());
		}

		return $ruleDetail;

	}
	
}