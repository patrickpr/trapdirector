<?php


//No autoloader when running in CLI.

include (dirname(__DIR__).'/library/Trapdirector/Icinga2Api.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Logging.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Database.php');

include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/MibDatabase.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Mib.php');

include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/RuleUtils.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Rule.php');
include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Plugins.php');

include (dirname(__DIR__).'/library/Trapdirector/TrapsProcess/Trap.php');

