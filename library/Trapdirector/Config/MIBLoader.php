<?php

//use Exception;

namespace Icinga\Module\TrapDirector\Config;

// TODO : create a cache of some kind.
class MIBLoader
{
	protected $filename; //< string file loaded

	// TODO : put all in protected after debug done
	public $mibList; //< array of all mibs
	/*  traps : 
		<oid>
		-> mib
		-> name
		-> objects
			<oid>
				->name
				->mib
				->type
	*/
	public $traps; //< array of traps
	
	public function __construct($file)
	{
		$input_stream=fopen($file, 'r');

		if ($input_stream==FALSE)
		{
			throw new Exception('Cannot load MIB : '.$file);
		}
		
		$this->mibList=array();
		$this->traps=array();
		
		while ( ($line=fgets($input_stream)) != FALSE )
		{
			$ret_code=preg_match('/([0-9\.]+) +([^ :]+)::([^ ]+) +(.*)/',$line,$matches);
			
			if ($ret_code==0  || $ret_code==FALSE)
			{
				// TODO : put warning somewhere 	echo 'Error adding '.$line.'<br>';
				continue;
			}
			//echo 'Found : ' . $matches[1] . '#' . $matches[2].'#' . $matches[3].'<br>';

			$oid=$matches[1];
			$mib=$matches[2];
			$name=$matches[3];
			
			$this->traps[$oid]['mib']=$mib;
			$this->traps[$oid]['name']=$name;
			$this->traps[$oid]['objects']=array();
				
			
			if (! in_array($mib,$this->mibList) ) // Add mib in mib list if not present 
			{
				array_push($this->mibList,$mib);
			}
			// Get objects in traps with oid & type
			$objects=$matches[4];
			//.1.3.6.1.4.1.8072.2.3.2.1 'Integer32' NET-SNMP-EXAMPLES-MIB::netSnmpExampleHeartbeatRate
			while (preg_match('/ *([0-9\.]+) +\'([^\']+)\' +([^ :]+)::([^ ]+)(.*)/',$objects,$matches))
			{
				$this->traps[$oid]['objects'][$matches[1]]['name']=$matches[4];
				$this->traps[$oid]['objects'][$matches[1]]['mib']=$matches[3];
				$this->traps[$oid]['objects'][$matches[1]]['type']=$matches[2];
				
				$objects=$matches[5];
			}
			
		}
		
	}

	public function getMIBList()
	{
		return $this->mibList;
	}
	
	public function getTrapList($mib)
	{
		$traps=array();
		foreach ($this->traps as $key => $val)
		{
			if ($this->traps[$key]['mib'] == $mib)
			{
				$traps[$key]=$this->traps[$key]['name'];
			}
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
		if (isset($this->traps[$trap]['objects']))
		{
			return $this->traps[$trap]['objects'];
		}
		else
		{
			return null;
		}
	}

	/** translate oid in MIB::Name 
	*	@param string oid
	*	@return array (oid -> oid, mib -> mib name, name -> oid name, type -> oid type)
	*/
	public function translateOID($oid)
	{
		// TODO : get binary & dirs from config / database
		$snmptranslate='/usr/bin/snmptranslate ';
		$snmptranslate_dirs='/usr/share/icingaweb2/modules/trapdirector/mibs:/usr/share/snmp/mibs';
		
		$retArray=array('oid' => $oid, 'mib' => null, 'name'=>null,'type'=>null);
		// Try to get oid name from snmptranslate
		$translate=exec($snmptranslate . ' -m ALL -M '.$snmptranslate_dirs.
		' '.$oid,$translate_output);
		$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
		if ($ret_code==0 || $ret_code==FALSE) {
			return null;
		} 
		$retArray['mib']=$matches[1];
		$retArray['name']=$matches[2];
		
		$translate=exec($snmptranslate . ' -m ALL -M '.$snmptranslate_dirs.' -Td -On ' . $matches[0] .
			" | grep SYNTAX | sed 's/SYNTAX\t//'"
		   ,$translate_output);
		$retArray['type']=$translate;
		
		return $retArray;
						
	}
	
}


?>