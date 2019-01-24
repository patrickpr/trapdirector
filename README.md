# trapdirector
Icingaweb2 module for receiving and handling snmp traps

Projet features : 

- Receive and handle traps using only net-snmp trapd daemon
- See all traps received by the system
- Update icinga services based on rules : host or hostgroups and traps data updates service status.
- OID decode to human readable name, possible to add mib files via web.

Project status : Beta release

- Module has been installed and tested on CentOS 7 and Ubuntu 18.04 (Bionic)
- All base project feature are working on thoses systems.	 

Help wanted : 

- English is not my native language, so grammar & spelling corrections in the docs (and the module !) are VERY welcome : make a pull request or issue or just send me a message
- If you want to help on this project pull request are welcome ! As it's still under heavy developpement, please open an issue before doing anything I could be doing right now. Have a look at the project tab to see what I'm currently doing.
- If anyone has some knowledge in Zend framework, I'll happily take advices 

Have a look at : 

* Installation doc : ![Installation](docs/01-install.md)

* User guide : ![Traps](docs/02-userguide.md)
	* Create rule from existing trapd : ![Here](docs/05-traps.md)
	* Create a rule from scratch : ![Here](docs/10-createrule.md)
	* Mib management : ![Here](docs/15-mib.md)

* Trap receiver logic : ![Basic schema](docs/20-receiver-logic.md)

