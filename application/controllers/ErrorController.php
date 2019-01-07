<?php

namespace Icinga\Module\TrapDirector\Controllers;

use Icinga\Web\Url;

use Icinga\Module\Trapdirector\TrapsController;


class ErrorController extends TrapsController
{
	
	public function indexAction()
	{	  
		$this->getTabs()->add('get',array(
			'active'	=> true,
			'label'		=> $this->translate('Error'),
			'url'		=> Url::fromRequest()
		));
		$source=$this->params->get('source');
		$message=$this->params->get('message');
		
		$this->view->source=$source;
		$this->view->message=$message;

	}

}