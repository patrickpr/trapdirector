Create rule
===============

The easy way to create rule is with an existing trap, but you also can create a rule before receiving any traps.

Create trap
---------------

Goto Handlers menu and click 'Add a trap handler'

![trap-1](img/create-rule-1.jpg)

You can choose between hosts or hostgroups :
- When selecting a host, the trap receiver will take it's source IP 
- When selecting a hostgroup, the trap receiver will take all IP of all host in this hostgroup (! performance issues (maybe) ! )

On both case, you must have a service attached to the host or hostgroup that accepts passive check (see ![Here](15-mib.md) about it)

Now first, (1) select host/group name (autocompletion provided)

Then select a service (2). The service list is from the IDO database.

![trap-2](img/create-rule-2.jpg)

Then you can either 

* add manually the trap OID  (3) (you won't be able to add objects automaticaly)
* or select the MIB (1) then the trap name in this mib (2).

In the second case, the "Add objects" will be enabled : this will add all objects that can be sent with the trap according to the MIB.

The "Revert OK" is the time after which the service will be reseted to OK. To do this, you have to set up a specific service on the icinga host (DOC TODO).

Automatic reset can also be done with icinga freshness check / active checks see ![Here](15-mib.md) about it).

![trap-3](img/create-rule-3.jpg)


Objects, rule & display
---------------

All of it is described ![Here](05-traps.md)


Then click create : rule is created and applied directly.



back to user guide : ![user guide](02-userguide.md)
