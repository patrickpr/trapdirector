#!/bin/bash

echo "Installing dependencies for $DB / db version $DBVER";

set -ex

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f "$(dirname "$0")")")"}
#PHP_VERSION="$(php -r 'echo phpversion();')"
PHP_BIN=$(which php);
ICINGAWEB_VERSION=${ICINGAWEB_VERSION:=2.7.1}
ICINGAWEB_GITREF=${ICINGAWEB_GITREF:=}

PHPCS_VERSION=${PHPCS_VERSION:=3.3.2}

################### Setup fake icingaweb2 /etc director for config & db setup

cd "${MODULE_HOME}"

mkdir -p vendor/icinga_etc/modules/trapdirector
echo -e "[config]\n" >  vendor/icinga_etc/modules/trapdirector/config.ini
touch vendor/icinga_etc/resources.ini

# seting icinga_etc in files : 

#sudo bin/installer.sh -c perm -d ${MODULE_HOME} -a nobody -w ${MODULE_HOME}/vendor/icinga_etc
sed -i -r "s#/etc/icingaweb2#${MODULE_HOME}/vendor/icinga_etc#" bin/trap_in.php 
chmod 755 bin/*.sh

# install database

if [ "$DB" = mysql ]; then

	bin/installer.sh -c database  -b mysql -t travistest:127.0.0.1:3306:root: -u travistestuser -s travistestpass -w "${MODULE_HOME}/vendor/icinga_etc"

	sed -i -e 's/#PREFIX#/traps_/g' "${MODULE_HOME}/SQL/schema_v${DBVER}.sql"	
	mysql -u root travistest < "SQL/schema_v${DBVER}.sql"
	echo -e "database_prefix = \"traps_\"\n" >> "${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini"	
	
	
elif [ "$DB" = pgsql ]; then

	bin/installer.sh -c database  -b pgsql -t travistest:127.0.0.1:5432:postgres: -u travistestuser -s travistestpass -w "${MODULE_HOME}/vendor/icinga_etc"
	
	sed -i -e 's/#PREFIX#/traps_/g' "${MODULE_HOME}/SQL/schema_v${DBVER}.pgsql"
	psql -U postgres travistest < "${MODULE_HOME}/SQL/schema_v${DBVER}.pgsql"
	echo -e "database_prefix = \"traps_\"\n" >> "${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini"
	
else
    echo "Unknown database set in environment!" >&2
    env
    exit 1
fi

# Install fake icinga db (TODO : only mysql for now).

if [ "$DB" = mysql ]; then

	mysql -u root -e "create database icinga"	
	mysql -u root icinga < "${MODULE_HOME}/tests/icingaDB.sql"

elif [ "$DB" = pgsql ]; then
	mysql -u root -e "create database icinga"
	mysql -u root icinga < "${MODULE_HOME}/tests/icingaDB.sql"
fi
echo -e "IDOdatabase = \"icinga_ido\"\n" >> "${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini"
echo '
[icinga_ido]
type = "db"
db = "mysql"
host = "localhost"
dbname = "icinga"
username = "root"
password = ""
use_ssl = "0"
' >> "${MODULE_HOME}/vendor/icinga_etc/resources.ini"

# snmptranslate

snmpt=$(which snmptranslate);
if [ $? -ne 0 ] ; then echo "No snmp translate"; exit 1; fi
echo -e "snmptranslate = \"${snmpt}\"\n" >> "${MODULE_HOME}/vendor/icinga_etc/modules/trapdirector/config.ini"

# snmptrapd configuration

sudo bin/installer.sh -c snmpconf -i -p $PHP_BIN -d "${MODULE_HOME}"

cat /etc/snmp/snmptrapd.conf
sleep 1

sudo bin/installer.sh -c snmprun -i



############## IcingaWeb2 installation, copied from director module

cd "${MODULE_HOME}"

test -d vendor || mkdir vendor
cd vendor/

# icingaweb2
if [ -n "$ICINGAWEB_GITREF" ]; then
  icingaweb_path="icingaweb2"
  test ! -L "$icingaweb_path" || rm "$icingaweb_path"

  if [ ! -d "$icingaweb_path" ]; then
    git clone https://github.com/Icinga/icingaweb2.git "$icingaweb_path"
  fi

  (
    set -e
    cd "$icingaweb_path"
    git fetch -p
    git checkout -f "$ICINGAWEB_GITREF"
  )
else
  icingaweb_path="icingaweb2-${ICINGAWEB_VERSION}"
  if [ ! -e "${icingaweb_path}".tar.gz ]; then
    wget -O "${icingaweb_path}".tar.gz https://github.com/Icinga/icingaweb2/archive/v"${ICINGAWEB_VERSION}".tar.gz
  fi
  if [ ! -d "${icingaweb_path}" ]; then
    tar xf "${icingaweb_path}".tar.gz
  fi

  rm -f icingaweb2
  ln -svf "${icingaweb_path}" icingaweb2
fi
ln -svf "${icingaweb_path}"/library/Icinga Icinga
ln -svf "${icingaweb_path}"/library/vendor/Zend Zend


exit 0;
