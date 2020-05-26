<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Data\ResourceFactory;
use Icinga\Web\Url;
use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use RunTimeException;
use Exception;

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Module\Trapdirector\Forms\TrapsConfigForm;
use Icinga\Module\Trapdirector\Icinga2Api;
use Icinga\Module\Trapdirector\TrapsActions\DBException;

use Trapdirector\Trap;

class SettingsController extends TrapsController
{
  
  /**
   * get param dberror or idoerror
   * set errorDetected
   */
  private function get_param()
  {
      $dberrorMsg=$this->params->get('dberror');
      if ($dberrorMsg != '')
      {
          $this->view->errorDetected=$dberrorMsg;
      }
      $dberrorMsg=$this->params->get('idodberror');
      if ($dberrorMsg != '')
      {
          $this->view->errorDetected=$dberrorMsg;
      }
  }
  
  /**
   * Check empty configuration (and create one if needed)
   * Setup : configErrorDetected
   */
  private function check_empty_config()
  {
      $this->view->configErrorDetected == NULL; // Displayed error on various conifugration errors.
      if ($this->Config()->isEmpty() == true)
      {
          $this->Config()->setSection('config'); // Set base config section.
          try
          {
              $this->Config()->saveIni();
              $this->view->configErrorDetected='Configuration is empty : you can run install script with parameters (see Automatic installation below)';
              //$emptyConfig=1;
          }
          catch (Exception $e)
          {
              $this->view->configErrorDetected=$e->getMessage();
          }
          
      }
  }
  
  /**
   * Check database and IDO database
   * Setup : 
   * db_error : numerical error (trap db) 0=OK
   * message : message (trap db)
   * ido_db_error : numerical error 0=OK
   * ido_message : message
   */
  private function check_db()
  {
      $db_message=array( // index => ( message OK, message NOK, optional link if NOK )
          0	=>	array('Database configuration OK','',''),
          1	=>	array('Database set in config.ini','No database in config.ini',''),
          2	=>	array('Database exists in Icingaweb2 config','Database does not exist in Icingaweb2 : ',
              Url::fromPath('config/resource')),
          3	=>	array('Database credentials OK','Database does not exist/invalid credentials/no schema : ',
              Url::fromPath('trapdirector/settings/createschema')),
          4	=>	array('Schema is set','Schema is not set for ',
              Url::fromPath('trapdirector/settings/createschema')),
          5	=>	array('Schema is up to date','Schema is outdated :',
              Url::fromPath('trapdirector/settings/updateschema')),
      );
      
      try {
          $this->getUIDatabase()->testGetDb(); // Get DB in test mode
          $dberror=array(0,'');
      } catch (DBException $e) {
          $dberror = $e->getArray();
      }
      
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
      
      try {
          $this->getUIDatabase()->testGetIdoDb(); // Get DB in test mode
          $dberror=array(0,'');
      } catch (DBException $e) {
          $dberror = $e->getArray();
      }
      
      $this->view->ido_db_error=$dberror[0];
      $this->view->ido_message='IDO Database : ' . $dberror[1];
  }
  
  /**
   * Check API parameters
   * Setup : 
   * apimessage
   */
  private function check_api()
  {
      if ($this->Config()->get('config', 'icingaAPI_host') != '')
      {
          $apitest=new Icinga2Api($this->Config()->get('config', 'icingaAPI_host'),$this->Config()->get('config', 'icingaAPI_port'));
          $apitest->setCredentials($this->Config()->get('config', 'icingaAPI_user'), $this->Config()->get('config', 'icingaAPI_password'));
          try {
              list($this->view->apimessageError,$this->view->apimessage)=$apitest->test($this->getModuleConfig()::getapiUserPermissions());
              //$this->view->apimessageError=false;
          } catch (RuntimeException $e) {
              $this->view->apimessage='API config : ' . $e->getMessage();
              $this->view->apimessageError=true;
          }
      }
      else
      {
          $this->view->apimessage='API parameters not configured';
          $this->view->apimessageError=true;
      }
  }

  /**
   * Check icingaweb2 etc path
   * Setup : 
   * icingaEtcWarn : 0 if same than in trap_in.php, 1 if not
   * icingaweb2_etc : path 
   */
  private function check_icingaweb_path()
  {
      $this->view->icingaEtcWarn=0;
      $icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
      if ($icingaweb2_etc != "/etc/icingaweb2/" && $icingaweb2_etc != '')
      {
          $output=array();
          
          exec('cat ' . $this->module->getBaseDir() .'/bin/trap_in.php | grep "\$icingaweb2Etc=" ',$output);
          
          
          if (! isset($output[0]) || ! preg_match('#"'. $icingaweb2_etc .'"#',$output[0]))
          {
              $this->view->icingaEtcWarn=1;
              $this->view->icingaweb2_etc=$icingaweb2_etc;
          }
      }
      
  }
  
  /**
   * Get db list filtered by $allowed
   * @param array $allowed : array of allowed database
   * @return array : resource list
   */
  private function get_db_list($allowed)
  {
      $resources = array();
      foreach (ResourceFactory::getResourceConfigs() as $name => $resource) 
      {
          if ($resource->get('type') === 'db' && in_array($resource->get('db'), $allowed)) 
          {
              $resources[$name] = $name;
          }
      }
      return $resources;
  }
  
  /**
   * Get php binary with path or NULL if not found.
   * @return string|NULL
   */
  private function get_php_binary()
  {
      $phpBinary= array( PHP_BINARY, PHP_BINDIR . "/php", '/usr/bin/php');

      foreach ($phpBinary as $phpBin )
      {
          $output=array();
          $retCode=255;
          $input="154865134987aaaa";
          exec("$phpBin -r \"echo '$input';\"",$output,$retCode);
          
          if (! isset($output[0])) $output[0]="NO OUT";
          
          if ($retCode == 0 && preg_match("/$input/",$output[0]) == 1)
          {
              return $phpBin;
          }          
      }
      return NULL;
  }
  
  /**
   * Index of configuration
   * Params setup in $this->view :
   * errorDetected : if db or ido was detected by another page
   * configErrorDetected : error if empty configuration (or error wrting a new one).
   * db_error : numerical error (trap db) 0=OK
   * message : message (trap db)
   * ido_db_error : numerical error 0=OK
   * ido_message : message
   * apimessage
   * icingaEtcWarn : 0 if same than in trap_in.php, 1 if not
   * icingaweb2_etc : path 
   **/
  public function indexAction()
  {
      
    // CHeck permissions : display tests in any case, but no configuration.
	$this->view->configPermission=$this->checkModuleConfigPermission(1);
	// But check read permission
	$this->checkReadPermission();
	
	$this->view->tabs = $this->Module()->getConfigTabs()->activate('config');
	
	// Get message : sent on configuration problems detected by controllers
    $this->get_param();
    
    // Test if configuration exists, if not create for installer script
	$this->check_empty_config();

	// Test Database
    $this->check_db();
	
	//********* Test API
    $this->check_api();
	
	//*********** Test snmptrapd alive and options
	list ($this->view->snmptrapdError, $this->view->snmptrapdMessage) = $this->checkSnmpTrapd();

	// List DB in $ressources
	$resources = $this->get_db_list(array('mysql', 'pgsql')); 

	// Check standard Icingaweb2 path
	$this->check_icingaweb_path();

	$phpBinary = $this->get_php_binary();
	if ($phpBinary == null)
	{
	    $phpBinary = ' PHP BINARY NOT FOUND ';
	    
	}
	
	// Setup path for mini documentation
	$this->view->traps_in_config= $phpBinary . ' ' . $this->Module()->getBaseDir() . '/bin/trap_in.php';
	
	$this->view->installer= $this->Module()->getBaseDir() . '/bin/installer.sh '
	    . ' -c all ' 
	    . ' -d ' . $this->Module()->getBaseDir()
	    . ' -p ' . $phpBinary
	    . ' -a ' . exec('whoami')
	    . ' -w ' . Icinga::app()->getConfigDir();
	        
	// ******************* configuration form setup*******************
	$this->view->form = $form = new TrapsConfigForm();
	
	// set default paths;
	$this->view->form->setPaths($this->Module()->getBaseDir(),Icinga::app()->getConfigDir());
	
	// set default ido database
	$this->view->form->setDefaultIDODB($this->Config()->module('monitoring','backends')->get('icinga','resource'));
	
	// Make form handle request.
	$form->setIniConfig($this->Config())
		->setDBList($resources)
		->handleRequest();
        
  }

  public function createschemaAction()
  {
	$this->checkModuleConfigPermission();
	$this->getTabs()->add('create_schema',array(
		'active'	=> true,
		'label'		=> $this->translate('Create Schema'),
		'url'		=> Url::fromRequest()
	));
	// check if needed
	
	try 
	{
	    $this->getUIDatabase()->testGetDb(); // Get DB in test mode
	    printf('Schema already exists');
	    
	} 
	catch (DBException $e) 
	{

		printf('Creating schema : <br>');

		// Get module database name
		$dbName=$this->Config()->get('config', 'database');

        $dbResource = ResourceFactory::getResourceConfig($dbName);
        $dbType=$dbResource->get('db');
        switch ($dbType) {
          case 'mysql':
              $dbFileExt='sql';
              break;
          case 'pgsql':
              $dbFileExt='pgsql';
              break;
          default:
              printf("Database configuration error : Unsuported DB");
              return;
        } 

		printf('<pre>');
		require_once $this->Module()->getBaseDir() .'/bin/trap_class.php';
		
		$icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
		$debug_level=4;
		$Trap = new Trap($icingaweb2_etc);
		$Trap->setLogging($debug_level,'display');
		
		$prefix=$this->Config()->get('config', 'database_prefix');
		// schema file : <path>/SQL/schema_v<verion>.<dbtype>
		$schema=$this->Module()->getBaseDir() . 
		'/SQL/schema_v'. $this->getModuleConfig()->getDbCurVersion() . '.' . $dbFileExt;
		
		$Trap->trapsDB->create_schema($schema,$prefix);
		echo '</pre>';
	}
	echo '<br><br>Return to <a href="' . Url::fromPath('trapdirector/settings') .'" class="link-button icon-wrench"> settings page </a>';
  }

  public function updateschemaAction()
  {
	  $this->checkModuleConfigPermission();
      $this->getTabs()->add('get',array(
    		'active'	=> true,
    		'label'		=> $this->translate('Update Schema'),
    		'url'		=> Url::fromRequest()
    	));
	  // check if needed
	  $dberror=array();
      try
      {
          $this->getUIDatabase()->testGetDb(); // Get DB in test mode
          echo 'Schema already exists and is up to date<br>';
          return;
      }
      catch (DBException $e)
      {
          $dberror=$e->getArray(); 
      }
	  
	  echo 'Return to <a href="' . Url::fromPath('trapdirector/settings') .'" class="link-button icon-wrench"> settings page </a><br><br>';
	  
	  if ($dberror[0] != 5)
	  {
	      echo 'Database does not exists or is not setup correctly<br>';
	      return;
	  }
      // setup
	  require_once($this->Module()->getBaseDir() .'/bin/trap_class.php');
	  $icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
	  $debug_level=4;
	  $Trap = new Trap($icingaweb2_etc);
	  
	  
	  $prefix=$this->Config()->get('config', 'database_prefix');
	  $updateSchema=$this->Module()->getBaseDir() . '/SQL/';
	  
	  $target_version=$dberror[2];
	  
	  if ($this->params->get('msgok') == null) {
	      // Check for messages and display if any
              echo "Upgrade databse is going to start.<br>Don't forget to backup your database before update<br>";
	      $Trap->setLogging(2,'syslog');
	      $message = $Trap->trapsDB->update_schema($updateSchema,$target_version,$prefix,true);
	      if ($message != '')
	      {
	          echo 'Note :<br><pre>';
	          echo $message;
	          echo '</pre>';
	          echo '<br>';
	          echo '<a  class="link-button" style="font-size:large;font-weight:bold" href="' . Url::fromPath('trapdirector/settings/updateschema') .'?msgok=1">Click here to update</a>';
	          echo '<br>';
	          return;
	      }
	  }
	  
	  $Trap->setLogging($debug_level,'display');
	  
	  echo 'Updating schema to '. $target_version . ': <br>';
	  echo '<pre>';
	  	  
	  $Trap->trapsDB->update_schema($updateSchema,$target_version,$prefix);
	  echo '</pre>';
  }  

  private function checkSnmpTrapd()
  {
      $psOutput=array();
      // First check is someone is listening to port 162. As not root, we can't have pid... 
      $sspath = '/usr/sbin/ss';
      if(!is_executable("$sspath"))
      {
          return array(1,"Can not execute $sspath");
      }
      exec("$sspath -lun | grep ':162 '",$psOutput);
      if (count($psOutput) == 0)
      {
          return array(1,'Port UDP/162 is not open : is snmptrapd running?');
      }
      $psOutput=array();
      exec('ps --no-headers -o command -C snmptrapd',$psOutput);
      if (count($psOutput) == 0)
      {
          return array(1,"UDP/162 : OK, but no snmptrapd process (?)");
      }
      // Assume there is only one line... TODO : see if there is a better way to do this
      $line = preg_replace('/^.*snmptrapd /','',$psOutput[0]);
      if (!preg_match('/-n/',$line))
          return array(1,'snmptrapd has no -n option : '.$line);
      if (!preg_match('/-O[^ ]*n/',$line))
          return array(1,'snmptrapd has no -On option : '.$line);
      if (!preg_match('/-O[^ ]*e/',$line))
          return array(1,'snmptrapd has no -Oe option : '.$line);
      
      return array(0,'snmptrapd listening to UDP/162, options : '.$line);
  }
}
