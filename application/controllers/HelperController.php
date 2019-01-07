<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Web\Controller;
use Icinga\Web\Url;

use Icinga\Module\Trapdirector\TrapsController;

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
		}

		$retHosts=array('status'=>'OK','hosts' => array());

		$hosts=$this->getHostByIP($hostFilter);
		foreach ($hosts as $key=>$val)
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
		}
		
		$hostArray=$this->getHostByIP($host);
		if (count($hostArray) > 1)
		{	
			$this->_helper->json(array('status'=>'More than one host matches','hostid' => -1));
		}
		else if (count($hostArray) == 0)
		{
			$this->_helper->json(array('status'=>'No host matches','hostid' => -1));
		}
		$services=$this->getServicesByHostid($hostArray[0]->id);
		if (count($services) < 1)
		{
			$this->_helper->json(array('status'=>'No services found for host','hostid' => $hostArray[0]->id));
		}
		$retServices=array('status'=>'OK','services' => array(),'hostid' => $hostArray[0]->id);
		foreach ($services as $key=>$val)
		{
			array_push($retServices['services'],array($val->id , $val->name));
		}
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
		// TODO : get binary & dirs from config / database
		$snmptranslate='/usr/bin/snmptranslate ';
		$snmptranslate_dirs='/usr/share/icingaweb2/modules/trapdirector/mibs:/usr/share/snmp/mibs';
		
		$postData=$this->getRequest()->getPost();
		if (isset($postData['oid']))
		{
			$oid=$postData['oid'];
		}
		else
		{
			$this->_helper->json(array('status'=>'No oid'));
		}
		
		// Try to get oid name from snmptranslate
		$translate=exec($snmptranslate . ' -m ALL -M '.$snmptranslate_dirs.
		' '.$oid,$translate_output);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code==0 || $ret_code==FALSE) {
			$this->_helper->json(array('status'=>'Not found'));;
		} else {
			$this->_helper->json(
				array('status'=>'OK','mib' => $matches[1], 'name' => $matches[2])
			);
		}			
	}
	
}
