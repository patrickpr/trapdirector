<?php



trait MibDatabase
{
    /** @return \Trapdirector\Logging */
    abstract public function getLogging();
    
    /** @return \Trapdirector\Database */
    abstract public function getTrapsDB();
  
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
    
}