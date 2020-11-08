<?php

namespace Icinga\Module\Trapdirector\TrapsActions;

use Icinga\Web\Url;
use Icinga\Util\Json;
use Exception;

use Icinga\Module\Trapdirector\TrapsController;
use Icinga\Module\Trapdirector\Tables\HandlerTable;

trait RuleManagement
{
    // (serviceSet=null,source = null, destination = null, hostidSet = null, addOption = null, addOptionVal = null)
    
    /** @var array $params parameters sent by form */
    protected $postParams=array(
        // id (also db) => 	array('post' => post id, 'val' => default val, 'db' => send to table)
        'hostgroup'		    =>	array('post' => 'hostgroup',                         'db'=>false),
        'db_rule'		    =>	array('post' => 'db_rule',                           'db'=>false),
        'hostid'		    =>	array('post' => 'hostid',                            'db'=>false),
        'host_name'		    =>	array('post' => 'hostname',          'val' => null,  'db'=>true),
        'host_group_name'   =>	array('post' => null,                'val' => null,  'db'=>true),
        'serviceid'		    =>	array('post' => 'serviceid',                         'db'=>false),
        'service_name'	    =>	array('post' => 'serviceName',                       'db'=>true),
        'comment'           =>  array('post' => 'comment',           'val' => '',    'db'=>true),
        'rule_type'         =>  array('post' => 'category',          'val' => 0,     'db'=>true),
        'trap_oid'		    =>	array('post' => 'oid',                               'db'=>true),
        'revert_ok'		    =>	array('post' => 'revertOK',          'val' => 0,     'db'=>true),
        'display'		    =>	array('post' => 'display',           'val' => '',    'db'=>true),
        'rule'			    =>	array('post' => 'rule',              'val' => '',    'db'=>true),
        'action_match'	    =>	array('post' => 'ruleMatch',         'val' => -1,    'db'=>true),
        'action_nomatch'    =>	array('post' => 'ruleNoMatch',       'val' => -1,    'db'=>true),
        'ip4'			    =>	array('post' => null,                'val' => null,  'db'=>true),
        'ip6'			    =>	array('post' => null,                'val' => null,  'db'=>true),
        'action_form'	    =>	array('post' => 'action_form',       'val' => null,  'db'=>false),      
        'performance_data'	=>	array('post' => null  ,              'val' => '',    'db'=>true),
        'rule_limit'	    =>	array('post' => null,                'val' => '0',    'db'=>true), 
        'rulesData'         =>  array('post' => 'rulesData',         'val' => null,  'db'=>false)
    );
    
    protected $postRuleArray = array(
        'rule'			     =>	array('post' => 'rule',              'val' => '',    'db'=>true),
        'evaluation_order'   =>	array('post' => null,                'val' => '',    'db'=>true),
        'display'		     =>	array('post' => 'display',           'val' => '',    'db'=>true),
        'action_match'	     =>	array('post' => 'actionOK',          'val' => -1,    'db'=>true),
        'action_nomatch'    =>	array('post' => 'actionNOK',         'val' => -1,    'db'=>false),
        'reassign_service'	 =>	array('post' => null,                'val' => null,  'db'=>true),
        'reassign_host'		 =>	array('post' => null,                'val' => null,  'db'=>true),
        'reassign_hostgroup' =>	array('post' => null,                'val' => null,  'db'=>true),
        'performance_data'	 =>	array('post' => 'perfdata',          'val' => '',    'db'=>true),
        'hostGroup'		     =>	array('post' => 'hostGroup',         'val' => 0,     'db'=>false),
        'rule_limit'	            =>	array('post' => 'newlimit',          'val' => '0',    'db'=>true), 
    );
    
    /**
     * Setup default view values for add action
     */
    private function add_setup_vars()
    {
        // variables to send to view
        $this->view->hostlist=array(); // host list to input datalist
        $this->view->hostname=''; // Host name in input text
        $this->view->serviceGet=false; // Set to true to get list of service if only one host set
        $this->view->serviceSet=null; // Select service in services select (must have serviceGet=true).
        $this->view->mainoid=''; // Trap OID
        $this->view->mib=''; // Trap mib
        $this->view->name=''; // Trap name
        $this->view->trapListForMIB=array(); // Trap list if mib exists for trap
        $this->view->objectList=array(); // objects sent with trap
        $this->view->display=''; // Initial display
        $this->view->rule=''; // rule display
        $this->view->revertOK=''; // revert OK in seconds
        $this->view->hostid=-1; // normally set by javascript serviceGet()
        $this->view->ruleid=-1; // Rule id in DB for update & delete
        $this->view->setToUpdate=false; // set form as update form
        $this->view->setRuleMatch=-1; // set action on rule match (default nothing)
        $this->view->setRuleNoMatch=-1; // set action on rule no match (default nothing)
        
        $this->view->selectGroup=false; // Select by group if true
        $this->view->hostgroupid=-1; // host group id
        $this->view->serviceGroupGet=false; // Get list of service for group (set serviceSet to select one)
        
        $this->view->ruleList=array();
        
        $this->view->modifier=null;
        $this->view->modified=null;
    }

    /**
     * Get property of object or throw exception.
     * @param object $object
     * @param string $prop
     * @throws Exception if property does not exists
     * @return mixed Value of property
     */
    private function ruleListGetProp( $object, string $prop)
    {
        if ( !property_exists($object, $prop) )
        {
            throw new Exception('Missing ' . $prop . ' in rule');
        }
        return $object->{$prop};
    }
    
    
    private function processRuleListForm(array &$params)
    {
       
        $retArray = array();
        $ruleListObj = Json::decode($params['rulesData']['val']);
        
        if (! property_exists($ruleListObj, 0)) throw new Exception('No default rule');
        $defaultRule = $ruleListObj->{0};
        $params['rule']['val'] = $this->ruleListGetProp($defaultRule, 'rule');
        $params['display']['val'] = $this->ruleListGetProp($defaultRule, 'display');
        $params['performance_data']['val'] = $this->ruleListGetProp($defaultRule, 'perfdata');
        $params['action_match']['val'] = $this->ruleListGetProp($defaultRule, 'actionOK');
        $params['action_nomatch']['val'] = $this->ruleListGetProp($defaultRule, 'actionNOK');
        $params['rule_limit']['val'] = $this->ruleListGetProp($defaultRule, 'newlimit');  
        
        $index = 1;
        while ( property_exists($ruleListObj,$index) )
        {
            $newElmt = array();
            $curRule = $ruleListObj->{$index};
            
            foreach ($this->postRuleArray as $key => $val)
            {
                if ($val['db'] == false) continue;
                if ($val['post'] == null ) {  $newElmt[$key] = $val['val']; continue; }
                
                if (! property_exists ($curRule,$val['post'])) throw new Exception('Missing value ' . $val['post'] . ' for rule ' . $index);
                
                $newElmt[$key] = $curRule->{$val['post']};
            }
            $newElmt['evaluation_order'] = $index;
            
            if ($curRule->{'newServiceID'} != -1) // Service re assignment
            {
                $newElmt['reassign_service'] = $curRule->newService;
                if ($curRule->newhostID != -1) // Host ou hostgroup re assignment
                {
                    if ($curRule->hostGroup == 0) // Host
                    {
                        $newElmt['reassign_host'] = $curRule->newHost;
                    }
                    else
                    {
                        $newElmt['reassign_hostgroup'] = $curRule->newHost;
                    }
                }
            }
            //throw new Exception('rule : ' . print_r($newElmt, true));
            array_push($retArray, $newElmt);
            $index++;
        }
        $params['rulesData']['val'] = $retArray;
        return $retArray;
    }
    
    
}

