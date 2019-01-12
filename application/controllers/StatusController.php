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
		
		/*************** Log destination *******************/
		
		try
		{		
			$this->view->currentLogDestination=$this->getDBConfigValue('log_destination');
			$this->view->logDestinations=$this->getModuleConfig()->getLogDestinations();
			$this->view->currentLogFile=$this->getDBConfigValue('log_file');
			$this->view->logLevels=$this->getModuleConfig()->getlogLevels();
			$this->view->currentLogLevel=$this->getDBConfigValue('log_level');
		}
		catch (Exception $e)
		{
			$this->displayExitError('status',$e->getMessage());
		}		
		
	} 
  
	public function mibAction()
	{
		$this->prepareTabs()->activate('mib');
		
		// snmptranslate tests
		$snmptranslate = $this->Config()->get('config', 'snmptranslate');
		$this->view->snmptranslate_bin=$snmptranslate;
		$this->view->snmptranslate_state='warn';
		if (is_executable ( $snmptranslate ))
		{
			$translate=exec($snmptranslate . ' 1');
			if (preg_match('/iso/',$translate))
			{
				$this->view->snmptranslate='works fine';
				$this->view->snmptranslate_state='ok';
			}
			else
			{
				$this->view->snmptranslate='can execute but no resolution';
			}
		}
		else
		{
			$this->view->snmptranslate='cannot execute';
		}
	
		// mib dirs
		$DirConf=$this->Config()->get('config', 'snmptranslate_dirs');
		$dirArray=array();
		$dirArray=explode(':',$DirConf);
		$this->view->dirArray=$dirArray;

		exec('ls '.$this->Module()->getBaseDir().'/mibs/ | grep -v traplist.txt',$output);
		//$i=0;$listFiles='';while (isset($output[$i])) $listFiles.=$output[$i++];
		//$this->view->fileList=explode(' ',$listFiles);
		$this->view->fileList=$output;
		
	}
	
	public function servicesActions()
	{
		$template_create = 'icingacli director service create --json \'{ "check_command": "dummy", "check_interval": "3600", "check_timeout": "20", "disabled": false, "enable_active_checks": true, "enable_event_handler": true, "enable_notifications": true, "enable_passive_checks": true, "enable_perfdata": true, "max_check_attempts": "1", "object_name": "trapdirector_main_template", "object_type": "template", "retry_interval": "3600"}\'';
		
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
		/*->add('services', array(
			'label' => $this->translate('Services management'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status/services')
		);*/
	} 
}
