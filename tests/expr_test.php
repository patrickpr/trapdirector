#!/opt/rh/rh-php71/root/bin/php
<?php

require_once 'bin/trap_class.php';

$options = getopt("r:d:");

$icingaweb2Etc=(array_key_exists('d',$options))?$options['d']:"/etc/icingaweb2";

$debugLevel=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$trap = new trap($icingaweb2Etc);
$trap->setLogging($debugLevel,'display');

$input=array_key_exists('r',$options);

if (! $input) {
  $inputStream=fopen('php://stdin', 'r');
  $rule=chop(fgets($inputStream));
} else
  $rule=$options['r'];

try
{
  $rule=$trap->eval_cleanup($rule);
  //echo 'After cleanup : #'.$rule."#\n";
  $item=0;
  $val = $trap->evaluation($rule,$item);
  if ($val==true) { printf( "true"); } else { printf( "false");}
  printf("\n");
}
catch (Exception $e) { printf("%s\n",$e->getMessage()); exit(1);}

exit(0);
?>
