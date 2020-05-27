Installation
===============

Requirements
---------------

* `icingaweb2`
* `php7` or later
* `net-snmp` for `snmptrapd`
* `net-snmp-utils` for `snmptranslate`
* `MySQL/MariaDB` or `PostgreSQL` as DB backend 

Optional
---------------

* Icinga Director : for centralized service and template management.


Get installation files
---------------

1. Download the latest release and unzip in a temporary directory.
2. Move the `trapdirector` directory to the modules directory (`/usr/share/icingaweb2/modules` by default).
3. `trapdirector/mibs/` directory must be writable by the icinga web user for web GUI MIB upload to function.

Automatic installation
-----------------

There is an optional install script which can help you with creating backend database and users with proper permissions. See [Automatic Installation](30-install-auto.md) for more information.

Non standard IcingaWeb2 installation
------------------------------------

If IcingaWeb2 configuration directory is not `/etc/icingaweb2`, you must set it up manually in the `/trapdirector/bin/trap_in.php` file by modifying this string:
```
[....]
// Icinga etc path : need to change this on non standard icinga installation.
$icingaweb2_etc="/etc/icingaweb2";
[....]
```

Database Preparation
-----------------

* MySQL / MariaDB

Set up a new (or use existing) database (these commands should be run as root or a database admin):

Create new database:

`mysql -u root -e "CREATE DATABASE <database name>;"`

Create user with required privileges:

```
mysql -u root -e "GRANT USAGE ON *.* TO <user>@localhost IDENTIFIED BY '<password>';"
mysql -u root -e "GRANT ALL PRIVILEGES ON <database name>.* TO <user>@localhost ;"
```

* PostgreSQL

Create user: 

````
su - postgres
createuser --interactive --pwprompt
````

or 

````
createuser -U <admin login> -W <new user> -P 
````

Create database: 

````
createdb -O <database user> <database name>
````

Create database in IcingaWeb2 web GUI on `/icingaweb2/config/resource` (trapdirector configuration page URL, available after module activation).


Module activation
---------------

Log in to the IcingaWeb2 web GUI and activate the trapdirector module in Configuration -> Modules.

After that, the trapdirector configuration tab should look like this: 

![install-1](img/install-1.jpg)

The options are:

* Database : backend database of trapdirector.
* Trapdirector table prefix : the prefix for all trapdirector-related database tables.
* IDO Database : the IDO database of IcingaWeb2.
* icingaweb2 config dir : configuration directory of IcingaWeb2.
* snmptranslate binary with path : path to binary, can be tested in MIB&status page.
* Path for mibs : Directory for trapdirector local MIBs (default `/usr/share/icingaweb2/modules/trapdirector/mibs`). You can add directories with `:` separators. MIBs will then be uploaded in the first one listed. 
* icingacmd with path : path to `icinga2` command file.
* icinga2 API host/port/username/password : see the API section.

Schema creation
---------------

After setting the database (1) and IDO database (2), save the configuration: 

![install-2](img/install-2.jpg)

Click on (3) to create required database schema:

![install-3](img/install-3.jpg)

Then go back to module configuration, database check should be showing OK status:

![install-4](img/install-4.jpg)

API user setup
---------------

An API user allows the module to use the Icinga2 API to submit check results, instead of via a command file, or where `icinga2` and icingaweb2 are running on separate hosts.

To create an `icinga2` API user, edit the API config file (`/etc/icinga2/conf.d/api-users.conf` by default) and add a section as per the following example: 

```
object ApiUser "trapdirector" {
  password = "trapdirector"
  permissions = [ "status", "objects/query/Host", "objects/query/Service" , "actions/process-check-result" ]
}
```
Note: These permissions are sufficient as of now, but may be subject to change in a future version of trapdirector (the module will check for this, see below).

Reload `icinga2` with `systemctl reload icinga2` command.

Then configure the API settings in the trapdirector module:

![install-10](img/install-10.jpg)

Fill in the following fields: 
* icinga2 API Host IP: IP or hostname of icinga2 server
* icinga2 API TCP Port: Port of icinga2 API (5665 by default)
* icinga2 API username: Name of the user in icinga2 `api_users.conf` file. 
* icinga2 API password: Password of the API user.

Then, the module will test connection and report OK if everything is fine : 

![install-11](img/install-11.jpg)

Else it will show a relevant error (missing permission in the example) : 

![install-12](img/install-12.jpg)

Snmptrapd configuration
------------------------

Next, snmptrapd must be configured to relay all SNMP traps to the module.

Edit the `/etc/snmp/snmptrapd.conf` file and add this line to it (assuming default file locations): 

```
traphandle default /usr/bin/php /usr/share/icingaweb2/modules/trapdirector/bin/trap_in.php 
```

At the bottom of trapdirector configuration page, you will have list of PHP and module directories on your system. If it shows `php-fpm` instead of `php`, you are using PHP-FPM and need to replace `/usr/bin/php` with something like `/sbin/php-fpm` in the above traphandle line.

Next, set up the community in `snmptrapd.conf` (`public` in example):

```
authCommunity log,execute,net public
```

Or, for SNMPv3 user :

```
createUser -e 0x8000000001020304 trapuser SHA "UserPassword" AES "EncryptionKey"
authUser log,execute,net trapuser 
```

So here is what your `snmptrapd.conf` should look like : 

```
authCommunity log,execute,net public
traphandle default /usr/bin/php /usr/share/icingaweb2/modules/trapdirector/bin/trap_in.php

createUser -e 0x8000000001020304 trapuser SHA "UserPassword" AES "EncryptionKey"
authUser log,execute,net trapuser 
```

Edit the launch options of snmptrapd
------------------------

* For RH7/CenOS7 and other systems using systemd : 

In `/usr/lib/systemd/system/snmptrapd.service` or `/lib/systemd/system/snmptrapd.service` 

If you have a line like `EnvironmentFile=-/etc/sysconfig/snmptrapd` change that file instead (as you would in RH6 example below).

If not, change `Environment=OPTIONS="-Lsd"` to `Environment=OPTIONS="-Lsd -n -t -Oen"`

If you have a weird 204 error on startup (happened on CentOS7 system), change `ExecStart` line instead to 

`ExecStart=/usr/sbin/snmptrapd -n -t -Oen $OPTIONS -f`


* For RH6/CenOS6 and other /etc/init.d system services 

In `/etc/sysconfig/snmptrapd` file change `# OPTIONS="-Lsd -p /var/run/snmptrapd.pid"` line to `OPTIONS="-Lsd -n -t -Oen -p /var/run/snmptrapd.pid"`

Enable & start snmptrap service : 
------------------------

* On systemd:

```
systemctl daemon-reload

systemctl enable snmptrapd

systemctl start snmptrapd
```

* on init.d systems:

```
chkconfig --level 345 snmptrapd on

service snmptrapd start
```

Now all traps received by the system will be redirected to the trapdirector module.

Set up MIBs
------------------------

The system MIBs should be set by the `net-snmp` package. Test the default MIBs status by `snmptranslate 1.3.6.1.2.1.1.1` with a required result of `SNMPv2-MIB::sysDescr` 

Uploaded MIBs will be in `/usr/share/icingaweb2/modules/trapdirector/mibs` directory by default: you must check if the directory is writable by the user of the web server.
For example (as root) : 
```
chown apache:apache /usr/share/icingaweb2/modules/trapdirector/mibs
chmod 755 /usr/share/icingaweb2/modules/trapdirector/mibs
```

After this, you can add your first MIBs into MIB database with the `icingacli trapdirector mib update` command

Ready to go!



User Guide
------------------------
Continue to the [user guide](02-userguide.md) for configuring trap handlers and other trapdirector features.
