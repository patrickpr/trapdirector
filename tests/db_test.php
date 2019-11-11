#!/opt/rh/rh-php71/root/bin/php
<?php
// TODO
require_once 'bin/trap_class.php';

$options = getopt("c:v:d:b:a:");

$icingaweb2Etc=(array_key_exists('d',$options))?$options['d']:"/etc/icingaweb2";

$debugLevel=4;// 0=No output 1=critical 2=warning 3=trace 4=ALL

$trap = new trap($icingaweb2Etc,$debugLevel,'display');
$trap->setLogging($debugLevel,'display');

if (!array_key_exists('v',$options) || !array_key_exists('c',$options) || !array_key_exists('b',$options))
{
    printf("Need version -v, database -b (mysql,pgsql) command -c (create/update)\n");
    exit(1);
}
$command=$options['c'];
$path=$options['a'];
try {
    switch($command)
    {
        case 'create':
            $schema=($options['b']=='mysql')?'schema_v'.$options['v'].'.sql':'schema_v'.$options['v'].'.pgsql';
            $schema=$path.'SQL/'.$schema;
            $trap->trapsDB->create_schema($schema, 'traps_');
            break;
        case 'update':
            $message=$trap->trapsDB->update_schema($path."SQL/",$options['v'], 'traps_',true);
            printf("Update message : %s\n",$message);
            if ($message == 'ERROR')
            {
                exit(1);
            }
            printf("Messages DONE, updating : \n");
            $message=$trap->trapsDB->update_schema($path."SQL/",$options['v'], 'traps_');
            if ($message == 'ERROR')
            {
                exit(1);
            }
            break;
        default:
            prtinf("Unknown command\n");
            exit(1);
    }
} catch (Exception $e) {
    printf("Caught Exception %s\n",$e->getMessage());
    exit (1);
}

exit(0);
