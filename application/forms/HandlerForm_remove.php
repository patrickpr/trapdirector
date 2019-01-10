<?php

namespace Icinga\Module\Trapdirector\Forms;

use Icinga\Web\Form;
use Zend\Form\Element;

class HandlerForm extends Form
{
	$hostName; //!< source host name of trap 
	public function init()
	{
		$this->setName('handler_traps');
		$this->setSubmitLabel($this->translate('Save Changes'));
	}
	
    public function createElements(array $formData)
    {
		
		$this->addElement(
			'select',
			'config_database',
			array(
				'required'      => true,
				'label'         => $this->translate('Database'),
				'empty_option' => $this->translate('Please choose database'),
				'autosubmit'    => false,
				'multiOptions'  => $this->DBList,
				'value'			=> 'director',
			 )
		);
		$this->addElement(
			'text',
			'config_database_prefix',
			array(
				'required'      => true,
				'label'         => $this->translate('Trapdirector table prefix'),
				'value'			=> 'traps_',
			)
		);
		$this->addElement(
            'text',
            'config_icingaweb2_etc',
            array(
                    'required'      => true,
                    'label'             => $this->translate('icingaweb2 config dir'),
					'value'			=> '/etc/icingaweb2',
             )
        );
		$this->addElement(
            'text',
            'config_icingaweb2_etc',
            array(
                    'required'      => true,
                    'label'             => $this->translate('icingaweb2 config dir'),
					'value'			=> '/etc/icingaweb2',
             )
        );
			
;			
    }	

	public function save()
    {
		parent::save();
	}
}
