# trapdirector
Icingaweb2 module for receiving and handling SNMP traps

[![Build Status](https://travis-ci.org/patrickpr/trapdirector.svg?branch=master)](https://travis-ci.org/patrickpr/trapdirector) [![Codacy Badge](https://api.codacy.com/project/badge/Grade/cc87e39440bc434bb5724bece6b5fcbc)](https://www.codacy.com/manual/patrick_34/trapdirector?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=patrickpr/trapdirector&amp;utm_campaign=Badge_Grade) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/patrickpr/trapdirector/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/patrickpr/trapdirector/?branch=master) [![PSR-2 Style](https://github.styleci.io/repos/164436083/shield)](https://github.styleci.io/repos/164436083)

[![Gitter](https://badges.gitter.im/trapdirector/community.svg)](https://gitter.im/trapdirector/community?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge) 

Features: 

-  Receive and handle traps using only the net-snmp trap daemon.
-  Update Icinga2 services based on rules for a host or hostgroup. The trap data is used to update the service status.
-  See all traps received by the system.
-  Write your own evaluation function in PHP for specific trap OIDs.
-  Decode OIDs to human readable names, including ability to add MIB files via the Icingaweb2 GUI.

Project status: [Stable release 1.0.4c](https://github.com/patrickpr/trapdirector/releases)

This module has been installed and tested on CentOS 7, Ubuntu 18.04 (Bionic) and some more.

In case of a problem or feature request, [open a case](https://github.com/patrickpr/trapdirector/issues/new/choose).

Help wanted: 

-  English is not my native language, so grammar & spelling corrections in the docs (and the module!) are VERY welcome. Make a pull request or issue, or just send me a message.
-  If you want to help on this project, pull request are welcome!
-  If anyone has some knowledge in Zend framework, I'll happily take your advice. 

Have a look at: 

-  [Installation](docs/01-install.md)

-  [User Guide](docs/02-userguide.md)
	-  [Create a rule from an existing trap](docs/05-traps.md)
	-  [Create a rule from scratch](docs/10-createrule.md)
	-  [MIB management](docs/15-mib.md)

-  [Trap receiver logic](docs/20-receiver-logic.md)
