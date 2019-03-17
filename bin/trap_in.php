<?php

// start
$time1 = microtime(true);

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";

$Trap = new Trap($icingaweb2_etc);

//$Trap->setLogging(4,'display'); // For debug

// TODO : tranfer this to reset_trap cli command
$Trap->eraseOldTraps();

try
{
	$Trap->read_trap('php://stdin');

	$Trap->applyRules();

	$Trap->writeTrapToDB();

}
catch (Exception $e) 
{
	$Trap->trapLog("Exception : ". $e->getMessage(),2,0);
}

//end
$Trap->add_rule_final(microtime(true) - $time1);

exit(0);

?>
