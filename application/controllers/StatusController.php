<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Web\Url;
use Icinga\Web\Form;
use Zend_Form_Element_File as File;
use Zend_Form_Element_Submit as Submit;

use Exception;

use Icinga\Module\Trapdirector\TrapsController;
use Trapdirector\Trap;

class StatusController extends TrapsController
{
	public function indexAction()
	{
		$this->prepareTabs()->activate('status');
		
		/************  Trapdb ***********/
		try
		{
		    $dbConn = $this->getUIDatabase()->getDbConn();
		    if ($dbConn === null) throw new \ErrorException('uncatched db error');
			$query = $dbConn->select()->from(
				$this->getModuleConfig()->getTrapTableName(),
				array('COUNT(*)')
			);			
			$this->view->trap_count=$dbConn->fetchOne($query);
			$query = $dbConn->select()->from(
				$this->getModuleConfig()->getTrapDataTableName(),
				array('COUNT(*)')
			);			
			$this->view->trap_object_count=$dbConn->fetchOne($query);
			$query = $dbConn->select()->from(
				$this->getModuleConfig()->getTrapRuleName(),
				array('COUNT(*)')
			);			
			$this->view->rule_count=$dbConn->fetchOne($query);			
 			
			$this->view->trap_days_delete=$this->getUIDatabase()->getDBConfigValue('db_remove_days');
			
		}
		catch (Exception $e)
		{
			$this->displayExitError('status',$e->getMessage());
		}
		
		/*************** Log destination *******************/
		
		try
		{		
		    $this->view->currentLogDestination=$this->getUIDatabase()->getDBConfigValue('log_destination');
			$this->view->logDestinations=$this->getModuleConfig()->getLogDestinations();
			$this->view->currentLogFile=$this->getUIDatabase()->getDBConfigValue('log_file');
			$this->view->logLevels=$this->getModuleConfig()->getlogLevels();
			$this->view->currentLogLevel=$this->getUIDatabase()->getDBConfigValue('log_level');
		}
		catch (Exception $e)
		{
			$this->displayExitError('status',$e->getMessage());
		}
		
		/*************** SNMP configuration ****************/
		try
		{
		    $this->view->useSnmpTrapAddess= ( $this->getUIDatabase()->getDBConfigValue('use_SnmpTrapAddess') == 1 ) ? TRUE : FALSE;
		    $this->view->SnmpTrapAddressOID=$this->getUIDatabase()->getDBConfigValue('SnmpTrapAddess_oid');
		    $this->view->SnmpTrapAddressOIDDefault = ($this->view->SnmpTrapAddressOID == $this->getModuleConfig()->getDBConfigDefaults()['SnmpTrapAddess_oid'] ) ? TRUE : FALSE;
		    
		}
		catch (Exception $e)
		{
		    $this->displayExitError('status',$e->getMessage());
		}		
		
	} 
  
	/** Mib management
	*	Post param : action=update_mib_db : update mib database
	*	Post param : ation=check_update : check if mib update is finished
	*	File post : mibfile -> save mib file
	*/
	public function mibAction()
	{
		$this->prepareTabs()->activate('mib');
		
		$this->view->uploadStatus=null;
		// check if it is an ajax query
		if ($this->getRequest()->isPost())
		{
			$postData=$this->getRequest()->getPost();
			/** Check for action update or check update */
			if (isset($postData['action']))
			{
				$action=$postData['action'];
				if ($action == 'update_mib_db')
				{ // Do the update in background
					$return=exec('icingacli trapdirector mib update --pid /tmp/trapdirector_update.pid');
					if (preg_match('/OK/',$return))
					{
					    $this->_helper->json(array('status'=>'OK'));
					}
					// Error
					$this->_helper->json(array('status'=>$return));
				}
				if ($action == 'check_update')
				{
				    $file=@fopen('/tmp/trapdirector_update.pid','r');
				    if ($file == false)
				    {   // process is dead
				        $this->_helper->json(array('status'=>'tu quoque fili','err'=>'Cannot open file'));
				        return;
				    }
				    $pid=fgets($file);
				    $output=array();
				    $retVal=0;
					exec('ps '.$pid,$output,$retVal);
					if ($retVal == 0)
					{ // process is alive
						$this->_helper->json(array('status'=>'Alive and kicking'));
					}
					else
					{ // process is dead
					    $this->_helper->json(array('status'=>'tu quoque fili','err'=>'no proc'.$pid));
					}
				}
				$this->_helper->json(array('status'=>'ERR : no '.$action.' action possible' ));
			}
			/** Check for mib file UPLOAD */
			if (isset($_FILES['mibfile']))
			{
			    $name=filter_var($_FILES['mibfile']['name'],FILTER_SANITIZE_STRING);
				$DirConf=explode(':',$this->Config()->get('config', 'snmptranslate_dirs'));
				$destDir=array_shift($DirConf);
				if (!is_dir($destDir))
				{
				    $this->view->uploadStatus="ERROR : no $destDir directory, check module configuration";
				}
				else
				{
				    if (!is_writable($destDir))
				    {
				        $this->view->uploadStatus="ERROR : $destDir directory is not writable";
				    }
				    else
				    {
				        $destination = $destDir .'/'.$name; //$this->Module()->getBaseDir() . "/mibs/$name";
				        $sourceTmpNam=filter_var($_FILES['mibfile']['tmp_name'],FILTER_SANITIZE_STRING);
				        if (move_uploaded_file($sourceTmpNam,$destination)===false)
    				    {
    				        $this->view->uploadStatus="ERROR, file $destination not loaded. Check file and path name or selinux violations";
    				    }
    				    else
    				    {
    				        $this->view->uploadStatus="File $name uploaded in $destDir";
    				    }
				    }
				}

			}
			
		}
		
		// snmptranslate tests
		$snmptranslate = $this->Config()->get('config', 'snmptranslate');
		$this->view->snmptranslate_bin=$snmptranslate;
		$this->view->snmptranslate_state='warn';
		if (is_executable ( $snmptranslate ))
		{
			$translate=exec($snmptranslate . ' 1');
			if (preg_match('/iso/',$translate))
			{
			    $translate=exec($snmptranslate . ' 1.3.6.1.4');
			    if (preg_match('/private/',$translate))
			    {		    
    				$this->view->snmptranslate='works fine';
    				$this->view->snmptranslate_state='ok';
			    }
			    else
			    {
			        $this->view->snmptranslate='works fine but missing basic MIBs';
			    }
			}
			else
			{
				$this->view->snmptranslate='Can execute but no OID to name resolution';
			}
		}
		else
		{
			$this->view->snmptranslate='Cannot execute';
		}
	
		// mib database
		
		$this->view->mibDbCount=$this->getMIB()->countObjects();
		$this->view->mibDbCountTrap=$this->getMIB()->countObjects(null,21);
		
		// mib dirs
		$DirConf=$this->Config()->get('config', 'snmptranslate_dirs');
		$dirArray=explode(':',$DirConf);

		// Get base directories from net-snmp-config
		$output=$matches=array();
		$retVal=0;
		$sysDirs=exec('net-snmp-config --default-mibdirs',$output,$retVal);
		if ($retVal==0)
		{
			$dirArray=array_merge($dirArray,explode(':',$sysDirs));
		}
		else
		{
			$translateOut=exec($this->Config()->get('config', 'snmptranslate') . ' -Dinit_mib .1.3 2>&1 | grep MIBDIRS');
			if (preg_match('/MIBDIRS.*\'([^\']+)\'/',$translateOut,$matches))
			{
				$dirArray=array_merge($dirArray,explode(':',$matches[1]));
			}
			else
			{
				array_push($dirArray,'Install net-snmp-config to see system directories');
			}
		}
		
		$this->view->dirArray=$dirArray;
		
		$output=null;
		foreach (explode(':',$DirConf) as $mibdir)
		{
			exec('ls '.$mibdir.' | grep -v traplist.txt',$output);
		}
		//$i=0;$listFiles='';while (isset($output[$i])) $listFiles.=$output[$i++];
		//$this->view->fileList=explode(' ',$listFiles);
		$this->view->fileList=$output;
		
		// Zend form 
		$this->view->form= new UploadForm();
		//$this->view->form= new Form('upload-form');
		
		
	}

	/** UI options */
	public function uimgtAction()
	{
	    $this->prepareTabs()->activate('uimgt');
	    
	    $this->view->setError='';
	    $this->view->setOKMsg='';
	    
	    //max_rows=25&row_update=update
	    if ( $this->getRequest()->getParam('max_rows',NULL) !== NULL )
	    {
	        $maxRows = $this->getRequest()->getParam('max_rows');
	        if (!preg_match('/^[0-9]+$/', $maxRows) || $maxRows < 1)
	        {
	            $this->view->setError='Max rows must be a number';
	        }
	        else
	        {
	            $this->setitemListDisplay($maxRows);
	            $this->view->setOKMsg='Set max rows to ' . $maxRows;
	        }
	    }
	    
	    if ( $this->getRequest()->getParam('add_category',NULL) !== NULL )
	    {
	        $addCat = $this->getRequest()->getParam('add_category');
            $this->addHandlersCategory($addCat);
	    }
	    
	    if ( $this->getRequest()->getPost('type',NULL) !== NULL )
	    {
	        $type = $this->getRequest()->getPost('type',NULL);
	        $index = $this->getRequest()->getPost('index',NULL);
	        $newname = $this->getRequest()->getPost('newname',NULL);

	        if (!preg_match('/^[0-9]+$/', $index) || $index < 1)
	            $this->_helper->json(array('status'=>'Bad index'));
	        
	        switch ($type)
	        {
	            case 'delete':
	                $this->delHandlersCategory($index);
	                $this->_helper->json(array('status'=>'OK'));
	                return;
	                break;
	            case 'rename':
	                $this->renameHandlersCategory($index, $newname);
	                $this->_helper->json(array('status'=>'OK'));
	                return;
	                break;
	            default:
	                $this->_helper->json(array('status'=>'Unknwon command'));
	                return;
	                break;
	        }
	    }
	    
	    $this->view->maxRows = $this->itemListDisplay();
	    
	    $this->view->categories = $this->getHandlersCategory();
	    
	    
	    
	}
	
	/** Create services and templates
	 *  Create template for trap service
	 * 
	 */
	public function servicesAction()
	{
		$this->prepareTabs()->activate('services');
		
		/*if (!$this->isDirectorInstalled())
		{
			$this->displayExitError("Status -> Services","Director is not installed, template & services install are not available");
		}
		*/
		// Check if data was sent :
		$postData=$this->getRequest()->getPost();
		$this->view->templateForm_output='';
		if (isset($postData['template_name']) && isset($postData['template_revert_time']))
		{
			$template_create = 'icingacli director service create --json \'{ "check_command": "dummy", ';
			$template_create .= '"check_interval": "' .$postData['template_revert_time']. '", "check_timeout": "20", "disabled": false, "enable_active_checks": true, "enable_event_handler": true, "enable_notifications": true, "enable_passive_checks": true, "enable_perfdata": true, "max_check_attempts": "1", ';
			$template_create .= '"object_name": "'.$postData['template_name'].'", "object_type": "template", "retry_interval": "'.$postData['template_revert_time'].'"}\'';
			$output=array();
			$ret_code=0;
			exec($template_create,$output,$ret_code);
			if ($ret_code != 0)
			{
				$this->displayExitError("Status -> Services","Error creating template : ".$output[0].'<br>Command was : '.$template_create);
			}
			exec('icingacli director config deploy',$output,$ret_code);
			$this->view->templateForm_output='Template '.$postData['template_name']. ' created';
		}
		
		// template creation form
		$this->view->templateForm_URL=Url::fromRequest()->__toString();
		$this->view->templateForm_name="trapdirector_main_template";
		$this->view->templateForm_interval="3600";
	}
    
	/**
	 * Plugins display and activation
	 */
	public function pluginsAction()
	{
	    $this->prepareTabs()->activate('plugins');
	    
	    require_once($this->Module()->getBaseDir() .'/bin/trap_class.php');
	    $icingaweb2_etc=$this->Config()->get('config', 'icingaweb2_etc');
	    $Trap = new Trap($icingaweb2_etc,4);
	    
	    $this->view->pluginLoaded = htmlentities($Trap->pluginClass->registerAllPlugins(false));
	    
	    $enabledPlugins = $Trap->pluginClass->getEnabledPlugins();

	    $pluginList = $Trap->pluginClass->pluginList();
	    
	    // Plugin list and fill function name list
	    $functionList=array();
	    $this->view->pluginArray=array();
	    foreach ($pluginList as $plugin)
	    {
	        $pluginDetails=$Trap->pluginClass->pluginDetails($plugin);
	        $pluginDetails->enabled =  (in_array($plugin, $enabledPlugins)) ? true : false;
	        $pluginDetails->catchAllTraps = ($pluginDetails->catchAllTraps === true )? 'Yes' : 'No';
	        $pluginDetails->processTraps = ($pluginDetails->processTraps === true )? 'Yes' : 'No';
	        $pluginDetails->description = htmlentities($pluginDetails->description);
	        $pluginDetails->description = preg_replace('/\n/','<br>',$pluginDetails->description);
	        array_push($this->view->pluginArray, $pluginDetails);
	        // Get functions for function details
	        foreach ($pluginDetails->funcArray as $function)
	        {
	            array_push($functionList,$function);
	        }
	    }
	    
	    // Function list with details
	    $this->view->functionList=array();
	    foreach ($functionList as $function)
	    {
	        $functionDetail = $Trap->pluginClass->getFunctionDetails($function);
	        $functionDetail->params = htmlentities($functionDetail->params);
	        $functionDetail->description = htmlentities($functionDetail->description);
	        $functionDetail->description = preg_replace('/\n/','<br>',$functionDetail->description);
	        array_push($this->view->functionList, $functionDetail);
	    }

	}

	/**
	 * For testing functions
	 */
	public function debugAction()
	{
	    $this->view->answer='No answer';
	    
	    $postData=$this->getRequest()->getPost();
	    if (isset($postData['input1']))
	    {
	        $input1 = $postData['input1'];
	        $input2 = $postData['input2'];
	        $input3 = $postData['input3'];
	        
	        //$this->view->answer=$input1 . '/' . $input2  . '/' . $input3;
	        try {
	            $API = $this->getIdoConn();
	            //$hosts = $API->getHostByIP($input1);
	            $hosts = $API->getHostsIPByHostGroup($input1);
	            $this->view->answer = print_r($hosts,true);
	            
	        } catch (Exception $e)
	        {
	            $this->view->answer = "Exception : " . print_r($e->getMessage());
	        }
	        
	    }
	    
	}
	
	protected function prepareTabs()
	{
		return $this->getTabs()->add('status', array(
			'label' => $this->translate('Status'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status')
		)->add('mib', array(
			'label' => $this->translate('MIB Management'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status/mib')
	    )->add('uimgt', array(
	        'label' => $this->translate('UI Configuration'),
	        'url'   => $this->getModuleConfig()->urlPath() . '/status/uimgt')
        )->add('services', array(
			'label' => $this->translate('Services management'),
			'url'   => $this->getModuleConfig()->urlPath() . '/status/services')
	    )->add('plugins', array(
	        'label' => $this->translate('Plugins management'),
	        'url'   => $this->getModuleConfig()->urlPath() . '/status/plugins')
	    );
	} 
}

// TODO : see if useless 
class UploadForm extends Form
{ 
    public function __construct($options = null) 
    {
        parent::__construct($options);
        $this->addElements2();
    }

    public function addElements2()
    {
        // File Input
        $file = new File('mib-file');
        $file->setLabel('Mib upload');
             //->setAttrib('multiple', null);
        $this->addElement($file);
		$button = new Submit("upload",array('ignore'=>false));
		$this->addElement($button);//->setIgnore(false);
    }
}
