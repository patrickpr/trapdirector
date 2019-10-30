#!/opt/rh/rh-php71/root/bin/php
<?php

require_once ('bin/trap_class.php');

$options = getopt("r:d:");

$icingaweb2_etc=(array_key_exists('d',$options))?$options['d']:"/etc/icingaweb2";

$debug_level=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$Trap = new Trap($icingaweb2_etc);
$Trap->setLogging($debug_level,'display');

$input=array_key_exists('r',$options);

if (! $input) {
  $input_stream=fopen('php://stdin', 'r');
  $rule=chop(fgets($input_stream));
} else
  $rule=$options['r'];

try
{
  $rule=$Trap->eval_cleanup($rule);
  //echo 'After cleanup : #'.$rule."#\n";
  $item=0;
  $val = $Trap->evaluation($rule,$item);
  if ($val==true) { echo "true"; } else { echo "false";}
  echo "\n";
}
catch (Exception $e) { echo $e->getMessage() . "\n"; exit(1);}

exit(0);
?>
