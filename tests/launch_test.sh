#!/bin/bash

# set -ex

function sqlExec()
{
   if [ "$DB" = mysql ]; then
	 RET=$(mysql -u root travistest -ss -e "$1" ) 
   elif [ "$DB" = pgsql ]; then
     RET=$(psql -U postgres travistest -q -t -c "$1")
   fi
   echo "$RET";
}

function fake_trap()
{
	message=$1
	ip=$2
	sqlfilter=$3
	sqlexists=$4;
	display=$5
	trapoid=$6
	trap="UDP: [${ip}]:56748->[127.0.0.1]:162\nUDP: [${ip}]:56748->[127.0.0.1]:162\n"
	trap="${trap}.1.3.6.1.6.3.1.1.4.1 ${trapoid}\n";
	shift 6
	while [ ! -z "$1" ]; do
	  trap="${trap}$1\n";
	  shift
	done
	echo -n "$message : ";
	echo -e "$trap" | $PHP_BIN ${MODULE_HOME}/bin/trap_in.php 2>/dev/null
	
	RET=$(sqlExec "select trap_oid,status from traps_received where trap_oid='${trapoid}' and ${sqlfilter};");
	#sqlExec "select * from traps_rules;";
	#RET=$(sqlExec "select trap_oid,status from traps_received where trap_oid='${trapoid}';");
	if [ -z "$RET" ] && [ $sqlexists -eq 1 ]; then
		echo "FAILED : no DB entry";
		GLOBAL_ERROR=1;
		return;
	fi
	if [ ! -z "$RET" ] && [ $sqlexists -eq 0 ]; then
		echo "FAILED : found entry : $RET";
		GLOBAL_ERROR=1;
		return;
	fi

	echo -n "DB OK,";
	#cat ${MODULE_HOME}/tests/icinga2.cmd 
	if [ ! -z "$display" ]; then 
		grep "$display" ${MODULE_HOME}/tests/icinga2.cmd  > /dev/null
		if [ $? -ne 0 ]; then
		   cat ${MODULE_HOME}/tests/icinga2.cmd
		   echo " FAILED finding $4 in command";
		   GLOBAL_ERROR=1;
		   return;
		fi
		
		echo " display OK";
	else
	   echo " display not tested";
	fi
	# Clean
	sqlExec "delete from traps_received where id > 0;";
	rm -f ${MODULE_HOME}/tests/icinga2.cmd;   
}

echo "Launching tests for $DB";

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f "$(dirname "$0")")")"}
PHP_BIN=$(which php);

GLOBAL_ERROR=0;

cd $MODULE_HOME

#### Set output to display and full log level
echo "Setting logging to max"
sqlExec "insert into traps_db_config (name,value) VALUES ('log_destination','display');"
sqlExec "insert into traps_db_config (name,value) VALUES ('log_level','5');"
sqlExec "insert into traps_db_config (name,value) VALUES ('db_remove_days ,50);"
#sqlExec "select * from traps_db_config;";

#if [ "$DB" = pgsql ]; then
#    PGPASSWORD="travistestpass"
#	psql -U travistestuser travistest -c "SELECT mib,name from traps_mib_cache WHERE oid='.1.3.6.31.1';"
#	psql -U travistestuser travistest -c "INSERT INTO traps_received (source_ip,source_port,destination_ip,destination_port,trap_oid,trap_name,trap_name_mib,status,source_name,date_received) VALUES ('127.0.0.1','56748','127.0.0.1','162','.1.3.6.31.1','dod.31.1','SNMPv2-SMI','done','Icinga host','2019-10-30 08:30:39') RETURNING id;";
#fi

# Setup rules
echo -n "Adding rules : "
RULES=$(cat ${MODULE_HOME}/tests/rules.sql)
echo $(sqlExec "${RULES}")

# Fake icingacmd as files
echo "Adding fake icingacmd"
echo -e "icingacmd = \"${MODULE_HOME}/tests/icinga2.cmd\"\n" >> ${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini


#			MessageIP	: IP : SQL filter : regexp display : trap oid : additionnal OIDs

fake_trap 'Simple rule match' 127.0.0.1 "status='done'" 1 '0;OK 1' .1.3.6.31.1 '.1.3.6.33.2 3'
echo "back to normal logging"
sqlExec "UPDATE traps_db_config set value=2 where name='log_level';"
#sqlExec "select * from traps_db_config;";

fake_trap 'Error in rule' 127.0.0.1 "status='error'" 1 '' .1.3.6.31.3 '.1.3.6.32.1 3'
fake_trap 'Missing oid' 127.0.0.1 "status='error'" 1 '' .1.3.6.31.2 '.1.3.6.33.1 3'
fake_trap 'Simple display' 127.0.0.1 "status='done'" 1 '1;OK 123' .1.3.6.31.2 '.1.3.6.32.1 4' '.1.3.6.32.2 123' 
fake_trap 'Simple text display' 127.0.0.1 "status='done'" 1 '1;OK Test' .1.3.6.31.2 '.1.3.6.32.1 4' '.1.3.6.32.2 "Test"' 
fake_trap 'Regexp oid' 127.0.0.1 "status='done'" 1 '1;OK Test' .1.3.6.255.5 '.1.3.6.32.1 3' '.1.3.6.32.1 "Test"' 

exit $GLOBAL_ERROR;

#( ip4 , 		trap_oid , 		host_name , 	host_group_name , 	action_match , action_nomatch ,	service_name ,		rule ,   display_nok , display)
#VALUES 
#( '127.0.0.1' ,	'.1.3.6.31.1',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	''	,	'KO 1', 			'OK 1'), 
#( '127.0.0.1' ,	'.1.3.6.31.2',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.32.1) = 3'	,	'KO _OID(.1.3.6.32.2)', 'OK _OID(.1.3.6.32.2)'), 
#( '127.0.0.1' ,	'.1.3.6.31.3',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.32.1) >< "test"'	,	'KO 1', 			'OK 1'), 
#( '127.0.0.1' ,	'.1.3.6.31.4',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.*.2) = 3'	,	'KO _OID(.1.3.6.*.2)', 'OK _OID(.1.3.6.**)'); 
#( '127.0.0.1' ,	'.1.3.6.*.5',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.32.1) = 3'	,	 'OK _OID(.1.3.6.32.1)');  
