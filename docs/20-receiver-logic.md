Receiver Logic
===============


General diagram
---------------


![diag](img/receiver-diagram.jpg)

Network trap 
---------------

A trap is sent to snmptrapd, it will 

* check snmp community or v3 user : snmptrapd will drop trap with wrong community/user
* send to defined handler as input :

UDP: [127.0.0.1]:33025->[127.0.0.1]:162
.1.3.6.1.2.1.1.3.0 0:0:00:00.00
.1.3.6.1.6.3.1.1.4.1.0 .1.3.6.1.6.3.1.1.5.4
.1.3.6.1.2.1.1.6.0 Just here
.1.3.6.1.2.1.2.2.1.7 1
.1.3.6.1.2.1.2.2.1.8 2

Here UDP from localhost to localhost
Following lines are traps objects, including the trap oid (here .1.3.6.1.6.3.1.1.5.4)

trap_in.php
---------------

1) read the trap from stdin

Extracts trap oid and stores objects (OID/value)

2) Get all rules which match ( sourceIP / trapoid )

Evaluate all rules one by one.
If a rule is empty, it will always be true : the "on match" action will by executed

If action is other than 'do nothing' then send passive service check to icingacmd
example : "[1547221876] PROCESS_SERVICE_CHECK_RESULT;Icinga host;LinkTrapStatus;2;Trap linkUp received at 0:0:00:00.00 from Just here" > /var/run/icinga2/cmd/icinga2.cmd

3) stores trap with status
- done : rule was found , whether or not it matches or action has been made
- unknown : no sourceIP/OID rule was found
- error : ....
- wating : trap was received but rules where not searched (for future use)