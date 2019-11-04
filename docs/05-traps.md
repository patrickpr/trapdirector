Traps
===============

A little about traps
---------------

Have a look here for ful info : http://www.net-snmp.org/wiki/

Shortly : a trap is send by an equipement to a trap server (here the icinga host), it contains : 

* Authentication : by a community - a word - in v1 & v2, or user/password in v3
* The trap OID : an OID (ex : .1.3.6.1.2.1.1) which defines the trap
* Trap objects : a list of OID with their values, so the system will provide additionnal information (ex : interface name).

Sending traps for testing
---------------

In the bin directory you have 3 bash files test_trap_v(1|2|3).sh which will send snmp v1, v2 & v3 traps to localhost.

Looking at traps received
---------------

Go to Traps -> received (or just Traps) to have a list of received traps

![trap-1](img/Trap-rule-1.jpg)

1) filter the display : it will show only traps which includes what you typed (will search in IP/OID/status etc..)
2) Click here to hide processed traps : those who has matched a rule (see Handlers). 

Click on a trap to have details

Columns : 
* Time the trap was received
* Source IP of trap
* Trap OID (or name if it could be resolved)
* Status : 
	* unknown : no rule was found for this trap
	* done : rule was found and evaluated
	* error : ...
	* waiting : trap was received, but no rule was applied for now
* Processing time : time taken by the script to process trap in seconds.


Trap details
---------------

Here you have all the information about the trap received :
* 1 : global information
* 2 : objects sent with trap and their values
* 3 : add a rule based on this trap

![trap-detail](img/trap-detail.jpg)

Click on 'add rule' : 


Adding a rule based on a received trap
---------------
	
The form is divided in three parts

1) Trap source  

![add-from-trap-1](img/add-from-trap-1.jpg)

* 1 : Icinga host whose service will be updated
* 2 : Service of host wich status will be updated. It must accept passive checks. Template is available in Status&Mibs.
* 3 : If you wan't your handler to be applied on a hostgroup intead of a host, click here and select hostgroup and service.

2) Trap definition

![add-from-trap-1_1](img/add-from-trap-1_1.jpg)

* 1 : MIB / trap name to select trap from (auto filled as trap has been recognised)
* 2 : trap OID
* 3 : if active, this button will add ALL objects that can be set with this trap (as descripbed in it's MIB) in the objects definition below.
* 4 : Time after which the service will be reverted to OK (you can do this here or in the service template).

3) Trap objects

This part lists all objects that will be used in the display and rules as $N$.

As you selected a trap, the objects sent with the trap are automaticaly added in here

![add-from-trap-2](img/add-from-trap-2.jpg)

* 1 : enter OID here to manually add objects
* 2 : Shortcuts $N$ that will be used in rules
* 3 : Value sent by the trap selected earlier
* 4 : Type of trap as described in MIB

If you hover on the type values, it will show you specific types meanings, for example : 

![add-from-trap-2_1](img/add-from-trap-2_1.jpg)

So here, the value "2" means interface is "down"

3) Display and rules

There you will configure : 
* The message displayed in the service when trap is received (and rule passes)
* The rule for actions, i.e. set service state

![add-from-trap-3](img/add-from-trap-3.jpg)

* 1 : Display

The display string will be sent with status to service. You can use all the $N$ defined above.

Here, the display will be for example : "Trap linkUP received for 3"
(if interface index in object is 3).

* 2 : Rule

Have a look [here](08-rules-evaluation.md) for how to make rules.

* 3 : actions depending on rule matches or not. You can choose any common status or 'nothing' to do nothing and 'ignore' to completly ignore trap (e.g. no database record)

Then click on save to save and activate your rule for next trap.


Updating a rule
---------------

On the Handler page, click on a rule to edit it.


Testing a rule
---------------

See "Rule testing" in : [Rules evaluation](08-rules-evaluation.md)


Go back to user guide : [Traps](02-userguide.md)

