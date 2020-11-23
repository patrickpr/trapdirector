<?php

namespace Trapdirector;

use Exception;


class RuleElmt
{
    /** @var RuleObject $mainRule */
    private $mainRule;

    /** @var Trap $mainTrap Trap with data set */
    public $mainTrap;
    
    /** @var array $ruleDef */
    public $ruleDef;

    private $rule;
    public $display;
    public $perfdata;

    public $order;
    
    public $hostName;
    public $serviceName;
    
    private $actionMatch;
    private $actionNoMatch;
    
    public $numMatch;
    public $id;
    private $lastMatch;
    private $limit;
    
    private $isDefault;
    
    function __construct(RuleObject $mainRule, Trap $mainTrap)
    {
        $this->mainRule = $mainRule;
        $this->mainTrap = $mainTrap;
    }
    
    public function setupRule(bool $isDefault, array $ruleDef, string $defaultHostName = NULL, string $defaulServiceName = NULL)
    {
        $this->rule = $ruleDef['rule'];
        $this->display = $ruleDef['display'];
        $this->perfdata = $ruleDef['performance_data'];
        
        $this->isDefault = $isDefault;
        $this->numMatch = $ruleDef['num_match'];
        $this->id = $ruleDef['id'];
        $this->lastMatch = $ruleDef['last_matched'];
        $this->limit = $ruleDef['rule_limit'];
        
        if ($this->isDefault === TRUE)
        {
            $this->hostName = $ruleDef['host_name'];
            $this->serviceName = $ruleDef['service_name'];
            $this->actionMatch = $ruleDef['action_match'];
            $this->actionNoMatch = $ruleDef['action_nomatch'];
        }
        else
        {
            $this->order = $ruleDef['evaluation_order'];
            if ( isset($ruleDef['reassign_host']) && $ruleDef['reassign_host'] !== NULL)
            {
                $this->hostName = $ruleDef['reassign_host'];
                if ( isset($ruleDef['reassign_service']) && $ruleDef['reassign_service'] !== NULL)
                {
                    $this->serviceName = $ruleDef['reassign_service'];
                }
                else
                {
                    $this->serviceName = $defaulServiceName;
                }
            }
            else
            {
                $this->hostName = $defaultHostName;
                $this->serviceName = $defaulServiceName;
            }
            $this->actionMatch = $ruleDef['action_match'];
        }
    }

    public function getDisplay()
    {
        $display = $this->display;
        $matches=array();
        if (preg_match('/(.*)\|(.*)/',$display,$matches) == 1)
        {
            $display=$matches[1];
        }
        return $this->replaceOID($display);
    }
 
    public function getPerfdata()
    {
        $perfdata = $this->perfdata;
        if ($perfdata == '')
        {
            $matches=array();
            if (preg_match('/(.*)\|(.*)/',$perfdata,$matches) == 1)
            {
                $perfdata=$matches[2];
            }
        }
        return $this->replaceOID($perfdata);
    }
    
    public function replaceOID($display)
    {
        $matches=array();
        while (preg_match('/_OID\(([0-9\.\*]+)\)/',$display,$matches) == 1)
        {
            $oid=$matches[1];
            $found=0;
            // Test and transform regexp
            $oidR = $this->mainTrap->ruleClass->regexp_eval($oid);
            
            foreach($this->mainTrap->trapDataExt as $val)
            {
                if (preg_match("/^$oidR$/",$val->oid) == 1)
                {
                    $val->value=preg_replace('/"/','',$val->value);
                    $rep=0;
                    $display=preg_replace('/_OID\('.$oid.'\)/',$val->value,$display,-1,$rep);
                    if ($rep==0)
                    {
                        $this->mainTrap->logging->log("Error in display/perfdata (code 1/replace)",WARN,'');
                        return $display;
                    }
                    $found=1;
                    break;
                }
            }
            if ($found==0)
            {
                $display=preg_replace('/_OID\('.$oid.'\)/','<not in trap>',$display,-1,$rep);
                if ($rep==0)
                {
                    $this->mainTrap->logging->log("Error in display/perfdata (code 1/oid not found)",WARN,'');
                    return $display;
                }
            }
        }
        return $display;
    }
    
    /** Match rules for current trap and do action
     */
    public function applyRule(string &$actionString, bool &$trapToDb)
    {
            
        $actionString = ($actionString==null)? '' : $actionString . ', ';

        $this->mainRule->logging->log('Rule to eval : '.$this->rule,INFO );
        $evalr=$this->mainTrap->ruleClass->eval_rule($this->rule, $this->mainTrap->trapDataExt) ;
        
        if ($evalr == true)
        {
            $this->mainRule->logging->log('action OK : '.$this->actionMatch,INFO );
            $this->mainTrap->add_rule_match($this->id,$this->numMatch+1, FALSE);
            
            if ($this->actionMatch >= 0)
            {
                if ($this->mainTrap->serviceCheckResult($this->hostName, $this->serviceName, $this->actionMatch,$this->getDisplay(),$this->getPerfdata()) == false)
                {
                    $actionString .='Error sending status : check cmd/API';
                }
                else
                {                      
                    $actionString .='Status '. $this->actionMatch .' to '. $this->hostName .'/'. $this->serviceName;
                }
            }
            $trapToDb= ( $this->actionMatch == -2 ) ? false : true;
            
        }
        if ($this->isDefault === FALSE) return; // No no match action if not default rule.
        
        $this->logging->log('action NOK : '.$this->actionNoMatch ,INFO );
                
        if ($this->actionNoMatch >= 0)
        {
            if ($this->mainTrap->serviceCheckResult($this->hostName, $this->serviceName, $this->actionNoMatch,$this->getDisplay(),$this->getPerfdata()) == false)
            {
                $actionString .='Error sending status : check cmd/API';
            }
            else
            {
                $actionString .='Status '. $this->actionMatch .' to '. $this->hostName .'/'. $this->serviceName;
            }
        }
        
        $this->trapToDb=( $this->actionNoMatch == -2 ) ? false : true;

    }

}