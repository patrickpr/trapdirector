<?php

namespace Icinga\Module\TrapDirector\Controllers;

use Icinga\Module\Trapdirector\TrapsController;

/** 
*/
class IndexController extends TrapsController
{
	
	public function indexAction()
	{	
		$this->checkReadPermission();
		$this->redirectNow('trapdirector/received');

	}
	
}