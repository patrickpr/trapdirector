CLI commands
=============


Delete old traps
-----------------

Using the default retention period: `icingacli trapdirector traps rotate`

Or specifying the retention period: `icingacli trapdirector traps rotate --days <num_days>`

Update MIB database with MIB files
-----------------------------------

`icingacli trapdirector mib update`

To run in the background, set a pid file: `icingacli trapdirector mib update --pid /path/to/pid.file`

To add verbose output, use "--verb": `icingacli trapdirector mib update --verb`

Get database status (number of traps/rules/objects) 
--------------------
`icingacli trapdirector status db`
