<?php



trait MibDatabase
{
    /** @return \Trapdirector\Logging */
    abstract public function getLogging();
    
    /** @return \Trapdirector\Database */
    abstract public function getTrapsDB();

    
    /**
     * Update or add an OID to database uses $this->dbOidIndex for mem cache
     * and $this->oidDesc doe data
     * @return number : 0=unchanged, 1 = changed, 2=created
     */
    public function update_oid()
    {
        $db_conn=$this->getTrapsDB()->db_connect_trap();
        // Quote description.
        $this->oidDesc['description']=$db_conn->quote($this->oidDesc['description']);
        
        if (isset($this->dbOidIndex[$this->oidDesc['oid']]))
        { // oid exists in db, so update
            return $this->update_oid_update();
        }
        // create new OID.
        return $this->update_oid_create();
        
    }
    
    /**
     * Update object in DB with object in dbOidIndex if name/mib/type has changed.
     * @return number : 0=unchanged, 1 = changed, 2=created
     */
    private function update_oid_update()
    {
        
        $db_conn=$this->getTrapsDB()->db_connect_trap();
        
        if ($this->dbOidIndex[$this->oidDesc['oid']]['key'] == -1)
        { // newly created.
            return 0;
        }
        $oidIndex=$this->dbOidIndex[$this->oidDesc['oid']]['key']; // Get index in dbOidAll
        $dbOid=$this->dbOidAll[$oidIndex]; // Get array of element
        if ( $this->oidDesc['name'] != $dbOid['name'] ||
            $this->oidDesc['mib'] != $dbOid['mib'] ||
            $this->oidDesc['type'] !=$dbOid['type']
            )
        { // Do update
            $sql='UPDATE '.$this->getTrapsDB()->dbPrefix.'mib_cache SET '.
                'name = :name , type = :type , mib = :mib , textual_convention = :tc , display_hint = :display_hint'.
                ', syntax = :syntax, type_enum = :type_enum, description = :description '.
                ' WHERE id= :id';
            $sqlQuery=$db_conn->prepare($sql);
            
            $sqlParam=array(
                ':name' => $this->oidDesc['name'],
                ':type' => $this->oidDesc['type'],
                ':mib' => $this->oidDesc['mib'],
                ':tc' =>  $this->oidDesc['textconv']??'null',
                ':display_hint' => $this->oidDesc['dispHint']??'null' ,
                ':syntax' => $this->oidDesc['syntax']==null??'null',
                ':type_enum' => $this->oidDesc['type_enum']??'null',
                ':description' => $this->oidDesc['description']??'null',
                ':id' => $this->dbOidAll[$this->dbOidIndex[$this->oidDesc['oid']]['id']]
            );
            
            if ($sqlQuery->execute($sqlParam) === false) {
                $this->getLogging()->log('Error in query : ' . $sql,ERROR,'');
            }
            $this->getLogging()->log('Trap updated : '.$this->oidDesc['name'] . ' / OID : '.$this->oidDesc['oid'],DEBUG );
            return 1;
        }
        else
        {
            $this->getLogging()->log('Trap unchanged : '.$this->oidDesc['name'] . ' / OID : '.$this->oidDesc['oid'],DEBUG );
            return 0;
        }
    }

    /**
     * Create object in DB with object in dbOidIndex
     * @return number : 0=unchanged, 1 = changed, 2=created
     */
    private function update_oid_create()
    {
        // Insert data
        
        $db_conn=$this->getTrapsDB()->db_connect_trap();
        $sql='INSERT INTO '.$this->getTrapsDB()->dbPrefix.'mib_cache '.
            '(oid, name, type , mib, textual_convention, display_hint '.
            ', syntax, type_enum , description ) ' .
            'values (:oid, :name , :type ,:mib ,:tc , :display_hint'.
            ', :syntax, :type_enum, :description )';
        
        if ($this->getTrapsDB()->trapDBType == 'pgsql') $sql .= 'RETURNING id';
        
        $sqlQuery=$db_conn->prepare($sql);
        
        $sqlParam=array(
            ':oid' => $this->oidDesc['oid'],
            ':name' => $this->oidDesc['name'],
            ':type' => $this->oidDesc['type'],
            ':mib' => $this->oidDesc['mib'],
            ':tc' =>  $this->oidDesc['textconv']??'null',
            ':display_hint' => $this->oidDesc['dispHint']??'null',
            ':syntax' => $this->oidDesc['syntax']??'null',
            ':type_enum' => $this->oidDesc['type_enum']??'null',
            ':description' => $this->oidDesc['description']??'null'
        );
        
        if ($sqlQuery->execute($sqlParam) === false) {
            $this->getLogging()->log('Error in query : ' . $sql,1,'');
        }
        
        switch ($this->getTrapsDB()->trapDBType)
        {
            case 'pgsql':
                // Get last id to insert oid/values in secondary table
                if (($inserted_id_ret=$sqlQuery->fetch(PDO::FETCH_ASSOC)) === false) {
                    $this->getLogging()->log('Error getting id - pgsql - ',1,'');
                }
                if (! isset($inserted_id_ret['id'])) {
                    $this->getLogging()->log('Error getting id - pgsql - empty.',ERROR);
                    return 0;
                }
                $this->dbOidIndex[$this->oidDesc['oid']]['id']=$inserted_id_ret['id'];
                break;
            case 'mysql':
                // Get last id to insert oid/values in secondary table
                $sql='SELECT LAST_INSERT_ID();';
                if (($ret_code=$db_conn->query($sql)) === false) {
                    $this->getLogging()->log('Erreur getting id - mysql - ',ERROR);
                    return 0;
                }
                
                $inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
                if ($inserted_id==false) throw new Exception("Weird SQL error : last_insert_id returned false : open issue");
                $this->dbOidIndex[$this->oidDesc['oid']]['id']=$inserted_id;
                break;
            default:
                $this->getLogging()->log('Error SQL type Unknown : '.$this->getTrapsDB()->trapDBType,ERROR);
                return 0;
        }
        
        // Set as newly created.
        $this->dbOidIndex[$this->oidDesc['oid']]['key']=-1;
        return 2;
    }

    /**
     * get all objects for a trap.
     * @param integer $trapId
     * @return array : array of cached objects
     */
    private function cache_db_objects($trapId)
    {
        $dbObjects=array(); // cache of objects for trap in db
        $db_conn=$this->getTrapsDB()->db_connect_trap();
        // Get all objects
        $sql='SELECT * FROM '.$this->getTrapsDB()->dbPrefix.'mib_cache_trap_object where trap_id='.$trapId.';';
        $this->getLogging()->log('SQL query get all traps: '.$sql,DEBUG );
        if (($ret_code=$db_conn->query($sql)) === false) {
            $this->getLogging()->log('No result in query : ' . $sql,1,'');
        }
        $dbObjectsRaw=$ret_code->fetchAll();
        
        foreach ($dbObjectsRaw as $val)
        {
            $dbObjects[$val['object_id']]=1;
        }
        return $dbObjects;
    }
    

}