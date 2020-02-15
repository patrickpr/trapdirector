<?php

//use Exception;

namespace Icinga\Module\TrapDirector\Config;


class MIBLoader
{
	protected $snmptranslate; // < snmp translate binary
	protected $snmptranslate_dirs; // < mib include dirs
	
	protected $cache=array(); //< cache of translateoid // TODO DESTROY
	protected $db; //< traps database
	protected $config; //<TrapModuleConfig
	
	/**
	 * 
	 * @param string $snmptranslate snmptranslate binary
	 * @param string $snmptranslate_dirs dirs to add to snmptranslate
	 * @param \Zend_Db_Adapter_Abstract $db current database
	 * @param TrapModuleConfig $config TrapModuleConfig class instance
	 */
	public function __construct($snmptranslate,$snmptranslate_dirs,$db,$config)
	{
		$this->snmptranslate=$snmptranslate;
		$this->snmptranslate_dirs=$snmptranslate_dirs;
		
		$this->db=$db;
		$this->config=$config;
				
	}

    /**
     * Get all mibs in db which have at least one trap
     * @return array
     */	
	
	public function getMIBList()
	{
		$dbconn = $this->db;
		$query=$dbconn->select()
				->distinct()
				->from(
					$this->config->getMIBCacheTableName(),
					array('mib' => 'mib'))
				->where("type = '21'")
				->order('mib ASC');				;
		$names=$dbconn->fetchAll($query);
		$mib=array();
		foreach($names as $val)
		{
			array_push($mib,$val->mib);
		}
		return $mib;
		
	}
	
	
	/** Get trap list from a mib 
	*	@param $mib string mib name
	*	@return array : traps
	*/
	public function getTrapList($mib)
	{
		$traps=array();
		$dbconn = $this->db;
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
				    array('name' => 'name', 'oid' => 'oid', 'description' => 'description'))
				->where("mib = '".$mib."' AND type='21'") ;
		$names=$dbconn->fetchAll($query);
		foreach ($names as $val)
		{
			$traps[$val->oid]=$val->name;
		}			
		return $traps;
	}
	
	/** Get objects a trap can have
	*	@param string $trap oid of trap
	*	@return array|null : null if trap not found, or array ( <oid> => name/mib/type )
	*/
	public function getObjectList($trap)
	{
		$objects=array();
		
		// Get trap id in DB
		$dbconn = $this->db;
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
					array('id' => 'id'))
				->where("oid = '".$trap."'") ;
		$id=$dbconn->fetchRow($query);
		if ( ($id == null) || ! property_exists($id,'id') ) return null;
		
		$query=$dbconn->select()
		        ->from(
		            array('c' => $this->config->getMIBCacheTableName()),
		            array('name' => 'c.name','mib' => 'c.mib','oid' => 'c.oid','type_enum'=>'c.type_enum',
		                'type' => 'c.syntax', 'text_conv' => 'c.textual_convention', 'disp' => 'display_hint',
		                'description' => 'c.description'))
		        ->join(
		            array('o' => $this->config->getMIBCacheTableTrapObjName()),
		            'o.trap_id='.$id->id )
		        ->where("o.object_id = c.id");
		$listObjects=$dbconn->fetchAll($query);
		if ( count($listObjects)==0 ) return null;
		
		foreach ($listObjects as $val)
		{
			$objects[$val->oid]['name']=$val->name;
			$objects[$val->oid]['mib']=$val->mib;
			$objects[$val->oid]['type']=$val->type;
			$objects[$val->oid]['type_enum']=$val->type_enum;
			$objects[$val->oid]['text_conv']=$val->text_conv;
			$objects[$val->oid]['disp']=$val->disp;
			$objects[$val->oid]['description']=$val->description;
		}
		return $objects;
	}

	/** translate oid in MIB::Name 
	*	@param string $oid
	*	@return array|null :  return array with index (oid -> oid, mib -> mib name, name -> oid name, type -> oid type)
	*/
	public function translateOID($oid)
	{
	    if (!preg_match('/^\./',$oid)) $oid = '.' . $oid; // Add a leading '.'
		$retArray=array('oid' => $oid, 'mib' => null, 'name'=>null,'type'=>null);
		$dbconn = $this->db;

		$query=$dbconn->select()
				->from(
					array('o' => $this->config->getMIBCacheTableName()),
					array('mib'=>'o.mib','name' => 'o.name','type'=>'o.syntax',
					    'type_enum'=>'o.type_enum', 'description'=>'o.description'))
				->where('o.oid=\''.$oid.'\'');
		$object=$dbconn->fetchRow($query);
		if ($object != null) 
		{
			$retArray['name']=$object->name;
			$retArray['mib']=$object->mib;
			$retArray['type_enum']=$object->type_enum;
			$retArray['type']=$object->type;
			$retArray['description']=$object->description;
			return $retArray;
		}
		
		// Try to get oid name from snmptranslate
		$matches=array();
		$translate=exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
		    ' '.$oid);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code===0 || $ret_code===false) {
			return null;
		} 
		$retArray['mib']=$matches[1];
		$retArray['name']=$matches[2];
		
		$translate=exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.' -Td -On ' . $matches[0] .
			" | grep SYNTAX | sed 's/SYNTAX[[:blank:]]*//'");
		if (preg_match('/(.*)\{(.*)\}/',$translate,$matches))
		{
		    $retArray['type']=$matches[1];
		    $retArray['type_enum']=$matches[2];
		}
		else
		{
			$retArray['type']=$translate;
			$retArray['type_enum']='';			
		}
		$retArray['description']=null;
		/* TODO : put in DB (but maybe only in trap_class).
		$query=$db->getConnection()->insert(
			$this->getModuleConfig()->getTrapRuleName(),
			$array(
				'oid'	=>
				'mib'	=>
				'name'	=>
				'type' 	=>
				'textual_convention' =>
				'display_hint'	=>
				'type_enum'	=>
			)
		);
		*/
		return $retArray;
						
	}

	/**
	 * Get number of objects in db
	 * @param string $mib filter by MIB
	 * @param string $type filter by type (21=trap)
	 * @return number number of entries in db.
	 */
	public function countObjects($mib=null,$type=null)
	{
		$dbconn = $this->db;
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
					array('COUNT(*)'));
		$where=null;
		if ($mib !== null)
		{
			$where ="mib = '$mib' ";
		}
		if ($type !== null)
		{
			$where=($where !== null)?' AND ':'';
			$where.="type='$type'";
		}
		if ($where !== null)
		{
			$query->where($where);
		}		
		return $dbconn->fetchOne($query);			
	}
	
	/**
	 * Get trap by oid, or if null, by id
	 * @param string $oid
	 * @param integer $id
	 * @return array trap details
	 */
	public function getTrapDetails($oid=null,$id=null)
	{	    
	    // Get trap id in DB
	    if ($oid===null)
	    {
	        $where="c.id = '$id'";
	    }
	    else
	    {
	        $where="c.oid = '$oid'";
	    }
	    $query=$this->db->select()
           ->from(
            array('c' => $this->config->getMIBCacheTableName()),
            array('name' => 'c.name','mib' => 'c.mib','oid' => 'c.oid','type_enum'=>'c.type_enum',
                'type' => 'c.syntax', 'text_conv' => 'c.textual_convention', 'disp' => 'display_hint',
                'description' => 'c.description'))
            ->where($where);
        $trap=$this->db->fetchRow($query);
        
        return $trap;
	}
	
}
