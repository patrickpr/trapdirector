<?php

use Trapdirector\Logging;
use Trapdirector\Trap;

// start
$time1 = microtime(true);

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2Etc="/etc/icingaweb2";

$trap=null;

try
{
       
    $trap = new Trap($icingaweb2Etc);
    //$Trap = new Trap($icingaweb2Etc,4,'display'); // For debug
    //$Trap = new Trap($icingaweb2Etc,4,'syslog'); // For debug
    //$Trap->setLogging(4,'syslog'); 
    
    // TODO : tranfer this to reset_trap cli command
    $trap->eraseOldTraps();

	$trap->read_trap('php://stdin');

	$trap->applyRules();

	$trap->writeTrapToDB();

	$trap->add_rule_final(microtime(true) - $time1);
	
}
catch (Exception $e) 
{
    if ($trap == null)
    {  // Exception in trap creation : log in display & syslog
        $logging = new Logging();
        $logging->log("Caught exception creating Trap class",2);
    }
    else
    {
	   $trap->trapLog("Exception : ". $e->getMessage(),2,0);
    }
}

//end

exit(0);
