#!/opt/rh/rh-php71/root/bin/php
<?php

require_once('trap_class.php');

// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
//

$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);
$Trap->setLogging($debug_level, 'display');
try {
    exit($Trap->reset_services());
} catch (Exception $e) {
    echo 'ERROR : '. $e->getMessage();
    exit(2);
}


exit(0);
?>
