<?php

namespace Icinga\Module\TrapDirector\Controllers;

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Module\Trapdirector\Rest\RestAPI as RestAPI;


/** 
*/
class ApiController extends TrapsController
{
	
    private $json_options=JSON_PRETTY_PRINT;
    
    protected function send_json($object)
    {
        if (isset($object['Error']))
        {
            $this->send_json_error($object);
            return;
        }
        $this->_helper->layout()->disableLayout();
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        $this->getResponse()->sendHeaders();
        echo json_encode($object, $this->json_options) . "\n";
    }

    protected function send_json_error($object)
    {
        $this->_helper->layout()->disableLayout();
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        $this->getResponse()->sendHeaders();
        echo json_encode($object, $this->json_options) . "\n";
    }
    
	public function indexAction()
	{	
		$this->checkReadPermission();
		$apiObj= new RestAPI($this);

		$modif = $apiObj->last_modified();
		$this->send_json($modif);
		//print_r($modif);
		return;
	}
	
	public function dboptionActions()
	{
	    $this->checkReadPermission();
	    $apiObj= new RestAPI($this);
	    
	    $params = $this->getRequest()->getParams();
	    if (isset($params['name']))
	    {
	        
	    }
	    else 
	    {
	        
	    }
	}
	
}