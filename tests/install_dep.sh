#!/bin/bash

echo "Installing dependencies for $DB";

## IcingaWeb2 installation, copied from director module
set -ex

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f "$(dirname "$0")")")"}
PHP_VERSION="$(php -r 'echo phpversion();')"

ICINGAWEB_VERSION=${ICINGAWEB_VERSION:=2.7.1}
ICINGAWEB_GITREF=${ICINGAWEB_GITREF:=}

PHPCS_VERSION=${PHPCS_VERSION:=3.3.2}

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
