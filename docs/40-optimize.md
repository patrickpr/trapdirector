Optimize trap reception
===============

!!!! Ongoing DOC !!!

snmptrapd
---------------

In :  /usr/lib/systemd/system/snmptrapd.service

Basic : 

	Environment=OPTIONS="-Lsd"

Better : 

	Environment=OPTIONS="-Lsd -n -t -On"
	
	-n : no hostame resolution 
	
	-t : disable trap to syslog
	
	-p : file for process id (useful?)
	
	-On : no oid translate
	
Temp snmpd file in memory : 

tmpfs /var/run/snmpd                     tmpfs defaults,size=128m 0 0

