Installation
===============

Requirements
---------------

* Icingaweb2 and php7
* net-snmp for snmptrapd
* net-snmp-utils for snmptranslate
* mysql/mariadb or postgresql database 

Better with
---------------

* Director : to set up services, templates (only one template for now).


Install files
---------------

1. Download latest release and unzip in a temporary directory.
2. Move the 'trapdirector' directory to the /usr/share/icingaweb2/modules directory (or modules directory if not standard installation)
3. trapdirector/mibs/ must be writable by icinga web user to upload mibs from web GUI


Non standard IcingaWeb2 installation
------------------------------------

If IcingaWeb2 configuration directory is not "/etc/icingaweb2", you must set up manually the path in the file : 
[...]/modules/trapdirector/bin/trap_in.php 
```
[....]
// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
[....]
```

Create Database
-----------------

* mysql / mariadb

Set up a new (or use existing) database in icingaweb 2 :
Note : following commands must be run as root or database admin.

Create database :

`mysql -u root -e "create database <database name>;"`

Create user and assign rights :

```
mysql -u root -e "grant usage on *.* to <user>@localhost identified by '<password>';"
mysql -u root -e "grant all privileges on <database name>.* to <user>@localhost ;"
```

* postgresql

Create user if needed : 

````
su - postgres
createuser --interactive --pwprompt
````

or 

````
createuser -U <admin login> -W <new user> -P 
````

Create database if needed : 

````
createdb -O <database user> <database name>
````


Create database on Icingaweb2 in /icingaweb2/config/resource (direct link on trapdirector configuration page).


Activate module
---------------

Log in to icingaweb2 go to Configuration -> modules  and activate the trapdirector modules

Go to the configuration tab, it should look like this : 

![install-1](img/install-1.jpg)

The options are

* Database : the DB where traps will be stored
* Prefix : the prefix for all database tables
* IDO Database : the IDO database set up with IcingaWeb2
* Icingaweb2 config dir : configuration directory in case of uncommon installation of icingaweb2
* snmptranslate binary : default should be OK, test in in mib&status page.
* Path for mibs : local mibs (default /usr/share/icingaweb2/modules/trapdirector/mibs). You can add directories with ':' separators : the mib upload will then be in the first one 
* icingacmd path : default should be OK

Create schema
---------------

After setting the database (1) and ido database (2), refresh the config page : 

![install-2](img/install-2.jpg)

Click on (3) to create schema

![install-3](img/install-3.jpg)

Then go back to module configuration, database should be OK :

![install-4](img/install-4.jpg)

Setup API user
---------------

API user allows the module to use Icinga2 API to submit check results, in case icinga2 is not on the same server than the module.

To setup a user in icinga2, edit the "/etc/icinga2/conf.d/api-users.conf" file and add a user or use existing one : 

```
object ApiUser "trapdirector" {
  password = "trapdirector"
  permissions = [ "status", "objects/query/Host", "objects/query/Service" , "actions/process-check-result" ]
}
```
Note (BETA VERSION) : Permissions will maybe change in near future (but the module will check for this, see below).

Then reload icinga2 (systemctl reload icinga2)

Then setup in the module configuration.

Note : If no IP/hostname is set, API usage is disabled : 

![install-10](img/install-10.jpg)

Set : 
* icinga2 host IP : IP or hostname of icinga2 server
* Port : by default 5665
* API username
* API password

Then, the module will test connection and report OK if all is fine : 

![install-11](img/install-11.jpg)

Or show an error (here missing permission) : 

![install-12](img/install-12.jpg)

Snmptrapd configuration
------------------------

Now, you must tell snmptrapd to send all traps to the module.

Edit the /etc/snmp/snmptrapd file and add : 

```
traphandle default /usr/bin/php /usr/share/icingaweb2/modules/trapdirector/bin/trap_in.php 
```

Note : on bottom of trapdirector configuration page, you will have the php and module directories adapted to your system. If it shows 'php-fpm' instead of php, you are using php-fpm and need to replace /sbin/php-fpm with something like bin/php .

In any case, it must be the php binary of php version > 7. You can check version on command line doing `php -v` 


Set up the community (still in snmptrapd.conf) : here with "public" 

```
authCommunity log,execute,net public
```

With a v3 user :

```
createUser -e 0x8000000001020304 trapuser SHA "UserPassword" AES "EncryptionKey"
authUser log,execute,net trapuser 
```

So here is what your snmptrapd.conf should look like : 

```
authCommunity log,execute,net public
traphandle default /usr/bin/php /usr/share/icingaweb2/modules/trapdirector/bin/trap_in.php

createUser -e 0x8000000001020304 trapuser SHA "UserPassword" AES "EncryptionKey"
authUser log,execute,net trapuser 
```

Edit the launch options of snmptrapd
------------------------

* For RH7/CenOS7 and other systems using systemd : 

In : `/usr/lib/systemd/system/snmptrapd.service`

If you have a line like `EnvironmentFile=-/etc/sysconfig/snmptrapd` change this file instead (see below for RH6)

Change : `Environment=OPTIONS="-Lsd"`

To : `Environment=OPTIONS="-Lsd -n -t -Oen"`

Note : if you have a weird 204 error on startup (happened on one centOS7 system), change ExecStart instead : 

`ExecStart=/usr/sbin/snmptrapd -n -t -Oen $OPTIONS -f`

* For RH6/CenOS6 and other /etc/init.d system services 

In : `/etc/sysconfig/snmptrapd`

Change : `# OPTIONS="-Lsd -p /var/run/snmptrapd.pid"`

To : `OPTIONS="-Lsd -n -t -Oen -p /var/run/snmptrapd.pid"`

Enable & start snmptrad service : 
------------------------

* On systemd 

```
systemctl daemon-reload

systemctl enable snmptrapd

systemctl start snmptrapd
```

* on init.d systems

```
chkconfig --level 345 snmptrapd on

service snmptrapd start

```

Now all traps received by the system will be redirected to the trapdirector module.

Set up mibs : 
------------------------

The system mibs should be set by net-snmp package. Check the defaults mibs are set by testing `snmptranslate 1.3.6.1.2.1.1.1` -> `SNMPv2-MIB::sysDescr` 

Mib you can upload will be in (default) `/usr/share/icingaweb2/modules/trapdirector/mibs` : you must check the directory is writable by the user of the web server.
For example (as root) : 
```
chown apache:apache /usr/share/icingaweb2/modules/trapdirector/mibs
chmod 755 /usr/share/icingaweb2/modules/trapdirector/mibs
```

After this, you can create the first mib database (from system mibs) with the following command line : 

```
icingacli trapdirector mib update
```

Ready to go !

Now have a look at the doc : ![Traps](02-userguide.md)
 
