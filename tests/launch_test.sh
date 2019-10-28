#!/bin/bash

echo "Launching tests for $DB";

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f "$(dirname "$0")")")"}
PHP_BIN=$(which php);

cd $MODULE_HOME

sed -i -e 's/.*For debug.*/$Trap->setLogging(4,"display");/' bin/trap_in.php

echo "UDP: [127.0.0.1]:56748->[127.0.0.1]:162
UDP: [127.0.0.1]:56748->[127.0.0.1]:162
.1.3.6.1.2.1.1.3.0 : 0:0:00:00.00
.1.3.6.1.6.3.1.1.4.1.0 : .1.3.6.1.6.3.1.1.5.3
.1.3.6.1.6.3.18.1.3.0 : 127.0.0.1
.1.3.6.1.6.3.18.1.4.0 : \"public\"
.1.3.6.1.6.3.1.1.4.3.0 : .1.3.6.1.6.3.1.1.5.1
" | $PHP_BIN bin/trap_in.php


echo "END---";
if [ "$DB" = mysql ]; then
	
	RET=$(mysql -u root travistest -e 'select * from received')
	
	echo "Returned : "
	echo $RET;
	
	
elif [ "$DB" = pgsql ]; then

	echo "TODO : implement"

fi

exit 0;