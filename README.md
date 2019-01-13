# trapdirector
Icingaweb2 module for receiving and handling snmp traps

Projet features : 

- Receive and handle traps using only net-snmp trapd daemon
- See all traps received by the system
- Update icinga services based on rules : host or hostgroups and traps data updates service status.
- OID decode to text, add mib files.

Project status : Testing code before first beta release

Have a look at : 
* installation doc : ![Installation](docs/01-install.md)

* Ongoing doc : ![Traps](docs/02-traps.md)

* Trap receiver logic : ![Basic schema](docs/20-receiver-logic.md)

