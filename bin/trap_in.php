<?php

// start
$time1 = microtime(true);

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
//

$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);

// Set by DB
//$Trap->setLogging($debug_level,'syslog');
//$Trap->setLogging($debug_level,'display');

// TODO : tranfer this to reset_trap script
$Trap->eraseOldTraps();

try
{
	$data=$Trap->read_trap('php://stdin');


	$Trap->applyRules();

	$Trap->writeTrapToDB();

}
catch (Exception $e) 
{
	$Trap->trapLog("Exception trapped : ". $e->getMessage(),2,0);
}

//end
$Trap->add_rule_final(microtime(true) - $time1);

exit(0);

?>
