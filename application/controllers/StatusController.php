<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Web\Controller;
use Icinga\Web\Url;

use Icinga\Module\Trapdirector\TrapsController;

class StatusController extends TrapsController
{
	public function indexAction()
	{
		$this->prepareTabs()->activate('status');
		
		/************  Trapdb ***********/
		try
		{
			$db = $this->getDb()->getConnection();
			$query = $db->select()->from(
				$this->getModuleConfig()->getTrapTableName(),
				array('COUNT(*)')
			);			
			$this->view->trap_count=$db->fetchOne($query);
			$query = $db->select()->from(
				$this->getModuleConfig()->getTrapDataTableName(),
				array('COUNT(*)')
			);			
			$this->view->trap_object_count=$db->fetchOne($query);
			$query = $db->select()->from(
				$this->getModuleConfig()->getTrapRuleName(),
				array('COUNT(*)')
			);			
			$this->view->rule_count=$db->fetchOne($query);			
 			
			$this->view->trap_days_delete=$this->getDBConfigValue('db_remove_days');
			
		}
		catch (Exception $e)
		{
			$this->displayExitError('status',$e->getMessage());
		}
		
	} 
  
	public function mibAction()
	{
		$this->prepareTabs()->activate('mib');
	}
	protected function prepareTabs()
	{
		return $this->getTabs()->add('status', array(
			'label' => $this->translate('Status'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status')
		)->add('mib', array(
			'label' => $this->translate('MIB Management'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status/mib')
		);
	} 
}
