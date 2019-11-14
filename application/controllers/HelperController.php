<?php

namespace Icinga\Module\Trapdirector\Controllers;

//use Icinga\Web\Controller;
//use Icinga\Web\Url;
use Exception;
use Icinga\Module\Trapdirector\TrapsController;
use Trap;

class HelperController extends TrapsController
{
	
	/** Get host list with filter (IP or name) : host=<filter>
	*	returns in JSON : status=>OK/NOK  hosts=>array of hosts
	*/
	public function gethostsAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['hostFilter']))
		{
			$hostFilter=$postData['hostFilter'];
		}
		else
		{
			$this->_helper->json(array('status'=>'KO'));
			return;
		}

		$retHosts=array('status'=>'OK','hosts' => array());

		$hosts=$this->getHostByIP($hostFilter);
		foreach ($hosts as $val)
		{
			array_push($retHosts['hosts'],$val->name);
		}
		
		$this->_helper->json($retHosts);
	}

	
	/** Get hostgroup list with filter (name) : hostgroup=<hostFilter>
	*	returns in JSON : status=>OK/NOK  hosts=>array of hosts
	*/
	public function gethostgroupsAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['hostFilter']))
		{
			$hostFilter=$postData['hostFilter'];
		}
		else
		{
			$this->_helper->json(array('status'=>'Error : no filter'));
			return;
		}

		$retHosts=array('status'=>'OK','hosts' => array());

		$hosts=$this->getHostGroupByName($hostFilter);
		foreach ($hosts as $val)
		{
			array_push($retHosts['hosts'],$val->name);
		}
		
		$this->_helper->json($retHosts);
	}

	
	/** Get service list by host name ( host=<host> )
	*	returns in JSON : 
	*		status=>OK/No services found/More than one host matches
	*		services=>array of services (name)
	*		hostid = host object id or -1 if not found.
	*/
	public function getservicesAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['host']))
		{
			$host=$postData['host'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No Hosts','hostid' => -1));
			return;
		}
		
		$hostArray=$this->getHostByName($host);
		if (count($hostArray) > 1)
		{	
			$this->_helper->json(array('status'=>'More than one host matches','hostid' => -1));
			return;
		}
		else if (count($hostArray) == 0)
		{
			$this->_helper->json(array('status'=>'No host matches','hostid' => -1));
			return;
		}
		$services=$this->getServicesByHostid($hostArray[0]->id);
		if (count($services) < 1)
		{
			$this->_helper->json(array('status'=>'No services found for host','hostid' => $hostArray[0]->id));
			return;
		}
		$retServices=array('status'=>'OK','services' => array(),'hostid' => $hostArray[0]->id);
		foreach ($services as $val)
		{
			array_push($retServices['services'],array($val->id , $val->name));
		}
		$this->_helper->json($retServices);
	}
	
	/** Get service list by host group ( name=<host> )
	*	returns in JSON : 
	*		status=>OK/No services found/More than one host matches
	*		services=>array of services (name)
	*		groupid = group object id or -1 if not found.
	*/
	public function gethostgroupservicesAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['host']))
		{
			$host=$postData['host'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No Hosts','hostid' => -1));
			return;
		}
		
		$hostArray=$this->getHostGroupByName($host);
		if (count($hostArray) > 1)
		{	
			$this->_helper->json(array('status'=>'More than one hostgroup matches','hostid' => -1));
			return;
		}
		else if (count($hostArray) == 0)
		{
			$this->_helper->json(array('status'=>'No hostgroup matches','hostid' => -1));
			return;
		}
		$services=$this->getServicesByHostGroupid($hostArray[0]->id);
		if (count($services) < 1)
		{
			$this->_helper->json(array('status'=>'No services found for hostgroup','hostid' => $hostArray[0]->id));
			return;
		}
		$retServices=array('status'=>'OK','services' => $services,'hostid' => $hostArray[0]->id);
		
		$this->_helper->json($retServices);
	}

	/** Get traps from mib  : entry : mib=<mib>
	*	returns in JSON : 
	*		status=>OK/No mib/Error getting mibs
	*		traps=>array of array( oid -> name)
	*/
	public function gettrapsAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['mib']))
		{
			$mib=$postData['mib'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No mib'));
			return;
		}
		try
		{
			$traplist=$this->getMIB()->getTrapList($mib);
			$retTraps=array('status'=>'OK','traps' => $traplist);
		} 
		catch (Exception $e) 
		{ 
			$retTraps=array('status' => 'Error getting mibs');
		}
		$this->_helper->json($retTraps);
	}	

	/** Get trap objects from mib  : entry : trap=<oid>
	*	returns in JSON : 
	*		status=>OK/no trap/not found
	*		objects=>array of array( oid -> name, oid->mib)
	*/
	public function gettrapobjectsAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['trap']))
		{
			$trap=$postData['trap'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No trap'));
			return;
		}
		try
		{
			$objectlist=$this->getMIB()->getObjectList($trap);
			$retObjects=array('status'=>'OK','objects' => $objectlist);
		} 
		catch (Exception $e) 
		{ 
			$retObjects=array('status' => 'not found');
		}
		$this->_helper->json($retObjects);
	}	
	
	/** Get list of all loaded mibs : entry : none
	*	return : array of strings.
	*/
	public function getmiblistAction()
	{
		try
		{
			$miblist=$this->getMIB()->getMIBList();
		} 
		catch (Exception $e) 
		{ 
			$miblist=array('Error getting mibs');
		}
		$this->_helper->json($miblist);
	}
	
	/** Get MIB::Name from OID : entry : oid
	*		status=>OK/No oid/not found
	*		mib=>string
	*		name=>string
	*/	
	public function translateoidAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['oid']))
		{
			$oid=$postData['oid'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No oid'));
			return;
		}
		
		// Try to get oid name from snmptranslate
		if (($object=$this->getMIB()->translateOID($oid)) == null)
		{
			$this->_helper->json(array('status'=>'Not found'));
			return;
		}
		else
		{
			$this->_helper->json(
				array('status'=>'OK',
					'mib' => $object['mib'], 
					'name' => $object['name'],
					'type' => $object['type'],
					'type_enum' => $object['type_enum'],
				    'description' => $object['description']
				)
			);
		}

	}

	
	/** Save or execute database purge of <n> days
	*	days=>int 
	*	action=>save/execute
	*	return : status=>OK/Message error
	*/
	public function dbmaintenanceAction()
	{
		
		$postData=$this->getRequest()->getPost();
		if (isset($postData['days']))
		{
			$days=$postData['days'];
			if (!preg_match('/^[0-9]+$/',$days))
			{
				$this->_helper->json(array('status'=>'invalid days : '.$days));
				return;
			}
		}
		else
		{
			$this->_helper->json(array('status'=>'No days'));
			return;
		}
		if (isset($postData['action']))
		{
			$action=$postData['action'];
			if ($action != 'save' && $action !='execute')
			{
				$this->_helper->json(array('status'=>'unknown action '.$action));
				return;
			}
		}
		else
		{
			$this->_helper->json(array('status'=>'No action'));
			return;
		}
		if ($action == 'save')
		{
			try
			{
				$this->setDBConfigValue('db_remove_days',$days);
			}
			catch (Exception $e)
			{
				$this->_helper->json(array('status'=>'Save error : '.$e->getMessage() ));
				return;
			}
			$this->_helper->json(array('status'=>'OK'));
			return;
		}
		if ($action == 'execute')
		{
			try
			{
				require_once($this->Module()->getBaseDir() .'/bin/trap_class.php');
				$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
				$debug_level=4;
				$Trap = new Trap($icingaweb2_etc);
				$Trap->setLogging($debug_level,'syslog');
				$Trap->eraseOldTraps($days);
			}
			catch (Exception $e)
			{
				$this->_helper->json(array('status'=>'execute error : '.$e->getMessage() ));
				return;
			}			
			$this->_helper->json(array('status'=>'OK'));
		}
			
	}	

	/** Save log output to db
	*	destination=>log destination 
	*	file=>file name
	*	level => int 
	*	return : status=>OK/Message error
	*/
	public function logdestinationAction()
	{
		$postData=$this->getRequest()->getPost();
		if (isset($postData['destination']))
		{
			$destination=$postData['destination'];
			$logDest=$this->getModuleConfig()->getLogDestinations();
			if (!isset($logDest[$destination]))
			{
				$this->_helper->json(array('status'=>'invalid destination : '.$destination));
				return;
			}
		}
		else
		{
			$this->_helper->json(array('status'=>'No destination'));
		}
		if (isset($postData['file']))
		{ 
			$file=$postData['file'];
			$fileHandler=@fopen($file,'w');
			if ($fileHandler == false)
			{   // File os note writabe / cannot create
			    $this->_helper->json(array('status'=>'File not writable :  '.$file));
			    return;
			}
		}
		else
		{
			if ($destination != 'file')
			{
				$file=null;
			}
			else
			{
				$this->_helper->json(array('status'=>'No file'));
				return;
			}
		}

		if (isset($postData['level']))
		{ 
			$level=$postData['level'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No level'));
			return;
		}
		
		try
		{
			$this->setDBConfigValue('log_destination',$destination);
			$this->setDBConfigValue('log_file',$file);
			$this->setDBConfigValue('log_level',$level);
		}
		catch (Exception $e)
		{
			$this->_helper->json(array('status'=>'Save error : '.$e->getMessage() ));
			return;
		}
		$this->_helper->json(array('status'=>'OK'));
		return;
			
	}	
	
	/** Test a rule evaluation
	 *	rule=>rule to evaluate
	 *	action=>'evaluate'
	 *	return : status=>OK/Message error & message : return of evaluation
	 */
	public function testruleAction()
	{
	    
	    $postData=$this->getRequest()->getPost();
	    if (isset($postData['rule']))
	    {
	        $rule=$postData['rule'];
	    }
	    else
	    {
	        $this->_helper->json(array('status'=>'No Rule'));
	    }
	    if (isset($postData['action']))
	    {
	        $action=$postData['action'];
	        if ($action != 'evaluate')
	        {
	            $this->_helper->json(array('status'=>'unknown action '.$action));
	            return;
	        }
	    }
	    else
	    {
	        $this->_helper->json(array('status'=>'No action'));
	        return;
	    }
	    if ($action == 'evaluate')
	    {
	        try
	        {
	            require_once($this->Module()->getBaseDir() .'/bin/trap_class.php');
	            $icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
	            $Trap = new Trap($icingaweb2_etc);
	            // Cleanup spaces before eval
	            $rule=$Trap->ruleClass->eval_cleanup($rule);
	            // Eval
	            $item=0;
	            $rule=$Trap->ruleClass->evaluation($rule,$item);
	        }
	        catch (Exception $e)
	        {
	            $this->_helper->json(array('status'=>'Evaluation error : '.$e->getMessage() ));
	            return;
	        }
	        $return=($rule==true)?'true':'false';
	        $this->_helper->json(array('status'=>'OK', 'message' => $return));
	    }
	    
	}	
	
}
