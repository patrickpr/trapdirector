CLI commands
=============


Delete old traps
-----------------

With default days to keep : 
*	icingacli trapdirector traps rotate

to specify days to keep : 
*	icingacli trapdirector traps rotate --days num_days

Update mib database with mib files
-----------------------------------

* icingacli trapdirector mib update

To run in background, set a pid file

* icingacli trapdirector mib update --pid /path/to/pid.file

To add verbose output, use "--verb"

* icingacli trapdirector mib update --verb

Get database status (number of traps/rules/objects) 
--------------------

* icingacli trapdirector status db

