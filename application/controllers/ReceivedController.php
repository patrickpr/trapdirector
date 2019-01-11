<?php

namespace Icinga\Module\TrapDirector\Controllers;

use Icinga\Web\Url;

use Exception;

use Icinga\Module\Trapdirector\TrapsController;

/** 
*/
class ReceivedController extends TrapsController
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
		$this->getTrapListTable()->setConnection($db);
		
		// Apply pagination limits
		$this->view->table=$this->applyPaginationLimits($this->getTrapListTable(),$this->getModuleConfig()->itemListDisplay());		
		
		// Set Filter
		$postData=$this->getRequest()->getPost();
		$filter['q']=$this->params->get('q');//(isset($postData['q']))?$postData['q']:'';
	
		$this->view->filter=$filter;
		$this->view->table->updateFilter(Url::fromRequest()->__toString(),$filter);
		//$this->view->filterEditor = $this->getTrapListTable()->getFilterEditor($this->getRequest());

	}

	/** TODO : after SQL code from Handler controller put in trapsController,
	*	use the getTrapDetail / getTrapObject functions
	*/	
	public function trapdetailAction() 
	{
		
		$this->checkReadPermission();
		// set up tab
		$this->getTabs()->add('get',array(
			'active'	=> true,
			'label'		=> $this->translate('Detailed status'),
			'url'		=> Url::fromRequest()
		));
		// get id
		$trapid=$this->params->get('id');
		$this->view->trapid=$trapid;
		$queryArray=$this->getModuleConfig()->trapDetailQuery();
		
		$db = $this->getDb()->getConnection();
		
		// URL to add a handler
		$this->view->addHandlerUrl=Url::fromPath(
			$this->getModuleConfig()->urlPath() . '/handler/add',
			array('fromid' => $trapid));
		// ***************  Get main data
		// extract columns and titles;
		$elmts=NULL;
		foreach ($queryArray as $key => $val) {
			$elmts[$key]=$val[1];
		}
		
		// Do DB query for trap. 
		try
		{
			$query = $db->select()
				->from($this->moduleConfig->getTrapTableName(),$elmts)
				->where('id=?',$trapid);
			$trapDetail=$db->fetchRow($query);
			if ( $trapDetail == null) throw new Exception('No traps was found with id = '.$trapid);
		}
		catch (Exception $e)
		{
			$this->displayExitError('Trap detail',$e->getMessage());
		}

		// Store result in array (with Titles).
		foreach ($queryArray as $key => $val) {
			if ($key == 'timestamp') {
				$cval=strftime('%c',$trapDetail->$key);
			} else {
				$cval=$trapDetail->$key;
			}
			array_push($queryArray[$key],$cval);
		}
		$this->view->rowset = $queryArray;

		// **************   Check for additionnal data
		
		// extract columns and titles;
		$queryArrayData=$this->getModuleConfig()->trapDataDetailQuery();
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
		}
		catch (Exception $e)
		{
			$this->displayExitError('Trap detail',$e->getMessage());
		}
		// TODO : code this in a better & simpler way
		if ($trapDetail == null ) 
		{
			$this->view->data=false;
		}
		else
		{
			$this->view->data=true;
			// Store result in array.
			$trapval=array();
			foreach ($trapDetail as $key => $val) 
			{	
				$trapval[$key]=array();
				foreach ($queryArrayData as $vkey => $vval) 
				{
					array_push($trapval[$key],$val->$vkey);
				}
			}
			$this->view->data_val=$trapval;
			$this->view->data_title=$queryArrayData;
		}
	}
	
}