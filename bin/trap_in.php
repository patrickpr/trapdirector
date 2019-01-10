<?php

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
//

$debug_level=3;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);

//$Trap->setLogging();
$Trap->setLogging($debug_level,'syslog');

//$Trap->create_schema('/usr/share/icingaweb2/modules/trapdirector/SQL/schema_v1.sql','traps_');

//exit(0);

$Trap->eraseOldTraps(30);

//exit(0);

try
{
	$data=$Trap->read_trap('php://stdin');
	//echo 'data : ';print_r($data);
	//echo 'data : ';print_r($Trap->trap_data_ext);

	$Trap->applyRules();

	$Trap->writeTrapToDB();
	
}
catch (Exception $e) 
{
	$Trap->trapLog("Exception trapped : ". $e->getMessage(),2,0);
}

exit(0);

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
