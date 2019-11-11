<?php

namespace Trapdirector;

use Trapdirector\Logging;
use Trapdirector\Database;
use PDO;
use Exception;

class Mib
{
    
    protected $logging; //< logging class
    protected $trapsDB; //< Database class
    
    public $snmptranslate;
    public $snmptranslate_dirs;
    
    private $dbOidAll; //< All oid in database;
    private $dbOidIndex; //< Index of oid in dbOidAll
    private $objectsAll; //< output lines of snmptranslate list
    private $trapObjectsIndex; //< array of traps objects (as OID)
    
    /**
     * Setup Mib Class
     * @param Logging $logClass : where to log
     * @param Database $dbClass : Database
     */
    function __construct($logClass,$dbClass,$snmptrans,$snmptransdir)
    {
        $this->logging=$logClass;
        $this->trapsDB=$dbClass;
        $this->snmptranslate=$snmptrans;
        $this->snmptranslate_dirs=$snmptransdir;

    }
    
    
    /**
     * Update or add an OID to database uses $this->dbOidIndex for mem cache
     * @param string $oid
     * @param string $mib
     * @param string $name
     * @param string $type
     * @param string $textConv
     * @param string $dispHint
     * @param string $syntax
     * @param string $type_enum
     * @param string $description
     * @return number : 0=unchanged, 1 = changed, 2=created
     */
    public function update_oid($oid,$mib,$name,$type,$textConv,$dispHint,$syntax,$type_enum,$description=NULL)
    {
        $db_conn=$this->trapsDB->db_connect_trap();
        $description=$db_conn->quote($description);
        if (isset($this->dbOidIndex[$oid]))
        {
            if ($this->dbOidIndex[$oid]['key'] == -1)
            { // newly created.
                return 0;
            }
            if ( $name != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['name'] ||
                $mib != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['mib'] ||
                $type != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['type'] //||
                //$textConv != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['textual_convention'] //||
                //$dispHint != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['display_hint'] ||
                //$syntax != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['syntax'] ||
                //$type_enum != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['type_enum'] ||
                //$description != $this->dbOidAll[$this->dbOidIndex[$oid]['key']]['description']
                )
            { // Do update
                $sql='UPDATE '.$this->trapsDB->dbPrefix.'mib_cache SET '.
                    'name = :name , type = :type , mib = :mib , textual_convention = :tc , display_hint = :display_hint'.
                    ', syntax = :syntax, type_enum = :type_enum, description = :description '.
                    ' WHERE id= :id';
                $sqlQuery=$db_conn->prepare($sql);
                
                $sqlParam=array(
                    ':name' => $name,
                    ':type' => $type,
                    ':mib' => $mib,
                    ':tc' =>  ($textConv==null)?'null':$textConv ,
                    ':display_hint' => ($dispHint==null)?'null':$dispHint ,
                    ':syntax' => ($syntax==null)?'null':$syntax,
                    ':type_enum' => ($type_enum==null)?'null':$type_enum,
                    ':description' => ($description==null)?'null':$description,
                    ':id' => $this->dbOidAll[$this->dbOidIndex[$oid]['id']]
                );
                
                if ($sqlQuery->execute($sqlParam) === false) {
                    $this->logging->log('Error in query : ' . $sql,ERROR,'');
                }
                $this->logging->log('Trap updated : '.$name . ' / OID : '.$oid,DEBUG );
                return 1;
            }
            else
            {
                $this->logging->log('Trap unchanged : '.$name . ' / OID : '.$oid,DEBUG );
                return 0;
            }
        }
        // create new OID.
        
        // Insert data
        
        $sql='INSERT INTO '.$this->trapsDB->dbPrefix.'mib_cache '.
            '(oid, name, type , mib, textual_convention, display_hint '.
            ', syntax, type_enum , description ) ' .
            'values (:oid, :name , :type ,:mib ,:tc , :display_hint'.
            ', :syntax, :type_enum, :description )';
        
        if ($this->trapsDB->trapDBType == 'pgsql') $sql .= 'RETURNING id';
        
        $sqlQuery=$db_conn->prepare($sql);
        
        $sqlParam=array(
            ':oid' => $oid,
            ':name' => $name,
            ':type' => $type,
            ':mib' => $mib,
            ':tc' =>  ($textConv==null)?'null':$textConv ,
            ':display_hint' => ($dispHint==null)?'null':$dispHint ,
            ':syntax' => ($syntax==null)?'null':$syntax,
            ':type_enum' => ($type_enum==null)?'null':$type_enum,
            ':description' => ($description==null)?'null':$description
        );
        
        if ($sqlQuery->execute($sqlParam) === false) {
            $this->logging->log('Error in query : ' . $sql,1,'');
        }
        
        switch ($this->trapsDB->trapDBType)
        {
            case 'pgsql':
                // Get last id to insert oid/values in secondary table
                if (($inserted_id_ret=$sqlQuery->fetch(PDO::FETCH_ASSOC)) === false) {
                    $this->logging->log('Error getting id - pgsql - ',1,'');
                }
                if (! isset($inserted_id_ret['id'])) {
                    $this->logging->log('Error getting id - pgsql - empty.',1,'');
                }
                $this->dbOidIndex[$oid]['id']=$inserted_id_ret['id'];
                break;
            case 'mysql':
                // Get last id to insert oid/values in secondary table
                $sql='SELECT LAST_INSERT_ID();';
                if (($ret_code=$db_conn->query($sql)) === false) {
                    $this->logging->log('Erreur getting id - mysql - ',1,'');
                }
                
                $inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
                if ($inserted_id==false) throw new Exception("Weird SQL error : last_insert_id returned false : open issue");
                $this->dbOidIndex[$oid]['id']=$inserted_id;
                break;
            default:
                $this->logging->log('Error SQL type Unknown : '.$this->trapsDB->trapDBType,1,'');
        }
        
        // Set as newly created.
        $this->dbOidIndex[$oid]['key']=-1;
        return 2;
    }
    
    /**
     * create or update (with check_existing = true) objects of trap
     * @param string $trapOID : trap oid
     * @param string $trapmib : mib of trap
     * @param array $objects : array of objects name (without MIB)
     * @param bool $check_existing : check instead of create
     */
    public function trap_objects($trapOID,$trapmib,$objects,$check_existing)
    {
        $dbObjects=null; // cache of objects for trap in db
        $db_conn=$this->trapsDB->db_connect_trap();
        
        // Get id of trapmib.
        
        $trapId = $this->dbOidIndex[$trapOID]['id'];
        if ($check_existing === true)
        {
            // Get all objects
            $sql='SELECT * FROM '.$this->trapsDB->dbPrefix.'mib_cache_trap_object where trap_id='.$trapId.';';
            $this->logging->log('SQL query get all traps: '.$sql,DEBUG );
            if (($ret_code=$db_conn->query($sql)) === false) {
                $this->logging->log('No result in query : ' . $sql,1,'');
            }
            $dbObjectsRaw=$ret_code->fetchAll();
            
            foreach ($dbObjectsRaw as $val)
            {
                $dbObjects[$val['object_id']]=1;
            }
        }
        foreach ($objects as $object)
        {
            $match=$snmptrans=array();
            $retVal=0;
            $objOid=$objTc=$objDispHint=$objSyntax=$objDesc=$objEnum=NULL;
            $tmpdesc='';$indesc=false;
            
            $objMib=$trapmib;
            exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
                ' -On -Td '.$objMib.'::'.$object . ' 2>/dev/null',$snmptrans,$retVal);
            if ($retVal!=0)
            {
                // Maybe not trap mib, search with IR
                exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
                    ' -IR '.$object . ' 2>/dev/null',$snmptrans,$retVal);
                if ($retVal != 0 || !preg_match('/(.*)::(.*)/',$snmptrans[0],$match))
                { // Not found -> continue with warning
                    $this->logging->log('Error finding trap object : '.$trapmib.'::'.$object,2,'');
                    continue;
                }
                $objMib=$match[1];
                
                // Do the snmptranslate again.
                exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
                    ' -On -Td '.$objMib.'::'.$object,$snmptrans,$retVal);
                if ($retVal!=0) {
                    $this->logging->log('Error finding trap object : '.$objMib.'::'.$object,2,'');
                }
                
            }
            foreach ($snmptrans as $line)
            {
                if ($indesc===true)
                {
                    $line=preg_replace('/[\t ]+/',' ',$line);
                    if (preg_match('/(.*)"$/', $line,$match))
                    {
                        $objDesc = $tmpdesc . $match[1];
                        $indesc=false;
                    }
                    $tmpdesc.=$line;
                    continue;
                }
                if (preg_match('/^\.[0-9\.]+$/', $line))
                {
                    $objOid=$line;
                    continue;
                }
                if (preg_match('/^[\t ]+SYNTAX[\t ]+([^{]*) \{(.*)\}/',$line,$match))
                {
                    $objSyntax=$match[1];
                    $objEnum=$match[2];
                    continue;
                }
                if (preg_match('/^[\t ]+SYNTAX[\t ]+(.*)/',$line,$match))
                {
                    $objSyntax=$match[1];
                    continue;
                }
                if (preg_match('/^[\t ]+DISPLAY-HINT[\t ]+"(.*)"/',$line,$match))
                {
                    $objDispHint=$match[1];
                    continue;
                }
                if (preg_match('/^[\t ]+DESCRIPTION[\t ]+"(.*)"/',$line,$match))
                {
                    $objDesc=$match[1];
                    continue;
                }
                if (preg_match('/^[\t ]+DESCRIPTION[\t ]+"(.*)/',$line,$match))
                {
                    $tmpdesc=$match[1];
                    $indesc=true;
                    continue;
                }
                if (preg_match('/^[\t ]+-- TEXTUAL CONVENTION[\t ]+(.*)/',$line,$match))
                {
                    $objTc=$match[1];
                    continue;
                }
            }
            $this->logging->log("Adding trap $object : $objOid / $objSyntax / $objEnum / $objDispHint / $objTc",DEBUG );
            //echo "$object : $objOid / $objSyntax / $objEnum / $objDispHint / $objTc / $objDesc\n";
            // Update
            $this->update_oid($objOid, $objMib, $object, '3', $objTc, $objDispHint, $objSyntax, $objEnum,$objDesc);
            
            if (isset($dbObjects[$this->dbOidIndex[$objOid]['id']]))
            {   // if link exists, continue
                $dbObjects[$this->dbOidIndex[$objOid]['id']]=2;
                continue;
            }
            if ($check_existing === true)
            {
                // TODO : check link trap - objects exists, mark them.
            }
            // Associate in object table
            $sql='INSERT INTO '.$this->trapsDB->dbPrefix.'mib_cache_trap_object (trap_id,object_id) '.
                'values (:trap_id, :object_id)';
            $sqlQuery=$db_conn->prepare($sql);
            $sqlParam=array(
                ':trap_id' => $trapId,
                ':object_id' => $this->dbOidIndex[$objOid]['id'],
            );
            
            if ($sqlQuery->execute($sqlParam) === false) {
                $this->logging->log('Error adding trap object : ' . $sql . ' / ' . $trapId . '/'. $this->dbOidIndex[$objOid]['id'] ,1,'');
            }
        }
        if ($check_existing === true)
        {
            // TODO : remove link trap - objects that wasn't marked.
        }
        
    }
    
    /**
     * Cache mib in database
     * @param boolean $display_progress : Display progress on standard output
     * @param boolean $check_change : Force check of trap params & objects
     * @param boolean $onlyTraps : only cache traps and objects (true) or all (false)
     * @param string $startOID : only cache under startOID (NOT IMPLEMENTED)
     */
    public function update_mib_database($display_progress=false,$check_change=false,$onlyTraps=true,$startOID='.1')
    {
        // Timing
        $timeTaken = microtime(true);
        $retVal=0;
        // Get all mib objects from all mibs
        $snmpCommand=$this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.' -On -Tto 2>/dev/null';
        $this->logging->log('Getting all traps : '.$snmpCommand,DEBUG );
        unset($this->objectsAll);
        exec($snmpCommand,$this->objectsAll,$retVal);
        if ($retVal!=0)
        {
            $this->logging->log('error executing snmptranslate',ERROR,'');
        }
        
        // Get all mibs from databse to have a memory index
        
        $db_conn=$this->trapsDB->db_connect_trap();
        
        $sql='SELECT * from '.$this->trapsDB->dbPrefix.'mib_cache;';
        $this->logging->log('SQL query : '.$sql,DEBUG );
        if (($ret_code=$db_conn->query($sql)) === false) {
            $this->logging->log('No result in query : ' . $sql,ERROR,'');
        }
        $this->dbOidAll=$ret_code->fetchAll();
        $this->dbOidIndex=array();
        // Create the index for db;
        foreach($this->dbOidAll as $key=>$val)
        {
            $this->dbOidIndex[$val['oid']]['key']=$key;
            $this->dbOidIndex[$val['oid']]['id']=$val['id'];
        }
        
        // Count elements to show progress
        $numElements=count($this->objectsAll);
        $this->logging->log('Total snmp objects returned by snmptranslate : '.$numElements,INFO );
        
        $step=$basestep=$numElements/10; // output display of % done
        $num_step=0;
        $timeFiveSec = microtime(true); // Used for display a '.' every <n> seconds
        
        // Create index for trap objects
        $this->trapObjectsIndex=array();
        
        // detailed timing (time_* vars)
        $time_parse1=$time_check1=$time_check2=$time_check3=$time_update=$time_objects=0;
        $time_parse1N=$time_check1N=$time_check2N=$time_check3N=$time_updateN=$time_objectsN=0;
        $time_num_traps=0;
        
        for ($curElement=0;$curElement < $numElements;$curElement++)
        {
            $time_1= microtime(true);
            if ((microtime(true)-$timeFiveSec) > 2 && $display_progress)
            { // echo a . every 2 sec
                echo '.';
                $timeFiveSec = microtime(true);
            }
            if ($curElement>$step)
            { // display progress
                $num_step++;
                $step+=$basestep;
                if ($display_progress)
                {
                    echo "\n" . ($num_step*10). '% : ';
                }
            }
            // Get oid or pass if not found
            if (!preg_match('/^\.[0-9\.]+$/',$this->objectsAll[$curElement]))
            {
                $time_parse1 += microtime(true) - $time_1;
                $time_parse1N ++;
                continue;
            }
            $oid=$this->objectsAll[$curElement];
            
            // get next line
            $curElement++;
            $match=$snmptrans=array();
            if (!preg_match('/ +([^\(]+)\(.+\) type=([0-9]+)( tc=([0-9]+))?( hint=(.+))?/',
                $this->objectsAll[$curElement],$match))
            {
                $time_check1 += microtime(true) - $time_1;
                $time_check1N++;
                continue;
            }
            
            $name=$match[1]; // Name
            $type=$match[2]; // type (21=trap, 0: may be trap, else : not trap
            
            if ($type==0) // object type=0 : check if v1 trap
            {
                // Check if next is suboid -> in that case is cannot be a trap
                if (preg_match("/^$oid/",$this->objectsAll[$curElement+1]))
                {
                    $time_check2 += microtime(true) - $time_1;
                    $time_check2N++;
                    continue;
                }
                unset($snmptrans);
                exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
                    ' -Td '.$oid . ' | grep OBJECTS ',$snmptrans,$retVal);
                if ($retVal!=0)
                {
                    $time_check2 += microtime(true) - $time_1;
                    $time_check2N++;
                    continue;
                }
                //echo "\n v1 trap found : $oid \n";
                // Force as trap.
                $type=21;
            }
            if ($onlyTraps===true && $type!=21) // if only traps and not a trap, continue
            {
                $time_check3 += microtime(true) - $time_1;
                $time_check3N++;
                continue;
            }
            
            $time_num_traps++;
            
            $this->logging->log('Found trap : '.$match[1] . ' / OID : '.$oid,INFO );
            if ($display_progress) echo '#'; // echo a # when trap found
            
            // get trap objects & source MIB
            unset($snmptrans);
            exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
                ' -Td '.$oid,$snmptrans,$retVal);
            if ($retVal!=0)
            {
                $this->logging->log('error executing snmptranslate',ERROR,'');
            }
            
            if (!preg_match('/^(.*)::/',$snmptrans[0],$match))
            {
                $this->logging->log('Error getting mib from trap '.$oid.' : ' . $snmptrans[0],1,'');
            }
            $trapMib=$match[1];
            
            $numLine=1;$trapDesc='';
            while (isset($snmptrans[$numLine]) && !preg_match('/^[\t ]+DESCRIPTION[\t ]+"(.*)/',$snmptrans[$numLine],$match)) $numLine++;
            if (isset($snmptrans[$numLine]))
            {
                $snmptrans[$numLine] = preg_replace('/^[\t ]+DESCRIPTION[\t ]+"/','',$snmptrans[$numLine]);
                
                while (isset($snmptrans[$numLine]) && !preg_match('/"/',$snmptrans[$numLine]))
                {
                    $trapDesc.=preg_replace('/[\t ]+/',' ',$snmptrans[$numLine]);
                    $numLine++;
                }
                if (isset($snmptrans[$numLine])) {
                    $trapDesc.=preg_replace('/".*/','',$snmptrans[$numLine]);
                    $trapDesc=preg_replace('/[\t ]+/',' ',$trapDesc);
                }
                
            }
            $update=$this->update_oid($oid,$trapMib,$name,$type,NULL,NULL,NULL,NULL,$trapDesc);
            $time_update += microtime(true) - $time_1; $time_1= microtime(true);
            
            if (($update==0) && ($check_change===false))
            { // Trapd didn't change & force check disabled
                $time_objects += microtime(true) - $time_1;
                if ($display_progress) echo "C";
                continue;
            }
            
            $synt=null;
            foreach ($snmptrans as $line)
            {
                if (preg_match('/OBJECTS.*\{([^\}]+)\}/',$line,$match))
                {
                    $synt=$match[1];
                }
            }
            if ($synt == null)
            {
                //echo "No objects for $trapOID\n";
                $time_objects += microtime(true) - $time_1;
                continue;
            }
            //echo "$synt \n";
            $trapObjects=array();
            while (preg_match('/ *([^ ,]+) *,* */',$synt,$match))
            {
                array_push($trapObjects,$match[1]);
                $synt=preg_replace('/'.$match[0].'/','',$synt);
            }
            
            $this->trap_objects($oid, $trapMib, $trapObjects, false);
            
            $time_objects += microtime(true) - $time_1;
            $time_objectsN++;
        }
        
        if ($display_progress)
        {
            echo "\nNumber of processed traps : $time_num_traps \n";
            echo "\nParsing : " . number_format($time_parse1+$time_check1,1) ." sec / " . ($time_parse1N+ $time_check1N)  . " occurences\n";
            echo "Detecting traps : " . number_format($time_check2+$time_check3,1) . " sec / " . ($time_check2N+$time_check3N) ." occurences\n";
            echo "Trap processing ($time_updateN): ".number_format($time_update,1)." sec , ";
            echo "Objects processing ($time_objectsN) : ".number_format($time_objects,1)." sec \n";
        }
        
        // Timing ends
        $timeTaken=microtime(true) - $timeTaken;
        if ($display_progress)
        {
            echo "Global time : ".round($timeTaken)." seconds\n";
        }
        
    }
    
    
}