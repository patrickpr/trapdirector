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
