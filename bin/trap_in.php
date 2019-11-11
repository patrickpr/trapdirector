<?php

use Trapdirector\Logging;

// start
$time1 = microtime(true);

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";

$Trap=null;

try
{
    $Trap = new Trap($icingaweb2_etc,4,'syslog');
    //$Trap = new Trap($icingaweb2_etc,4,'display'); // For debug
    //$Trap->setLogging(4,'syslog'); 
    
    // TODO : tranfer this to reset_trap cli command
    $Trap->eraseOldTraps();

	$Trap->read_trap('php://stdin');

	$Trap->applyRules();

	$Trap->writeTrapToDB();

	$Trap->add_rule_final(microtime(true) - $time1);
	
}
catch (Exception $e) 
{
    if ($Trap == null)
    {  // Exception in trap creation : log in display & syslog
        $logging = new Logging();
        $logging->log("Caught exception creating Trap class",2);
    }
    else
    {
	   $Trap->trapLog("Exception : ". $e->getMessage(),2,0);
    }
}

//end

exit(0);

?>
