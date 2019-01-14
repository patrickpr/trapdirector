<?php

// start
$time1 = microtime(true);

require_once ('trap_class.php');


// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
//

$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);

//$Trap->setLogging($debug_level,'display');

$Trap->update_mib_database(true);
echo "Updating syntax and trap objects\n";
$Trap->update_mibs_options();

exit(0);
?>
