#!/bin/bash

function sqlExec()
{
   if [ "$DB" = mysql ]; then
	 RET=$(mysql -u root travistest -ss -e "$1" ) 
   elif [ "$DB" = pgsql ]; then
     RET=$(psql -U postgres travistest -q -t -c "$1")
   fi
   return $RET;
}

function trap()
{
	message=$1
	ip=$2
	sqlfilter=$3
	display=$4
	trapoid=$5
	trap="UDP: [${ip}]:56748->[127.0.0.1]:162\nUDP: [${ip}]:56748->[127.0.0.1]:162\n"
	trap="${trap}.1.3.6.1.6.3.1.1.4.1 : ${trapoid}\n";
	shift 5
	while [ ! -z "$1" ]; do
	  trap="${trap}$1\n";
	  shift
	done
	echo -n "$message : ";
	echo -e "$trap" | $PHP_BIN ${MODULE_HOME}/bin/trap_in.php 2>/dev/null
	
	RET=$(sqlExec "select trap_oid,status from traps_received where trap_oid='${trapoid}' and ${sqlfilter};");
	if [ -z "$RET" ]; then
		echo "FAILED : no DB entry";
		exit 1;
	fi
	echo -n "DB OK,";
	grep $display ${MODULE_HOME}/tests/icinga2.cmd
	if [ $? -ne 0 ]; then
	   echo "FAILED finding $4 in command";
	   exit 1;
	fi
	
	echo "display OK";
	# Clean
	sqlExec "delete from traps_received where id > 0;";
	rm -f ${MODULE_HOME}/tests/icinga2.cmd;   
}

echo "Launching tests for $DB";

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f "$(dirname "$0")")")"}
PHP_BIN=$(which php);

cd $MODULE_HOME

#### Set output to display and full log level
sqlExec "insert into traps_db_config (name,value) VALUES ('log_destination','display');"
sqlExec "insert into traps_db_config (name,value) VALUES ('log_level','5');"

# Setup rules
$RULES=$(cat ${MODULE_HOME}/tests/rules.sql)
sqlExec "${RULES}"

# Fake icingacmd as files

echo -e "icingacmd = \"${MODULE_HOME}/tests/icinga2.cmd\"\n" >> ${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini


#			MessageIP	: IP : SQL filter : regexp display : trap oid : additionnal OIDs

test_trap 'Simple rule match' 127.0.0.1 "status='done'" 'OK 1' .1.3.6.31.1 '.1.3.6.33.2 : 3'

#( 127.0.0.1 "status='done'" 'OK 1' .1.3.6.31.1 '.1.3.6.33.2 : 3' )
#( 127.0.0.1 "status='error'" 'OK 1' .1.3.6.31.3 '.1.3.6.33.2 : 3' )

#(25,NULL,NULL,'.1.3.6.1.4.1.2620.1.1.0.1',NULL,'test_trap','_OID(.1.3.6.1.4.1.2620.1.1.11) = 3',1,-2,'test_delete_service',0,NULL,'display',NULL,NULL,NULL,10,NULL,NULL,NULL)
#(27,'127.0.0.1','','.1.3.6.1.6.3.1.1.5.4.0','Icinga host',NULL,'( _OID(.1.3.6.1.2.1.1.6.*) =\"\") & ( _OID(.1.3.6.1.2.1.2.2.1.1) > 3 ) & ( _OID(.1.3.6.1.2.1.2.2.1.1) <6) & ( _OID(.1.3.6.1.2.1.2.2.1.8) != 1 )',2,0,'LinkTrapStatus',0,NULL,'Trap linkUp received','2019-08-26 15:48:52','2019-10-27 07:54:42','admin',11,0,NULL,0),
#(28,'192.168.56.101','','.1.3.6.1.2.1.17.0.2','trap_test',NULL,'',0,-1,'Ping_host',0,NULL,'','2019-09-24 20:44:46','2019-09-24 20:44:46','admin',0,0,NULL,0)
#(29,NULL,NULL,'.1.3.6.1.4.1.2620.1.3000.5.1.1',NULL,'test_trap','_OID(.1.3.6.1.4.1.2620.1.6.7.8.1.1.2) = 3',0,1,'NetBotz SNMP Traps',0,NULL,'_OID(.1.3.6.1.4.1.2620.1.6.7.8.1.1.3) is set','2019-10-25 19:39:49','2019-10-25 20:04:20','admin',0,0,NULL,0)
#(30,'127.0.0.1','','.1.3.6.1.6.3.1.1.5.3','Icinga host',NULL,'',1,0,'LinkTrapStatus2',0,NULL,'Trap linkDown received','2019-10-25 20:11:58','2019-10-25 20:11:58','admin',1,0,NULL,0)
#(31,'127.0.0.1','','.1.3.6.1.6.3.1.1.5.1','Icinga host',NULL,'( _OID(.1.3.6.1.2.1.1.6.0) = \"Just here\" )',1,0,'Ping_host',0,NULL,'Trap coldStart received','2019-10-26 14:28:11','2019-10-26 14:28:11','admin',8,0,NULL,0);

exit 0;













