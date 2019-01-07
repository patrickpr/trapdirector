#!/bin/bash

read host
 read ip
 vars=
 
 while read oid val
 do
   if [ "$vars" = "" ]
   then
     vars="$oid = $val \n "
   else
     vars="$vars, $oid = $val \n "
   fi
 done
 
 echo -e "trap: $1 \n HOST= $host \n IP= $ip \n VARS = $vars ">> /tmp/trap1.txt
