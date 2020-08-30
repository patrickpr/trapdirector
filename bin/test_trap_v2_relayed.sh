#!/bin/bash
snmptrap -v 2c -c public 127.0.0.1 "" SNMPv2-MIB::coldStart SNMPv2-MIB::sysLocation.0 s "Just here" 1.3.6.1.4.1.2657.5.1 i 256 .1.3.6.1.6.3.18.1.3 a 192.168.0.20
