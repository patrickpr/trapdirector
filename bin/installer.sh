#!/bin/bash

############ Icinga module trapdirector installer ##############
# 
# Setup all parameters for IcingaWeb2 module TrapDirector
# 
# run as root or user which can change /etc/snmp/snmptrapd.conf
#
###############################################################

####### Constants ###########
# Icinga
icinga='icinga2';
icingaEtc='';
icinga2APIconf="/conf.d/api-users.conf";
icinga2APIperms='[ "status", "objects/query/Host", "objects/query/Service" , "actions/process-check-result" ]'

# TrapDirector

trapDirName='trapdirector'

# Web server
apacheUser='apache'

# IcingaWeb2
icingawebEtc='/etc/icingaweb2';
icingawebModule="/usr/share/icingaweb2/modules/${trapDirName}";

icingawebResources="resources.ini";

function usage()
{
   echo "$0 -c <command> [ -w <icingaweb2 etc dir> -p <php binary> -d <trapdirector directory>";
   echo -e "\t -a <apache user>"
   echo -e "\t -b <mysql|pgsql> -u <SQL_user> [ -s <SQL Password> ] ]";
   echo -e "\t [ -t <DBName:SQL IP:SQL port:SQL admin:SQL admin password> ] ";
   echo -e "\t -i";
   echo "command (you can have multiple -c ): 
   api      : setup api & user
   snmpconf : setup snmptrapd.conf
   snmprun  : setup snmptrapd startup options
   database : create database & db user
   perm     : set directory/file permissions and paths in files
   all      : all commands in sequence
   
   -i : disable interactive mode.
   "
   exit 0;
}

function question()
{
	echo -n "$1 [y/N]"; 
	read add;
	if [ "$add" = "y" ] ||  [ "$add" = "Y" ]; then 
		return 1;
	else 
		return 0;
	fi
}

function add_to_config()
{ # Adds configuration element ( $1 = $2 ) to config.ini.
  # Replace if exists, create file and [config] if necessary
  param=$1;val=$2;
  if [ ! -z "$PicingawbEtc" ] ; then etcDir=$PicingawbEtc ;
  else etcDir=$icingawebEtc; fi
  iniFile="${etcDir}/modules/${trapDirName}/config.ini";
  if [ ! -f $iniFile ]; then
      # No config.ini file, must not create as permissions can be specific
      echo "config.ini file not  found";
	  #echo -e "[config]\n${param} = \"${val}\"\n" >  $iniFile
	  return 1;
  fi
  grep -E "^${param} *=" $iniFile > /dev/null 2>&1
  if [ $? -eq 0 ]; then
	sed -i -r "s@^${param} *=.*@${param} = \"${val}\"@" $iniFile;
	return 0;
  fi
  echo -e "\n${param} = \"${val}\"\n" >>  $iniFile;
  
}

function get_icinga_etc()
{
	icinga2 -V > /dev/null 2>&1
	if [ $? -ne 0 ]; then echo ""; return 1; fi
	icingaEtc=$(icinga2 -V | grep "Config directory:" | awk -F ': ' '{print $2}')
	echo "$icingaEtc";
	return 0;
}

function check_api() {
	# Check Icinga2 available
	echo -e "\n==================================";
	echo "API Check"
	
	echo -n "icinga2 binary & path";
	
	icingaEtc=$(get_icinga_etc);
	if [ $? -ne 0 ]; then echo ": NOT FOUND"; return 1; fi
	 
	echo -n "(${icingaEtc})"
	echo -n " , api ";
	api=$(icinga2 feature list  2>&1 | grep -E "Enabled features:.* api ");
	if [ $? -ne 0 ]; then 
		echo ": API not enabled"; 
		question "Do you want to enable API ? "
		if [ $? -eq 0 ]; then return 1; fi
		icinga2 api setup
		echo
		question "Was installation OK ?"
		if [ $? -eq 0 ]; then return 1; fi
	fi
	echo ": enabled";
	
	echo -n 'api users'
	if [ ! -f ${icingaEtc}${icinga2APIconf} ] ; then echo -e "\n Cannot read api users file ${icingaEtc}${icinga2APIconf}"; return 1; fi;
	apiusers=$(grep "object ApiUser" "${icingaEtc}${icinga2APIconf}" | sed -r 's/.*object +ApiUser +"(.*)".*/\1/');
	echo " found : "; 
	echo -e "$apiusers \n";
	
	question "Add a user";
	if [ $? -eq 0 ]; then return 0; fi
	 
	echo -n "Username : "; read apiUser;
	echo -n "Password : "; read apiPass;
	echo -e "\nobject ApiUser \"${apiUser}\" {\n password = \"${apiPass}\"\n permissions = ${icinga2APIperms}\n}" >> ${icingaEtc}${icinga2APIconf}

	question "Reload icinga (need systemctl)";
	if [ $? -eq 1 ]; then 
		systemctl reload icinga2.service; 
	fi
	
	echo "Adding API user in trapdirector configuration"
	if [ -z  "$PicingawbEtc" ]; then
	  echo -n "IcingaWeb2 etc dir [${icingawebEtc}] : ";
	  read inputEtc;
	  if [ "$inputEtc" = "" ] ; then 
		inputEtc=$icingawebEtc; 
	  else 
		PicingawbEtc=$inputEtc;
	  fi;
	else
		inputEtc=$PicingawbEtc
	fi;  	

	add_to_config "icingaAPI_host" "127.0.0.1"
	add_to_config "icingaAPI_port" "5665"
	add_to_config "icingaAPI_user" "${apiUser}"
	add_to_config "icingaAPI_password" "${apiPass}"
	
	return 0;

}

function check_snmptrapd() {
	# TODO : snmp v3 user
	trapdConfig='/etc/snmp/snmptrapd.conf';
	if [ -z "$1" ]; then phpBinary='';
	else phpBinary=$1; fi 
	if [ -z "$2" ]; then moduleDir='';
	else moduleDir=$2; fi
	
	echo -e "\n==================================";
	echo "Snmptrapd config check"
	echo
	
	while [ ! -f $trapdConfig ]; do 
	   echo "Config file not found (${trapdConfig})"
	   echo 'snmptrapd.conf files found in /etc : '
	   find /etc -name "snmptrapd.conf"
	   
	   if [ $Pinter -eq 0 ]; then exit 1; fi
	   echo -n -e  "\nEnter snmptrapd.conf file with path or press Enter\nto skip this :"
	   read trapdConfig;
	   if [ "$trapdConfig" == "" ]; then return 0; fi;
	done
	echo "Using file : $trapdConfig";
	
	echo "Searching for traphandles in $trapdConfig";
	grep -E "^ *traphandle *default" /etc/snmp/snmptrapd.conf | sed -r 's/^ *traphandle *default *//'
	echo
	if [ $Pinter -eq 0 ]; then
		echo "traphandle default $phpBinary $moduleDir/bin/trap_in.php" >> $trapdConfig;
		echo "authCommunity   log,execute,net public" >> $trapdConfig;
		systemctl restart snmptrapd.service
		echo "Handler added with community, service restarted";
		return 0;
	fi
	question "Add a traphandle"
	if [ $? -eq 1 ]; then
		if [ ! -z "$phpBinary" ] && [ ! -z "$moduleDir" ]; then 
		   echo "traphandle default $phpBinary $moduleDir/bin/trap_in.php" >> $trapdConfig;
		   echo "Added traphandle to $phpBinary $moduleDir/bin/trap_in.php in $trapdConfig";
		else
			echo
			echo "Enter : <php binary> <module_path>/bin/trap_in.php"
			read trapHandler;
			echo "traphandle default $trapHandler" >> $trapdConfig;
		fi
		
		echo "Searching for community in $trapdConfig";
		grep -E "^ *authCommunity *" /etc/snmp/snmptrapd.conf | sed -r 's/^ *authCommunity *//'
		echo
		question "Add a v1/v2c community"
		if [ $? -eq 0 ]; then return 0; fi
		
		echo
		echo -n "Enter community : "
		read community;
		echo "authCommunity   log,execute,net $community" >> $trapdConfig;
		
		question "Restart snmptrapd (need systemctl)";
		if [ $? -eq 0 ]; then return 0; fi
		systemctl restart snmptrapd.service
	fi
	return 0;
}

function check_snmptrapd_run() {
	# Check start options of snmptrapd
	echo -e "\n==================================";
	echo "Snmptrapd starting options"
	echo
	
	# Check port 
	port=$(ss -plun | grep ':162 ' | head -1 2>&1)
	if [ $? -ne 0 ]; then
		echo 'No process is listening on port 162, trying to start it.'
		ret=$(systemctl start snmptrapd)
		if [ $? -ne 0 ]; then
			echo 'cannot start : maybe you need to install snmptrapd'
			return 0;
		fi
		echo "Returned : $ret"
		echo "Waiting 5 sec to come up"
		sleep 5
		port=$(ss -plun | grep ':162 ' | head -1 2>&1)
		if [ $? -ne 0 ]; then
			echo 'snmptrapd started but not listening to udp/162.... Exiting...'
			return 0;
		fi
	fi
	
	# get pid 
	snmpPid=$( echo $port | sed -r 's/.*pid=([0-9]+).*/\1/' );
	snmpName=$( echo $port | grep -oP '(?<=")[\w]+' );
	echo "Found process $snmpName with pid $snmpPid"

	# get options
	options=$(ps  --pid $snmpPid  -f | grep ${snmpName} | sed -r "s/.*${snmpName}//")
	
	echo "Snmptrapd options are : $options";
	optionErr=0;
	optionAdd='';
	if [[ ! "$options" =~ -n ]] ; then echo "No '-n' option"; optionErr=1; optionAdd="$optionAdd -n"; fi
	regexp='-O[^ ]*n';
	if [[ ! "$options" =~ $regexp ]] ; then echo "No '-On' option"; optionErr=1; optionAdd="$optionAdd -On"; fi
	regexp='-O[^ ]*e';
	if [[ ! "$options" =~ $regexp ]] ; then echo "No '-Oe' option"; optionErr=1; optionAdd="$optionAdd -Oe"; fi
	
	if [ $optionErr -eq 0 ]; then return 0; fi
	
	if [ $Pinter -eq 1 ]; then
		# Change options
		question "update snmptrapd startup options"
		if [ $? -eq 0 ] ; then return 0; fi
	fi
	
	snmpstart='/etc/default/snmptrapd'
	if [ ! -f $snmpstart ] ; then
		snmpstart='/etc/sysconfig/snmptrapd'
		if [ ! -f $snmpstart ] ; then
			echo 'cannot find snmptrapd startup config in /etc/default or /etc/sysconfig';
			return 1;
		fi
	fi
	
	sed -r -i "s/^ *(OPTIONS *= *\")(.*)/\1${optionAdd} \2/" $snmpstart
	
	if [ $Pinter -eq 1 ]; then
		question "Restart snmptrapd (need systemctl)";
		if [ $? -eq 0 ]; then return 0; fi
	fi
	
	systemctl restart snmptrapd
	
	return 0;
}

function add_schema_mysql(){

  echo -e "\n==================================";
  echo "Adding trap schema in database";
  echo
  unset dbName dbFrom;
  dbAuto=0;
  if [ ! -z "$Psqlconn" ]; then
	OIFS=$IFS; IFS=':';
	arr=($Psqlconn);
	IFS=$OIFS;
	dbName=${arr[0]};
	dbFrom='%';
	dbAuto=1;
	if [ ${#arr[*]} -eq 5 ]; then 
		sql_conn=" -h ${arr[1]} -P ${arr[2]} -u ${arr[3]} -p${arr[4]} ";
	else if [ ${#arr[*]} -eq 4 ]; then
			sql_conn=" -h ${arr[1]} -P ${arr[2]} -u ${arr[3]} ";
		else
		   echo "Error in sql params : $Psqlconn"
		   usage
		fi
	fi
	dbHost=${arr[1]};
	dbPort=${arr[2]};
  else
  
	  echo "Script needs a user which can create schema, user, and assign permissions";
	  echo
	  
	  echo -n "Enter database host [set to 127.0.0.1 if you don't enter anything] : "
	  read dbHost;
	  if [ "$dbHost" == "" ]; then dbHost="127.0.0.1"; fi;
	  
	  echo -n "Enter database port [3306] : "
	  read dbPort;
	  if [ "$dbPort" == "" ]; then dbPort="3306"; fi;
	  
	  echo -n "Enter username : ";
	  read dbUser;
	  
	  echo -n "Enter password (or press enter if no password is required) : ";
	  read -s dbPass;
	  if [ ! "$dbPass" == "" ]; then dbPass=" -p${dbPass} " ;fi;
	  sql_conn=" -h $dbHost -P $dbPort -u $dbUser $dbPass "
  fi
  
  echo
  echo -n "Connecting..."

  dbRet=$(mysql $sql_conn -e 'show databases;')
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  
  echo 'params OK'
  
  if [ $dbAuto -eq 0 ]; then 
	  echo -n "Enter new database name (or enter to exit): ";
	  read dbName;
	  if [ "$dbName" == "" ] ; then return 1; fi;
  fi
  dbRet=$(mysql $sql_conn  -e "create database $dbName;");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  
  if [ -z "$PsqlUser" ]; then 
	  echo -n "Enter database user for db $dbName (or enter to exit):"
	  read dbUser2;
	  if [ "$dbUser2" == "" ] ; then return 1; fi;
  else
	dbUser2=$PsqlUser;
  fi
  
  if [ -z "$PsqlUser" ]; then   
	  echo -n "Enter database new password for $dbUser2 [] :"
	  read dbPass2;
  else
	dbPass2=$PsqlPass;
  fi 
  
  if [ $dbAuto -eq 0 ]; then 
	  echo -n "Allow new user to connect only from [localhost] : "
	  read dbFrom;  
	  if [ "$dbFrom" == "" ] ; then dbFrom='localhost'; fi;
  fi
  sqlCommand="grant usage on *.* to '${dbUser2}'@'${dbFrom}' identified by '${dbPass2}'";
  echo "Adding :  $sqlCommand";
  dbRet=$(mysql $sql_conn  -e "$sqlCommand");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   echo "Errors in setting user, deleting database $dbName";
	   mysql $sql_conn -e "drop database $dbName;"
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters and start again"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi

  sqlCommand="grant all privileges on $dbName.* to '${dbUser2}'@'${dbFrom}';";
  echo "Adding :  $sqlCommand";
  dbRet=$(mysql $sql_conn  -e "$sqlCommand");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   echo "Errors in setting user, deleting database $dbName";
	   mysql $sql_conn -e "REVOKE ALL PRIVILEGES ON *.* FROM '${dbUser2}'@'${dbFrom}';";
	   mysql $sql_conn -e "drop database $dbName;"
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters and start again"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi

  echo "Database parameters set"
  echo
  if [ $dbAuto -eq 0 ]; then 
	question "Do you want to add this database as a resource in IcingaWeb2"
	if [ $? -eq 0 ]; then return 0; fi
  fi

  if [ ! -f ${icingawebEtc}/${icingawebResources} ]; then 
     echo "Cannot find  icingaWeb2 resource file : ${icingawebEtc}/${icingawebResources}";
	 return 1;
  fi
  echo "
[${dbName}_db]
type = \"db\"
db = \"mysql\"
host = \"${dbHost}\"
port = \"${dbPort}\"
dbname = \"${dbName}\"
username = \"${dbUser2}\"
password = \"${dbPass2}\"
use_ssl = \"0\"
" >> ${icingawebEtc}/${icingawebResources};
  
  echo "Added ${dbName}_db as icinga resource !";
  echo "Adding this to module configuration";
  add_to_config "database" "${dbName}_db"
  add_to_config "database_prefix" "traps_"
  echo 'Done !';
  
  
}

function add_schema_pgsql(){

  echo -e "\n==================================";
  echo "Adding trap schema in database";
  echo
  unset dbName dbFrom;
  dbAuto=0;
  if [ ! -z "$Psqlconn" ]; then
	OIFS=$IFS; IFS=':';
	arr=($Psqlconn);
	IFS=$OIFS;
	dbName=${arr[0]};
	dbFrom='%';
	dbAuto=1;
	if [ ${#arr[*]} -eq 5 ]; then
		PGPASSWORD="${arr[4]}";
		export PGPASSWORD;
		sql_conn="-h ${arr[1]} -p ${arr[2]} -U ${arr[3]} -w "
	else if [ ${#arr[*]} -eq 4 ]; then
			sql_conn="-h ${arr[1]} -p ${arr[2]} -U ${arr[3]} -w ";
		else
		   echo "Error in sql params : $Psqlconn"
		   usage
		fi
	fi
	dbHost=${arr[1]};
	dbPort=${arr[2]};
  else
  
	  echo "Script needs a user which can create schema, user, and assign permissions";
	  echo
	  
	  echo -n "Enter database host [set to 127.0.0.1 if you don't enter anything] : "
	  read dbHost;
	  if [ "$dbHost" == "" ]; then dbHost="127.0.0.1"; fi;
	  
	  echo -n "Enter database port [5432] : "
	  read dbPort;
	  if [ "$dbPort" == "" ]; then dbPort="5432"; fi;
	  
	  echo -n "Enter username : ";
	  read dbUser;
	  
	  echo -n "Enter password (or press enter if no password is required) : ";
	  read -s dbPass;
	  sql_conn="";
	  if [ ! "$dbPass" == "" ]; then PGPASSWORD="$dbPass" ; export PGPASSWORD ; fi;
	  sql_conn="-h $dbHost -p $dbPort -U $dbUser"
  fi
  
  echo
  echo -n "Connecting..."

  dbRet=$(psql $sql_conn -d  postgres -c 'select schema_name from information_schema.schemata;')
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  
  echo 'params OK'

  if [ $dbAuto -eq 0 ]; then 
	  echo -n "Enter new database name (or enter to exit): ";
	  read dbName;
	  if [ "$dbName" == "" ] ; then return 1; fi;
  fi

  if [ -z "$PsqlUser" ]; then 
	  echo -n "Enter database user for db $dbName (or enter to exit):"
	  read dbUser2;
	  if [ "$dbUser2" == "" ] ; then return 1; fi;
  else
	dbUser2=$PsqlUser;
  fi
  
  if [ -z "$PsqlUser" ]; then   
	  echo -n "Enter database new password for $dbUser2 [] :"
	  read dbPass2;
  else
	dbPass2=$PsqlPass;
  fi 
  
  if [ $dbAuto -eq 0 ]; then 
	  echo -n "Allow new user to connect only from [localhost] : "
	  read dbFrom;  
	  if [ "$dbFrom" == "" ] ; then dbFrom='localhost'; fi;
  fi
  sqlCommand="CREATE USER ${dbUser2} WITH PASSWORD '${dbPass2}';"
  echo "Adding :  $sqlCommand";
  dbRet=$(psql $sql_conn -d  postgres -c "$sqlCommand");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   echo "Errors in setting user, deleting database $dbName";
	   psql $sql_conn -d  postgres -c "drop database $dbName;"
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters and start again"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  
  dbRet=$(psql $sql_conn -d  postgres -c "CREATE DATABASE ${dbName} WITH ENCODING 'UTF8' OWNER ${dbUser2};");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi  
  
  sqlCommand="GRANT ALL PRIVILEGES ON DATABASE ${dbName} TO ${dbUser2};"
  echo -n "setting :  $sqlCommand : ";
  dbRet=$(psql $sql_conn -d ${dbName} -c "$sqlCommand");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   echo "Errors in setting user, deleting database $dbName";
	   psql $sql_conn -d  postgres -c "REVOKE ALL ON DATABASE ${dbName} FROM ${dbUser2};"
	   psql $sql_conn -d  postgres -c "DROP user ${dbUser2};";
	   psql $sql_conn -d  postgres -c "drop database ${dbName};"
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters and start again"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  echo $dbRet;
  #sqlCommand="GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO  ${dbUser2};"  
  sqlCommand="ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON TABLES TO ${dbUser2};"
  sqlCommand="${sqlCommand} ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL on sequences TO ${dbUser2};"
  echo -n "setting :  $sqlCommand : ";
  dbRet=$(psql $sql_conn -d  ${dbName} -c "$sqlCommand");
  if [ $? -ne 0 ]; then 
	   # Error is shown with stderr
	   echo "Errors in setting user, deleting database $dbName";
	   psql $sql_conn -d  postgres -c "REVOKE ALL ON DATABASE ${dbName} FROM ${dbUser2};"
	   psql $sql_conn -d  postgres -c "DROP user ${dbUser2};";
	   psql $sql_conn -d  postgres -c "drop database ${dbName};"
	   if [ $dbAuto -eq 1 ]; then exit 1; fi
	   question "Change parameters and start again"
	   if [ $? -eq 0 ]; then return 1; fi
	   add_schema
	   return 0;
  fi
  echo $dbRet;  
  echo "Database parameters set"
  echo
  if [ $dbAuto -eq 0 ]; then 
	question "Do you want to add this database as a resource in IcingaWeb2"
	if [ $? -eq 0 ]; then return 0; fi
  fi
 
  if [ ! -f ${icingawebEtc}/${icingawebResources} ]; then 
     echo "Cannot find  icingaWeb2 resource file : ${icingawebEtc}/${icingawebResources}";
	 return 1;
  fi
  echo "
[${dbName}_db]
type = \"db\"
db = \"pgsql\"
host = \"${dbHost}\"
port = \"${dbPort}\"
dbname = \"${dbName}\"
username = \"${dbUser2}\"
password = \"${dbPass2}\"
use_ssl = \"0\"
" >> ${icingawebEtc}/${icingawebResources};
  
  echo "Added ${dbName}_db as icinga resource !";
  echo "Adding this to module configuration";
  add_to_config "database" "${dbName}_db"
  add_to_config "database_prefix" "traps_"
  echo 'Done !';
  
	
}

function set_perms(){
# Set permissions on files
  echo -e "\n==================================";
  echo -e "File permissions setup\n"

  if [ -z "$PmoduleDir" ]; then 
	  echo -n "Module directory [$icingawebModule] : ";
	  read inputDir;
	  if [ "$inputDir" == "" ] ; then inputDir=$icingawebModule ; fi;
	  if [ "$inputUser" == "" ] ; then inputUser=$apacheUser ; fi;
  else
	echo "Using module directory : ${PmoduleDir}";
	inputDir=${PmoduleDir}
  fi
  
  if [ -z "$PApacheUser" ]; then 
	  echo -n "Web server user [$apacheUser] : ";
	  read inputUser;
	  if [ "$inputUser" == "" ] ; then inputUser=$apacheUser ; fi;
  else
	echo "Using web server user : ${PApacheUser}";
	inputUser=${PApacheUser}
  fi
  
  listFiles=$(find $inputDir);
  
  for modFile in $listFiles; do  
	if [ -f $modFile ] ; then 
		chmod 644 $modFile
		chown root:root $modFile
	fi
	if [ -d $modFile ] ; then 
		chmod 755 $modFile
		chown root:root $modFile
	fi	
  done
  
  # Mib file directory must be writable by web server user
  chown -R $inputUser $inputDir/mibs
  
  # bin utilities
  chmod 755 $inputDir/bin/*.sh
  
  # change icingaweb2 etc if needed
  if [ -z  "$PicingawbEtc" ]; then
	  echo -n "IcingaWeb2 etc dir [$icingawebEtc] : ";
	  read inputEtc;
	  if [ "$inputEtc" = "" ] ; then 
		inputEtc=$icingawebEtc; 
	  else 
		PicingawbEtc=$inputEtc;
	  fi;
  else
	inputEtc=$PicingawbEtc
  fi;
  #echo "$inputEtc / $icingawebEtc"
  if [ "$inputEtc" != "$icingawebEtc" ] ; then
	 echo "Changing non default icingaweb etc in files"
	 sed -i -r "s#${icingawebEtc}#${inputEtc}#" trap_in.php
  fi
  
  echo -e "\nDone setting permissions";
  echo -e "\n==================================";
}


unset commands PicingawbEtc PphpBin PmoduleDir PsqlUser PsqlPass PApacheUser Pdbtype
unset Psqlconn
commands='';
Pinter=1;

while getopts ":c:w:p:d:u:s:a:b:t:i" o; do
	case "${o}" in
		c)
			commands="$commands ${OPTARG}"
			;;
		w)
			PicingawbEtc=${OPTARG}
			;;
		p)
			PphpBin=${OPTARG}
			;;
		d)
			PmoduleDir=${OPTARG}
			;;
		u)
			PsqlUser=${OPTARG}
			;;
		s)
			PsqlPass=${OPTARG}
			;;
		a)
			PApacheUser=${OPTARG}
			;;
		b)
			Pdbtype=${OPTARG}
			;;
		t)
			Psqlconn=${OPTARG}
			;;
		i)
			Pinter=0;
			;;
		*)
			echo "unknown option ${OPTARG}"
			usage
			;;
	esac
done
shift $((OPTIND-1))




if [ -z "${commands}" ] ; then
	echo "Must set a least one command"
    usage
fi

if [ ! -z "$PicingawbEtc" ] ; then # if icingaweb2 etc set then write it to config.ini
	echo "Adding to icingaweb2_etc";
	add_to_config "icingaweb2_etc" "${PicingawbEtc}"
	icingawebEtc=${PicingawbEtc};
fi

if [[ $commands =~ api ]] || [[ $commands =~ all ]]; then
	check_api
fi

if [[ $commands =~ snmpconf ]] || [[ $commands =~ all ]]; then
	check_snmptrapd $PphpBin $PmoduleDir
fi

if [[ $commands =~ snmprun ]] || [[ $commands =~ all ]]; then
	check_snmptrapd_run
fi

if [[ $commands =~ database ]] || [[ $commands =~ all ]]; then
	if [[ -z "$Pdbtype" ]] || [[ $Pdbtype =~ mysql ]]; then
		add_schema_mysql 
	else if [[ $Pdbtype =~ pgsql ]]; then
		add_schema_pgsql
	else
		echo "Unknown database type : $Pdbtype"
		usage
	fi
	fi
fi

if [[ $commands =~ perm ]] || [[ $commands =~ all ]]; then
	set_perms
fi

exit 0;
