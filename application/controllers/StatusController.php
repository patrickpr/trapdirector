<?php

namespace Icinga\Module\Trapdirector\Controllers;

use Icinga\Web\Controller;
use Icinga\Web\Url;

class StatusController extends Controller
{
  public function getAction()
  {
	$this->getTabs()->add('get',array(
		'active'	=> true,
		'label'		=> $this->translate('Status'),
		'url'		=> Url::fromRequest()
	));    
  } 
}
