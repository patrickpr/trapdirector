<?php

//use Exceptions;

require_once ('trap_class.php');

// Icinga etc path

$icingaweb2_etc="/etc/icingaweb2";
$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc,$debug_level);

/***************************
  Variables
***************************/

// Icinga etc path
$icingaweb2_etc="/etc/icingaweb2";
$trap_module_config=$icingaweb2_etc."/modules/trapdirector/config.ini";
$icingaweb2_ressources=$icingaweb2_etc."/resources.ini";

// SNMP stuff
$snmptranslate='/usr/bin/snmptranslate';


// debug
$debug_level=4;  // 0=No output 1=critical 2=warning 3=trace 4=ALL
$debug_file="/tmp/trapdebug.txt";

/** Send log 
*	@param	string $message Message to log
*	@param	int $level 1=critical 2=warning 3=trace 4=debug
*	@param  int $destination Not implemented
*	@return None
**/
function Trap_Log( $message, $level, $destination )
{
  	global $debug_level;
	global $debug_file;
	if ($debug_level >= $level) 
	{
		file_put_contents ($debug_file, 
			'[File '.__FILE__ .' # line:'.__LINE__.'] '.$message . "\n", FILE_APPEND);
	}
}

/** connects to trap database
*	@param string database : 'traps' for traps database, 'ido' for ido database
*	@return PDO connection
**/
function db_connect($database) {
	$confarray=get_database($database);
	//	$dsn = 'mysql:dbname=traps;host=127.0.0.1';
	$dsn= $confarray[0].':dbname='.$confarray[2].';host='.$confarray[1];
	$user = $confarray[3];
	$password = $confarray[4];
	try {
		$dbh = new PDO($dsn, $user, $password);
	} catch (PDOException $e) {
		Trap_Log('Connexion échouée : ' . $e->getMessage(),1,0);
		exit(1);
	}
	return $dbh;
}

/** Get database connexion options
*	@param string database : 'traps' for traps database, 'ido' for ido database
*	@return array( DB type (mysql, pgsql.) , db_host, database name , db_user, db_pass)
**/
function get_database($database) {
	global $trap_module_config,$icingaweb2_ressources;
	$trap_config=parse_ini_file($trap_module_config,true);
	if ($trap_config == false) 
	{
		Trap_Log("Error reading ini file : ".$trap_module_config,1,0); 
		exit(1);
	}
	if ($database == 'traps')
	{
		if (!isset($trap_config['config']['database'])) 
		{
			Trap_Log("No Config/database in config file: ".$trap_module_config,1,0); 
			exit(1);
		}
		$db_name=$trap_config['config']['database'];
	} 
	else if ($database == 'ido')
	{
		if (!isset($trap_config['config']['IDOdatabase'])) 
		{
			Trap_Log("No Config/IDOdatabase in config file: ".$trap_module_config,1,0); 
			exit(1);
		}
		$db_name=$trap_config['config']['IDOdatabase'];		
	}
	else
	{
		Trap_Log("Unknown database type : ".$database,1,0); 
		exit(1);		
	}	
	Trap_Log("Found database in config file: ".$db_name,3,0); 
	$db_config=parse_ini_file($icingaweb2_ressources,true);
	if ($db_config == false) 
	{
		Trap_Log("Error reading ini file : ".$icingaweb2_ressources,1,0); 
		exit(1);
	}
	if (!isset($db_config[$db_name])) 
	{
		Trap_Log("No Config/database in config file: ".$icingaweb2_ressources,1,0); 
		exit(1);
	}
	$db_type=$db_config[$db_name]['db'];
	$db_host=$db_config[$db_name]['host'];
	$db_sql_name=$db_config[$db_name]['dbname'];
	$db_user=$db_config[$db_name]['username'];
	$db_pass=$db_config[$db_name]['password'];	
	Trap_Log( "$db_type $db_host $db_sql_name $db_user $db_pass",3,0); 
	return array($db_type,$db_host,$db_sql_name,$db_user,$db_pass);
}	

/***************************** 
  Trap receive
*****************************/

//Read data from snmptrapd from stdin
$input_stream=fopen("php://stdin", 'r');

if ($input_stream==FALSE)
{
	Trap_Log("Error reading stdin !",1,0); 
	exit(1);
}

// line 1 : host
$host=chop(fgets($input_stream));
if ($host == FALSE)
{
	Trap_Log("Error reading Host !",1,0); 
	exit(1);
}

$IP=chop(fgets($input_stream));
if ($IP == FALSE)
{
	Trap_Log("Error reading IP !",1,0); 
	exit(1);
}
$IP_source=$IP_dest=$IP_source_port=$IP_dest_port="unknown";
$ret_code=preg_match('/.DP: \[(.*)\]:(.*)->\[(.*)\]:(.*)/',$IP,$matches);
if ($ret_code==0 || $ret_code==FALSE) 
{
	Trap_Log('Error parsing IP : '.$IP,2,0);
} 
else 
{
	$IP_source=$matches[1];
	$IP_dest=$matches[3];
	$IP_source_port=$matches[2];
	$IP_dest_port=$matches[4];
}

$trap_vars=array();
while (($vars=chop(fgets($input_stream))) !=FALSE)
{
	$ret_code=preg_match('/^([^ ]+) (.*)$/',$vars,$matches);
	if ($ret_code==0 || $ret_code==FALSE) 
	{
		$trap_vars[$vars]="No match";
	} else {
		$trap_vars[$matches[1]]=$matches[2];
	}
	//array_push($trap_vars,$vars);
}
$trap_oid='unknown';
foreach ($trap_vars as $key => $value) {
	if ($key == '.1.3.6.1.6.3.1.1.4.1.0')
	{
		$trap_oid=$value;
	}
}
if ($trap_oid=='unknown') {
	Trap_Log('no trap oid found',2,0);
}


// Connect to trap database

$db_conn=db_connect('traps');


/***************************** 
  Fin rule for trap
*****************************/



// Try to get oid name from snmptranslate
$translate=exec($snmptranslate . ' '.$trap_oid,$translate_output);
$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
if ($ret_code==0 || $ret_code==FALSE) {
	$trap_oid_name='NULL';
	$trap_oid_mib='NULL';
} else {
	$trap_oid_name=$db_conn->quote($matches[2]);
	$trap_oid_mib=$db_conn->quote($matches[1]);
}

$IP_source=$db_conn->quote($IP_source);
$IP_dest=$db_conn->quote($IP_dest);
$IP_source_port=$db_conn->quote($IP_source_port);
$IP_dest_port=$db_conn->quote($IP_dest_port);
$trap_oid=$db_conn->quote($trap_oid);

$sql= 'INSERT INTO traps.traps_received ' .
	  '(`date_received`, `source_ip`, `source_port`, `destination_ip`, `destination_port`, `trap_oid`, `status`, `trap_name`, `trap_name_mib`) ' . 
	   'VALUES (\''. date("Y-m-d H:i:s") .'\',  '.$IP_source.' ,  '.$IP_source_port.' , ' . 
	   ' '. $IP_dest .' ,  '. $IP_dest_port .' ,  '. $trap_oid.' , \'waiting\' , '.$trap_oid_name.', '.$trap_oid_mib.' );';


//$sql = $db_conn->quote($sql);
Trap_Log('sql : '.$sql,3,0);
if (($ret_code=$db_conn->query($sql)) == FALSE) {
	Trap_Log('Erreur insertion SQL : '.$sql,2,0);
	exit(1);
}

Trap_Log('Insertion OK : ',3,0);

// Get last id to insert oid/values in secondary table
$sql='SELECT LAST_INSERT_ID();';
if (($ret_code=$db_conn->query($sql)) == FALSE) {
	Trap_Log('Erreur recuperation id',2,0);
	exit(1);
}

// TODO check value of $inserted_id
$inserted_id=$ret_code->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()'];
Trap_Log('id recupere : '.$inserted_id,3,0);
foreach ($trap_vars as $key => $value) {
	
	// get rid of trapoid already saved in main table.
	if ($key == '.1.3.6.1.6.3.1.1.4.1.0') continue;
	// Translate oids
	$translate=exec($snmptranslate . ' '.$key,$translate_output);
	$ret_code=preg_match('/(.*)::(.*)/',$translate,$matches);
	if ($ret_code==0 || $ret_code==FALSE) {
		$trap_oid_name='NULL';
		$trap_oid_mib='NULL';
	} else {
		$trap_oid_name=$db_conn->quote($matches[2]);
		$trap_oid_mib=$db_conn->quote($matches[1]);
	}	
	// TODO : detect if trap value is encoded and decode it to UTF-8 for database

	// Write OID / Value in secondary table.
	$sql='INSERT INTO traps.traps_received_data (`oid`, `value`,`trap_id`,`oid_name`,`oid_name_mib`) VALUES'.
		 '('. $db_conn->quote($key) .' , '. $db_conn->quote($value) .', \''. $inserted_id.'\' , '. 
		 $trap_oid_name . ',' .$trap_oid_mib.');';
	if (($ret_code=$db_conn->query($sql)) == FALSE) {
		Trap_Log('Erreur insertion : ' . $sql,2,0);
		exit(1);
	}	
}


// send alarm : 
//echo "[`date +%s`] PROCESS_SERVICE_CHECK_RESULT;$HOST_NAME;$SERVICE_NAME;0;Auto-reset (`date +"%m-%d-%Y %T"`)." >> /var/run/icinga2/cmd/icinga2.cmd

//file_put_contents ('/tmp/trap1.txt','Whoami : '.shell_exec("whoami"). "\n", FILE_APPEND);
file_put_contents ('/tmp/trap1.txt','Host : '.$host. "\n", FILE_APPEND);
file_put_contents ('/tmp/trap1.txt','IP : '.$IP. "\n", FILE_APPEND);
foreach ($trap_vars as $key => $value) {
	file_put_contents ('/tmp/trap1.txt','var : '.$key. ' => ' .$value . "\n", FILE_APPEND);
}

exit (0);

/*
Check services in down state : 
	 icingacli monitoring list services --columns 'host,service,service_state,service_last_state_change' --format='"$host$" "$service$" $service_state$ $service_last_state_change$'
	  --problem : hard et not ack.	 
	 ou format=csv 
            'host_name',
            'host_state',
            'host_output',
            'host_handled',
            'host_acknowledged',
            'host_in_downtime',
            'service_description',
            'service_state',
            'service_acknowledged',
            'service_in_downtime',
            'service_handled',
            'service_output',
            'service_perfdata',
            'service_last_state_change'
	 
*/	 
?>
