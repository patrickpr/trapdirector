CLI commands
=============


Delete old traps
-----------------

With default days to keep : 
*	icingali trapdirector traps rotate

to specify days to keep : 
*	icingali trapdirector traps rotate --days num_days

Update mib database with mib files
-----------------------------------

* icingli trapdirector mib update

To run in background, set a pid file

* icingli trapdirector mib update --pid /path/to/pid.file


Get database status (number of traps/rules/objects) 
--------------------

* icingacli trapdirector status db

