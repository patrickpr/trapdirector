<?php

namespace Trapdirector;

use Exception;


class RuleObject
{
 
   /** @var Trap $mainTrap Trap with data set */
    public $mainTrap;
    
    /** @var string $oid oid to select rules */
    protected $oid;
    /** @var string $ip source ip to select rules */
    protected $ip;
    
    /** @var array $trapExtensions */
    protected $trapExtensions;
    
    public $trapAction;
    
    public $trapToDB;
    
    public $hostName;
    public $serviceName;
    
    /**
     * Setup RuleObject Class
     * @param Logging $logClass : where to log
     * @param Database $dbClass : Database
     */
    function __construct(Trap $mainTrap, string $oid,string $ip)
    {
        $this->mainTrap = $mainTrap;
        $this->oid = $oid;
        $this->ip = $ip;
    }
    
    /** Get rules from rule database with ip and oid
     *	@param $ip string ipv4 or ipv6
     *	@param $oid string oid in numeric
     *	@return mixed|boolean : PDO object or false
     */
    protected function getMainRules()
    {
        $db_conn=$this->mainTrap->trapsDB->db_connect_trap();
        
        // fetch rules based on IP in rule and OID
        $sql='SELECT * from '.$this->mainTrap->dbPrefix.'rules WHERE trap_oid=\''.$this->oid.'\' ';
        $this->mainTrap->logging->log('SQL query : '.$sql,DEBUG );
        
        if (($ret_code=$db_conn->query($sql)) === false) {
            $this->mainTrap->logging->log('No result in query : ' . $sql,WARN,'');
            return false;
        }
        $rules_all=$ret_code->fetchAll();
        //echo "rule all :\n";print_r($rules_all);echo "\n";
        
        $rules_ret=array();
        $rule_ret_key=0;
        foreach ($rules_all as $key => $rule)
        {
            if ($rule['ip4']==$this->ip || $rule['ip6']==$this->ip)
            {
                $rules_ret[$rule_ret_key]=$rules_all[$key];
                //TODO : get host name by API (and check if correct in rule).
                $rule_ret_key++;
                continue;
            }
            // TODO : get hosts IP by API
            if (isset($rule['host_group_name']) && $rule['host_group_name']!=null)
            { // get ips of group members by oid
                $db_conn2=$this->mainTrap->trapsDB->db_connect_ido();
                $sql="SELECT m.host_object_id, a.address as ip4, a.address6 as ip6, b.name1 as host_name
						FROM icinga_objects as o
						LEFT JOIN icinga_hostgroups as h ON o.object_id=h.hostgroup_object_id
						LEFT JOIN icinga_hostgroup_members as m ON h.hostgroup_id=m.hostgroup_id
						LEFT JOIN icinga_hosts as a ON a.host_object_id = m.host_object_id
						LEFT JOIN icinga_objects as b ON b.object_id = a.host_object_id
						WHERE o.name1='".$rule['host_group_name']."';";
                if (($ret_code2=$db_conn2->query($sql)) === false) {
                    $this->mainTrap->logging->log('No result in query : ' . $sql,WARN,'');
                    continue;
                }
                $grouphosts=$ret_code2->fetchAll();
                //echo "rule grp :\n";print_r($grouphosts);echo "\n";
                foreach ( $grouphosts as $host)
                {
                    //echo $host['ip4']."\n";
                    if ($host['ip4']==$this->ip || $host['ip6']==$this->ip)
                    {
                        //echo "Rule added \n";
                        $rules_ret[$rule_ret_key]=$rules_all[$key];
                        $rules_ret[$rule_ret_key]['host_name']=$host['host_name'];
                        $rule_ret_key++;
                    }
                }
            }
        }
        //echo "rule rest :\n";print_r($rules_ret);echo "\n";exit(0);
        $this->mainTrap->logging->log('Found ' . count($rules_ret) . ' rules',DEBUG);
        return $rules_ret;
    }
    
    protected function getExtendedRules(int $ruleID)
    {
        $db_conn=$this->mainTrap->trapsDB->db_connect_trap();
        
        // fetch rules based on IP in rule and OID
        $sql='SELECT * from '. $this->mainTrap->dbPrefix. 'rules_list WHERE handler=\''. $ruleID .'\' ';
        $this->mainTrap->logging->log('SQL query : '.$sql,DEBUG );
        
        if (($ret_code=$db_conn->query($sql)) === false) {
            $this->mainTrap->logging->log('No extended rules found : ' . $sql,INFO,'');
            return array();
        }
        $rules_all=$ret_code->fetchAll();
       
        
        $this->mainTrap->logging->log('Found ' . count($rules_all) . ' extended rules',DEBUG);
        return $rules_all;
    }

    /** Add rule match to rule
     *	@param id int : rule id
     *   @param set int : value to set
     */
    public function add_rule_match($id, $set, $mainRule = TRUE)
    {
        $db_conn=$this->mainTrap->trapsDB->db_connect_trap();
        
        $table = ($mainRule === TRUE) ? 'rules' : 'rules_list';
        $sql="UPDATE ". $this->mainTrap->dbPrefix . $table . " SET num_match = '".$set."' WHERE (id = '".$id."');";
        if ($db_conn->query($sql) === false) {
            $this->mainTrap->logging->log('Error in update query : ' . $sql,WARN,'');
        }
    }
    
    /** Match rules for current trap and do action
     */
    public function applyRules()
    {
        $rules = $this->getMainRules();
        
        if ($rules===false || count($rules)==0)
        {
            $this->mainTrap->logging->log('No rules found for this trap',INFO );
            $this->mainTrap->trapData['status']='unknown';
            $this->mainTrap->trapToDb=true;
            return;
        }
        //print_r($rules);
        // Evaluate all rules in sequence
        $this->trapAction='';
        $this->trapToDb = TRUE;
        try
        {
            foreach ($rules as $rule)
            {
                $mainRule = new RuleElmt($this, $this->mainTrap);
                $mainRule->setupRule(true, $rule);
                $hostName=$rule['host_name'];
                $serviceName=$rule['service_name'];
                
                $extRules = $this->getExtendedRules($rule['id']);
                $extRuleArray=array();
                foreach ($extRules as $extRule)
                {
                    $curRule = new RuleElmt($this, $this->mainTrap);
                    $curRule->setupRule(FALSE, $extRule,$hostName,$serviceName);
                    $extRuleArray[$curRule->order] = $curRule;
                }
                
                $retCode = FALSE;
                for ( $index=1 ; $index <= count($extRuleArray) ; $index++ )
                {
                    if (! isset($extRuleArray[$index]) ) throw new \Exception('Index error in extended rules at rule : ' . $index);
                    $retCode = $extRuleArray[$index]->applyRule($this->trapAction, $this->trapToDb);
                    if ($retCode == TRUE) break;
                }
                
                if ($retCode == FALSE)
                { // no extended rules matched, eval default rule
                    $retCode = $mainRule->applyRule($this->trapAction, $this->trapToDb);
                }
    
                if ($retCode == TRUE)
                {
                    // Put name in source_name
                    if (!isset($this->trapData['source_name']))
                    {
                        $this->mainTrap->trapData['source_name'] = $mainRule->hostName;
                    }
                    else
                    {
                        if (!preg_match('/'.$mainRule->hostName.'/',$this->mainTrap->trapData['source_name']))
                        { // only add if not present
                            $this->mainTrap->trapData['source_name'].=','.$mainRule->hostName;
                        }
                    }
                    
                }
            }
        }
        catch (Exception $e)
        {
            $this->mainTrap->logging->log('Error in rule eval : '.$e->getMessage(),WARN,'');
            $this->trapAction.=' ERR : '.$e->getMessage();
            $this->mainTrap->trapData['status']='error';
        }
        
        $this->mainTrap->trapAction = ($this->trapAction == '' )? NULL : $this->trapAction;
        
        if ($this->mainTrap->trapData['status']=='error')
        {
            $this->mainTrap->trapToDb=true; // Always put errors in DB for the user can see it
        }
        else
        {
            $this->mainTrap->trapData['status']='done';
            $this->mainTrap->trapToDb = $this->trapToDB;
        }
    }
}