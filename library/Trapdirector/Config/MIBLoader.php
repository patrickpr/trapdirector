<?php

//use Exception;

namespace Icinga\Module\TrapDirector\Config;

// TODO : create a cache of some kind.
class MIBLoader
{
	protected $snmptranslate; // < snmp translate binary
	protected $snmptranslate_dirs; // < mib include dirs
	
	protected $cache=array(); //< cache of translateoid // TODO DESTROY
	protected $db; //< traps database
	protected $config; //<TrapModuleConfig
	
	public function __construct($snmptranslate,$snmptranslate_dirs,$db,$config)
	{
		$this->snmptranslate=$snmptranslate;
		$this->snmptranslate_dirs=$snmptranslate_dirs;
		
		$this->db=$db;
		$this->config=$config;
				
	}

	// Get all mibs in db which have at least one trap
	public function getMIBList()
	{
		$dbconn = $this->db->getConnection();
		$query=$dbconn->select()
				->distinct()
				->from(
					$this->config->getMIBCacheTableName(),
					array('mib' => 'mib'))
				->where("type = 21")
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
	*	@return array(traps)
	*/
	public function getTrapList($mib)
	{
		$traps=array();
		$dbconn = $this->db->getConnection();
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
					array('name' => 'name', 'oid' => 'oid'))
				->where("mib = '".$mib."' AND type=21") ;
		$names=$dbconn->fetchAll($query);
		foreach ($names as $val)
		{
			$traps[$val->oid]=$val->name;
		}			
		return $traps;
	}
	
	/** Get objects a trap can have
	*	@param int oid of trap
	*	@return : null if trap not found, or array ( <oid> => name/mib/type )
	*/
	public function getObjectList($trap)
	{
		// TODO : add leading '.' if missing
		$objects=array();
		
		// Get trap id in DB
		$dbconn = $this->db->getConnection();
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
					array('id' => 'id'))
				->where("oid = '".$trap."'") ;
		$id=$dbconn->fetchRow($query);
		if ( ($id == null) || ! property_exists($id,'id') ) return null;
		
		$query=$dbconn->select()
				->from(
					array('o' => $this->config->getMIBCacheTableTrapObjName()),
					array('name' => 'o.object_name'))
				->join(
					array('c' => $this->config->getMIBCacheTableName()),
					'o.object_name=c.name',
					array('mib' => 'c.mib','oid' => 'c.oid','type_enum'=>'c.type_enum'))
				->join(
					array('s' => $this->config->getMIBCacheTableSyntax()),
					's.num=c.type',
					array('type' => 's.value')	)			
				->where("o.trap_id = ".$id->id);
		$listObjects=$dbconn->fetchAll($query);
		if ( count($listObjects)==0 ) return null;
		
		foreach ($listObjects as $val)
		{
			$objects[$val->oid]['name']=$val->name;
			$objects[$val->oid]['mib']=$val->mib;
			$objects[$val->oid]['type']=$val->type;
			$objects[$val->oid]['type_enum']=$val->type_enum;
		}
		return $objects;
	}

	/** translate oid in MIB::Name 
	*	@param string oid
	*	@return array (oid -> oid, mib -> mib name, name -> oid name, type -> oid type)
	*/
	public function translateOID($oid)
	{
		// TODO : put a first . if missing
		$retArray=array('oid' => $oid, 'mib' => null, 'name'=>null,'type'=>null);
		$dbconn = $this->db->getConnection();

		$query=$dbconn->select()
				->from(
					array('o' => $this->config->getMIBCacheTableName()),
					array('mib'=>'o.mib','name' => 'o.name','type'=>'o.type','type_enum'=>'o.type_enum'))
				->where('o.oid=\''.$oid.'\'');
		$object=$dbconn->fetchRow($query);
		if ($object != null) 
		{
			$retArray['name']=$object->name;
			$retArray['mib']=$object->mib;
			$retArray['type_enum']=$object->type_enum;
			$query=$dbconn->select()
					->from(
						array('o' => $this->config->getMIBCacheTableSyntax()),
						array('type'=>'o.value'))
					->where('o.num=\''.$object->type.'\'');
			$object=$dbconn->fetchRow($query);
			if ($object != null) 
			{
				$retArray['type']=$object->type;
			}
			
			return $retArray;
		}
		
		// Try to get oid name from snmptranslate
		$matches=array();
		$translate=exec($this->snmptranslate . ' -m ALL -M +'.$this->snmptranslate_dirs.
		    ' '.$oid);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code==0 || $ret_code==FALSE) {
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
		/* TODO : put in DB but only if 
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

	public function countObjects($mib=null,$type=null)
	{
		$dbconn = $this->db->getConnection();
		$query=$dbconn->select()
				->from(
					$this->config->getMIBCacheTableName(),
					array('COUNT(*)'));
		$where=null;
		if ($mib != null)
		{
			$where ="mib = '$mib' ";
		}
		if ($type != null)
		{
			$where=($where != null)?' AND ':'';
			$where.="type='$type'";
		}
		if ($where != null)
		{
			$query->where($where);
		}		
		return $dbconn->fetchOne($query);			
	}
	
}


?>