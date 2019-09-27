<?php

namespace Icinga\Module\TrapDirector\Config;

class TrapModuleConfig
{
    /********** Database configuration ***********************/
	// Database prefix for tables 
    protected $table_prefix; //< Database prefix for tables 	
	protected $DBConfigDefaults=array(
		'db_remove_days' => 60, // number of days before removing traps
		'log_destination' => 'syslog', // Log destination for trap handler
		'log_file' => '/tmp/trapdirector.log', // Log file
		'log_level' => 2, // log level
	);
	// get default values for dbconfig
	public function getDBConfigDefaults() { return $this->DBConfigDefaults;}
	// Minimum DB version
	static public function getDbMinVersion() { return 1;}	
	// Current DB version
	static public function getDbCurVersion() { return 1;}

	/************ Module configuration **********************/
	// Module base path
	static public function urlPath() { return 'trapdirector'; }
	static public function getapiUserPermissions() { return array("status", "objects/query/Host", "objects/query/Service" , "actions/process-check-result"); } //< api user permissions required
	
	/*********** Log configuration *************************/
	protected $logLevels=array(0=>'No output', 1=>'critical', 2=>'warning', 3=>'trace', 4=>'ALL');
	public function getlogLevels() { return $this->logLevels;}
	protected $logDestinations=array('syslog'=>'syslog','file'=>'file','display'=>'display');
	public function getLogDestinations() { return $this->logDestinations;}
	
	function __construct($prefix)
	{
		$this->table_prefix=$prefix;
	}
	
	/************  Database table names ********************/
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
	
	// Mib cache tables
	public function getMIBCacheTableName() { return $this->table_prefix . 'mib_cache'; }
	public function getMIBCacheTableTrapObjName() { return $this->table_prefix . 'mib_cache_trap_object'; }
	public function getMIBCacheTableSyntax() { return $this->table_prefix . 'mib_cache_syntax'; }
	public function getMIBCacheTableTC() { return array('t' => $this->table_prefix . 'mib_cache_tc'); }
	
	
	/****************** Database queries *******************/
	// DB columns to display in view table (prefix is set for table in getTrapTableName)
	// Note : must have 'id' and 'timestamp'
	public function getTrapListDisplayColumns()
	{
		return array(
			'timestamp'		=> 'UNIX_TIMESTAMP(t.date_received)',
			'source_ip'		=> 'CASE WHEN t.source_name IS NULL THEN t.source_ip ELSE t.source_name END as source_ip',
			'trap_oid'		=> "CASE WHEN t.trap_name IS NULL OR t.trap_name = '' THEN t.trap_oid ELSE t.trap_name END",
			'status'		=> 't.status',
			'status_detail'	=> 't.status_detail',
			'process_time'	=> 't.process_time',
			'id'           	=> 't.id',
			//'destination_port'           	=> 't.destination_port',
		);
	}
	public function getTrapListSearchColumns()
	{
		return array(
			't.date_received',
			't.source_ip',
			't.source_name',
			't.trap_name',
			't.trap_oid',
			't.status',
			't.id',
			//'destination_port'           	=> 't.destination_port',
		);
	}	
	// Titles display in Trap List table
	public function getTrapListTitles()
	{
		return array(
			'timestamp'		=> 'Time',
			'source_ip'		=> 'Source IP/name',
			'trap_oid'		=> 'Trap OID',
			'status'		=> 'Status',
			'status_detail'	=> 'Status detail',
			'process_time'	=> 'Processing time',
			//'destination_port' => 'Destination Port',
			//'id'			=> 'Id',
		);
	}

	// DB columns to display in host view table (prefix is set for table in getTrapTableName)
	// Note : must have 'source_ip' and 'last_sent'
	public function getTrapHostListDisplayColumns()
	{
	    return array(
	        'source_name'  =>  't.source_name',
	        'source_ip'    =>  't.source_ip',
	        'trap_oid'     =>  't.trap_oid',
	        'count'        =>  'count(*)',
	        'last_sent'    =>  'UNIX_TIMESTAMP(max(t.date_received))'
	    );
	}

	public function getTrapHostListSearchColumns()
	{
	    return array(); // No search needed on this table
	}
	// Titles display in Trap List table
	public function getTrapHostListTitles()
	{
	    return array(
	        'trap_oid'		=> 'Trap OID',
	        'count'		    => 'Number of traps received',
	        'last_sent'     => 'Last trap received'
	    );
	}
	
	
	
	// DB columns to display in view table (prefix is set for table in getTrapTableName)
	// Note : must have 'id' and 'timestamp'
	public function getHandlerListDisplayColumns()
	{
		return array(
			'host_name'		=> 'r.host_name',//'UNIX_TIMESTAMP(t.date_received)',
			'host_group_name'=> 'r.host_group_name',
			'source_ip'		=> "CASE WHEN r.ip4 IS NULL THEN r.ip6 ELSE r.ip4 END",
			'trap_oid'		=> 'r.trap_oid',
			'rule'			=> 'r.rule',
			'action_match'	=> 'r.action_match',
			'action_nomatch'=> 'r.action_nomatch',
			'service_name'	=> 'r.service_name',
			'num_match'		=> 'r.num_match',
			'id'           	=> 'r.id'
		);
	}
	// Titles display in Trap List table
	public function getHandlerListTitles()
	{
		return array(
			'host_name'		=> 'Host/Group Name',
			'source_ip'		=> 'Source IP',
			'trap_oid'		=> 'Trap OID',
			'rule'			=> 'Rule',
			'action_match'	=> 'On rule match',
			'action_nomatch'=> 'On rule dont match',
			'service_name'	=> 'Service Name',
			'num_match'		=> 'Has matched'			
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
			'host_group_name'		=> 'r.host_group_name',
			'rule'			=> 'r.rule',
			'action_match'	=> 'r.action_match',
			'action_nomatch'=> 'r.action_nomatch',
			'service_name'	=> 'r.service_name',
			'revert_ok'		=> 'r.revert_ok',
			'display'		=> 'r.display',
			'modified'		=> 'UNIX_TIMESTAMP(r.modified)',
            'modifier'		=> 'r.modifier'
		);
	}	
		
	// Trap detail (<key> => <title> <sql select>)
	public function trapDetailQuery()
	{
		return array(
			'timestamp'			=> array('Date','UNIX_TIMESTAMP(t.date_received)'),
			'source_ip'			=> array('Source IP','t.source_ip'),
			'source_name'		=> array('Source name','t.source_name'),
			'source_port'		=> array('Source port','t.source_port'),
			'destination_ip'	=> array('Destination IP','t.destination_ip'),
			'destination_port'	=> array('Destination port','t.destination_port'),			
			'trap_oid'			=> array('Numeric OID','t.trap_oid'),
			'trap_name'			=> array('Trap name','t.trap_name'),
			'trap_name_mib'		=> array('Trap MIB','t.trap_name_mib'),
			'status'			=> array('Processing status','t.status'),
			'status_detail'		=> array('Status details','t.status_detail'),
			'process_time'		=> array('Trap processing time','t.process_time'),			
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
