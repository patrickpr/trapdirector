<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Data\ResourceFactory;
use Icinga\Web\Url;

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Module\Trapdirector\Forms\TrapsConfigForm;
class SettingsController extends TrapsController
{
  public function indexAction()
  {
	//TODO : let error DB show without permissions but dont allow configuration form. 
	//TODO : also check ido database as set on this page.
	//TODO : $this->checkModuleConfigPermission();
	
	// Get message : sent on configuration problems detected by controllers
	$this->view->errorDetected=$this->params->get('dberror');
	
	// Test Database
	$db_message=array( // index => ( message OK, message NOK, optional link if NOK ) 
		0	=>	array('Database configuration OK','',''),
		1	=>	array('Database set in config.ini','No database in config.ini',''),
		2	=>	array('Database exists in Icingaweb2 config','Database does not exist in Icingaweb2 : ',
					Url::fromPath('config/resource')),
		3	=>	array('Database credentials OK','Database does not exist or invalid credentials : ','',
					Url::fromPath('config/resource')),
		4	=>	array('Schema is set','Schema is not set for ',
					Url::fromPath('trapdirector/settings/createschema')),					
		5	=>	array('Schema is up to date','Schema is outdated :',
					Url::fromPath('trapdirector/settings/updateschema')),
	);
		
	$dberror=$this->getDb(true); // Get DB in test mode

	$this->view->db_error=$dberror[0];
	switch ($dberror[0]) 
	{
		case 2:
		case 4:
			$db_message[$dberror[0]][1] .= $dberror[1];
			break;
		case 3:
			$db_message[$dberror[0]][1] .= $dberror[1] . ', Message : ' . $dberror[2];
			break;
		case 5:
			$db_message[$dberror[0]][1] .= ' version '. $dberror[1] . ', version needed : ' .$dberror[2];
			break;
		case 0:
		case 1:
			break;
		default:
			new ProgrammingError('Out of bond result from database test');
	}
	$this->view->message=$db_message;
	
	// List DB in $ressources
	$resources = array();
	$allowed = array('mysql', 'pgsql'); // TODO : check pgsql OK and maybe other DB
	foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
		if ($resource->get('type') === 'db' && in_array($resource->get('db'), $allowed)) {
			$resources[$name] = $name;
		}
	}

    $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');

	$this->view->form = $form = new TrapsConfigForm();

	//$form->setRedirectUrl('trapdirector/status');
	//echo $form->getRedirectUrl();
	
	// Setup path for mini documentation
	$this->view->traps_in_config= PHP_BINARY . ' ' . $this->Module()->getBaseDir() . '/bin/trap_in.php';
	// Make form handle request.
	$form->setIniConfig($this->Config())
		->setDBList($resources)		
		->handleRequest();
        
  }

  public function createschemaAction()
  {
	  $this->checkModuleConfigPermission();
	$this->getTabs()->add('get',array(
		'active'	=> true,
		'label'		=> $this->translate('Create Schema'),
		'url'		=> Url::fromRequest()
	));
	echo "<div> Not Implemented </div>";
  }

  public function updateschemaAction()
  {
	  $this->checkModuleConfigPermission();
	$this->getTabs()->add('get',array(
		'active'	=> true,
		'label'		=> $this->translate('Update Schema'),
		'url'		=> Url::fromRequest()
	));	  
	  echo "<div> Not Implemented </div>";
  }  
}
