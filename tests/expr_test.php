#!/opt/rh/rh-php71/root/bin/php
<?php

require_once ('../bin/trap_class.php');

// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
//

$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);
$Trap->setLogging($debug_level,'display');

$input_stream=fopen('php://stdin', 'r');
while(1)
{
	$rule=chop(fgets($input_stream));
	$rule=$Trap->eval_cleanup($rule);
	echo 'After cleanup : #'.$rule."#\n";
	$item=0;
	try
	{
	  $val = $Trap->evaluation($rule,$item);
	echo "result : ";
	if ($val==true) { echo "true"; } else { echo "false";} 
	echo " $val ";
	echo "\n";
	}
	catch (Exception $e) { echo $e->getMessage() . "\n";}

}
return;
?>
