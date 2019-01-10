<?php

namespace Icinga\Module\Trapdirector\Forms;

use Icinga\Forms\ConfigForm;
use Zend\Form\Element;

class TrapsConfigForm extends ConfigForm
{
	private $DBList;
	public function init()
	{
		$this->setName('form_config_traps');
		$this->setSubmitLabel($this->translate('Save Changes'));
	}
	
	public function setDBList($resources)
	{
		$this->DBList=$resources;
		//print_r($this->DBList);
		return $this;
	}
	
    public function createElements(array $formData)
    {
		$this->addElement(
			'select',
			'config_database',
			array(
				'required'      => true,
				'label'         => $this->translate('Database'),
				'empty_option' => 'Please choose database',
				'autosubmit'    => false,
				'multiOptions'  => $this->DBList,
				'value'			=> 'traps',
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
			'select',
			'config_IDOdatabase',
			array(
				'required'      => true,
				'label'         => $this->translate('IDO Database'),
				'empty_option' => 'Please choose your database',
				'autosubmit'    => false,
				'multiOptions'  => $this->DBList,
				'value'			=> '',
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
            'config_snmptranslate',
            array(
                    'required'      => true,
                    'label'             => $this->translate('snmptranslate binary with path'),
					'value'			=> '/usr/bin/snmptranslate',
             )
        );
		$this->addElement(
            'text',
            'config_snmptranslate_dirs',
            array(
                    'required'      => true,
                    'label'         => $this->translate('Path for mibs'),
					'value'			=> '/usr/share/icingaweb2/modules/trapdirector/mibs:/usr/share/snmp/mibs',
             )
        );		
		$this->addElement(
            'text',
            'config_icingacmd',
            array(
                    'required'      => true,
                    'label'             => $this->translate('icingacmd with path'),
					'value'			=> '/var/run/icinga2/cmd/icinga2.cmd',
             )
        );	
    }	

	public function save()
    {
		parent::save();
	}
}
