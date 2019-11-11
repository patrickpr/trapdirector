<?php

namespace Trapdirector;

use Trapdirector\Logging;
use PDO;

class Mib
{
    
    protected $logging; //< logging class
    protected $trapsDB; //< Database class
    
    
    /**
     * Setup Mib Class
     * @param Logging $logClass : where to log
     * @param Database $dbClass : Database
     */
    function __construct($logClass,$dbClass)
    {
        $this->logging=$logClass;
        $this->trapsDB=$dbClass;       
    }

    /** Create database schema
     *	@param $schema_file	string File to read schema from
     *	@param $table_prefix string to replace #PREFIX# in schema file by this
     */
    public function create_schema($schema_file,$table_prefix)
    {
        //Read data from snmptrapd from stdin
        $input_stream=fopen($schema_file, 'r');
        
        if ($input_stream=== false)
        {
            $this->logging->log("Error reading schema !",ERROR,'');
            return;
        }
        $newline='';
        $cur_table='';
        $cur_table_array=array();
        $db_conn=$this->trapsDB->db_connect_trap();
        
        while (($line=fgets($input_stream)) !== false)
        {
            $newline.=chop(preg_replace('/#PREFIX#/',$table_prefix,$line));
            if (preg_match('/; *$/', $newline))
            {
                $sql= $newline;
                if ($db_conn->query($sql) === false) {
                    $this->logging->log('Error create schema : '.$sql,ERROR,'');
                    return;
                }
                if (preg_match('/^ *CREATE TABLE ([^ ]+)/',$newline,$cur_table_array))
                {
                    $cur_table='table '.$cur_table_array[1];
                }
                else
                {
                    $cur_table='secret SQL stuff :-)';
                }
                $this->logging->log('Creating : ' . $cur_table,INFO );
                $newline='';
            }
        }
        
        $sql= $newline;
        if ($sql != '' )
        {
            if ($db_conn->query($sql) === false) {
                $this->logging->log('Error create schema : '.$sql,ERROR,'');
                return;
            }
        }
        $this->logging->log('Schema created',INFO);
    }
    
    /**
     * Update database schema from current (as set in db) to $target_version
     *     @param $prefix string file prefix of sql update File
     *     @param $target_version int target db version number
     *     @param $table_prefix string to replace #PREFIX# in schema file by this
     *     @param bool $getmsg : only get messages from version upgrades
     *     @return string : if $getmsg=true, return messages.
     */
    public function update_schema($prefix,$target_version,$table_prefix,$getmsg=false)
    {
        // Get current db number
        $db_conn=$this->trapsDB->db_connect_trap();
        $sql='SELECT id,value from '.$this->db_prefix.'db_config WHERE name=\'db_version\' ';
        $this->logging->log('SQL query : '.$sql,DEBUG );
        if (($ret_code=$db_conn->query($sql)) === false) {
            $this->logging->log('Cannot get db version. Query : ' . $sql,2,'');
            return;
        }
        $version=$ret_code->fetchAll();
        $cur_version=$version[0]['value'];
        $db_version_id=$version[0]['id'];
        
        if ($this->trapsDB->trapDBType == 'pgsql')
        {
            $prefix .= 'update_pgsql/schema_';
        }
        else
        {
            $prefix .= 'update_sql/schema_';
        }
        //echo "version all :\n";print_r($version);echo " \n $cur_ver \n";
        if ($getmsg === true)
        {
            $message='';
            $this->logging->log('getting message for upgrade',DEBUG );
            while($cur_version<$target_version)
            {
                $cur_version++;
                $updateFile=$prefix.'v'.($cur_version-1).'_v'.$cur_version.'.sql';
                $input_stream=fopen($updateFile, 'r');
                if ($input_stream=== false)
                {
                    $this->logging->log("Error reading update file ". $updateFile,2,'');
                    return;
                }
                do { $line=fgets($input_stream); }
                while ($line !== false && !preg_match('/#MESSAGE/',$line));
                if ($line === false)
                {
                    $this->logging->log("No message in file ". $updateFile,2,'');
                    return;
                }
                $message .= ($cur_version-1) . '->' . $cur_version. ' : ' . preg_replace('/#MESSAGE : /','',$line)."\n";
            }
            return $message;
        }
        while($cur_version<$target_version)
        { // tODO : execute pre & post scripts
            $cur_version++;
            $this->logging->log('Updating to version : ' .$cur_version ,INFO );
            $updateFile=$prefix.'v'.($cur_version-1).'_v'.$cur_version.'.sql';
            $input_stream=fopen($updateFile, 'r');
            if ($input_stream=== false)
            {
                $this->logging->log("Error reading update file ". $updateFile,2,'');
                return;
            }
            $newline='';
            $db_conn=$this->trapsDB->db_connect_trap();
            $db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            while (($line=fgets($input_stream)) !== false)
            {
                if (preg_match('/^#/', $line)) continue; // ignore comment lines
                $newline.=chop(preg_replace('/#PREFIX#/',$table_prefix,$line));
                if (preg_match('/; *$/', $newline))
                {
                    $sql_req=$db_conn->prepare($newline);
                    if ($sql_req->execute() === false) {
                        $this->logging->log('Error create schema : '.$newline,1,'');
                    }
                    $cur_table_array=array();
                    if (preg_match('/^ *([^ ]+) TABLE ([^ ]+)/',$newline,$cur_table_array))
                    {
                        $cur_table=$cur_table_array[1] . ' SQL table '.$cur_table_array[2];
                    }
                    else
                    {
                        $cur_table='secret SQL stuff :-)';
                        //$cur_table=$newline;
                    }
                    $this->logging->log('Doing : ' . $cur_table,INFO );
                    
                    $newline='';
                }
            }
            fclose($input_stream);
            
            //$sql= $newline;
            //if ($db_conn->query($sql) === false) {
            //    $this->logging->log('Error updating schema : '.$sql,1,'');
            //}
            
            $sql='UPDATE '.$this->db_prefix.'db_config SET value='.$cur_version.' WHERE ( id = '.$db_version_id.' )';
            $this->logging->log('SQL query : '.$sql,DEBUG );
            if ($db_conn->query($sql) === false) {
                $this->logging->log('Cannot update db version. Query : ' . $sql,2);
                return;
            }
            
            $this->logging->log('Schema updated to version : '.$cur_version ,INFO);
        }
    }
    
    
    
}