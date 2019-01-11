<?php

namespace Icinga\Module\TrapDirector\Config;

class TrapModuleConfig
{
	// Database prefix for tables 
	protected $table_prefix;
	
	protected $DBConfigDefaults=array(
		'db_remove_days' => 60, // number of days before removing traps
		'log_destination' => 'syslog', // Log destination for trap handler
		'log_file' => '/tmp/trapdirector.log', // Log file
		'log_level' => 2, // log level
	);
	
	protected $logLevels=array(0=>'No output', 1=>'critical', 2=>'warning', 3=>'trace', 4=>'ALL');
	public function getlogLevels() { return $this->logLevels;}
	protected $logDestinations=array('syslog'=>'syslog','file'=>'file','display'=>'display');
	public function getLogDestinations() { return $this->logDestinations;}
	
	function __construct($prefix)
	{
		$this->table_prefix=$prefix;
	}
	// DB table name of trap received list : prefix 't'
	public function getTrapTableName() 
	{ 
		return array('t' => $this->table_prefix . 'received'); 
	}
	// DB table name of trap data  list : prefix 'd'
	public function getTrapDataTableName() 
	{ 
		return array('d' => $this->table_prefix . 'received_data'); 
	}	

	// DB table name of rules : prefix 'r'
	public function getTrapRuleName() 
	{ 
		return array('r' => $this->table_prefix . 'rules'); 
	}		
	
	// DB table name of db config : prefix 'c'
	public function getDbConfigTableName() 
	{ 
		return array('c' => $this->table_prefix . 'db_config');
	}

	
	
	// get default values for dbconfig
	public function getDBConfigDefaults() { return $this->DBConfigDefaults;}
	// Minimum DB version 
	public function getDbMinVersion() { return 1;}
	
	// Current DB version 
	public function getDbCurVersion() { return 1;}
	
	// DB columns to display in view table (prefix is set for table in getTrapTableName)
	// Note : must have 'id' and 'timestamp'
	public function getTrapListDisplayColumns()
	{
		return array(
			'timestamp'		=> 'UNIX_TIMESTAMP(t.date_received)',
			'source_ip'		=> 't.source_ip',
			'trap_oid'		=> "CASE WHEN t.trap_name IS NULL OR t.trap_name = '' THEN t.trap_oid ELSE t.trap_name END",
			'status'		=> 't.status',
			'id'           	=> 't.id',
			//'destination_port'           	=> 't.destination_port',
		);
	}
	// Titles display in Trap List table
	public function getTrapListTitles()
	{
		return array(
			'timestamp'		=> 'Time',
			'source_ip'		=> 'Source IP',
			'trap_oid'		=> 'Trap OID',
			'status'		=> 'Status',
			//'destination_port' => 'Destination Port',
			//'id'			=> 'Id',
		);
	}

	// DB columns to display in view table (prefix is set for table in getTrapTableName)
	// Note : must have 'id' and 'timestamp'
	public function getHandlerListDisplayColumns()
	{
		return array(
			'host_name'		=> 'r.host_name',//'UNIX_TIMESTAMP(t.date_received)',
			'source_ip'		=> "CASE WHEN r.ip4 IS NULL THEN r.ip6 ELSE r.ip4 END",
			'trap_oid'		=> 'r.trap_oid',
			'rule'			=> 'r.rule',
			'action_match'	=> 'r.action_match',
			'action_nomatch'=> 'r.action_nomatch',
			'service_name'	=> 'r.service_name',
			'id'           	=> 'r.id'
		);
	}
	// Titles display in Trap List table
	public function getHandlerListTitles()
	{
		return array(
			'host_name'		=> 'Host Name',
			'source_ip'		=> 'Source IP',
			'trap_oid'		=> 'Trap OID',
			'rule'			=> 'Rule',
			'action_match'	=> 'On rule match',
			'action_nomatch'=> 'On rule dont match',
			'service_name'	=> 'Service Name',			
			//'id'			=> 'Id',
		);
	}

	// handler update (<key> => <sql select>)
	public function ruleDetailQuery()
	{
		return array(
			'id'           	=> 'r.id',
			'ip4'			=> "r.ip4",
			'ip6'			=> "r.ip6",
			'trap_oid'		=> 'r.trap_oid',
			'host_name'		=> 'r.host_name',
			'rule'			=> 'r.rule',
			'action_match'	=> 'r.action_match',
			'action_nomatch'=> 'r.action_nomatch',
			'service_name'	=> 'r.service_name',
			'revert_ok'		=> 'r.revert_ok',
			'display'		=> 'r.display',
			'modified'		=> 'UNIX_TIMESTAMP(r.modified)'
		);
	}	
	
	// Module base path
	public function urlPath() { return 'trapdirector'; }
	
	// Trap detail (<key> => <title> <sql select>)
	public function trapDetailQuery()
	{
		return array(
			'timestamp'		=> array('Date','UNIX_TIMESTAMP(t.date_received)'),
			'source_ip'		=> array('Source IP','t.source_ip'),
			'source_port'		=> array('Source port','t.source_port'),
			'destination_ip'		=> array('Destination IP','t.destination_ip'),
			'destination_port'		=> array('Destination port','t.destination_port'),			
			'trap_oid'		=> array('Numeric OID','t.trap_oid'),
			'trap_name'		=> array('Trap name','t.trap_name'),
			'trap_name_mib'		=> array('Trap MIB','t.trap_name_mib'),
			'status'		=> array('Processing status','t.status'),
		);
	}
	// Trap detail : additional data (<key> => <title> <sql select>)
	public function trapDataDetailQuery()
	{
		return array(
			'oid'				=> array('Numeric OID','d.oid'),
			'oid_name'			=> array('Text OID','d.oid_name'),
			'oid_name_mib'		=> array('MIB','d.oid_name_mib'),
			'value'				=> array('Value','d.value'),
		);
	}
	// foreign key of trap data table
	public function trapDataFK() { return 'trap_id';}
	
	// Max items in a list
	public function itemListDisplay() { return 25; }
}


?>