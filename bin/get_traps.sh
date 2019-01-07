#!/bin/bash

MIBDIRS="/usr/share/icingaweb2/modules/trapdirector/mibs:/usr/share/snmp/mibs"
TRAPS=$(snmptranslate  -m ALL -M $MIBDIRS -Tt | grep 'type=21' |  sed -r 's/\(.+\) type=21//' );

for i in $TRAPS; do
#for i in "ipv6IfStateChange"; do
  # Get trap + mib : MIB::Name
  TRAPD1=$(snmptranslate  -m ALL -M $MIBDIRS -TB "^${i}$")
  for j in $TRAPD1; do
	  OLDIFS=$IFS
	  IFS="
"
	  TRAPDET=($(snmptranslate  -m ALL -M $MIBDIRS  -On -Td $j))
	  IFS=$OLDIFS
	  
	  OID=${TRAPDET[0]}
	  #echo $OID
	  # Check it's really a trap
	  if ! [[ ${TRAPDET[1]} =~  "NOTIFICATION-TYPE" ]] ; then continue; fi
	  
	  if [[ ${TRAPDET[3]} =~  "OBJECT" ]] ; then 
	     OBJECTS=$(echo ${TRAPDET[3]} | sed -r -e 's/.*\{(.*)\}.*/\1/' -e 's/,//g')
	     MIB=$(echo "$j" | cut -d":" -f1)
	     OBJECTS2=''
             for k in $OBJECTS; do
		LISTOBJ=$(snmptranslate  -m ALL -M $MIBDIRS -TB "^${k}$")

		for l in $LISTOBJ; do
		  OOID=$(snmptranslate  -m ALL -M $MIBDIRS -On $l)
		  SYNTAX=$(snmptranslate  -m ALL -M $MIBDIRS -Td -On $l | grep SYNTAX | sed -r 's/.*SYNTAX\t([^\t]+).*/\1/')
		  if [ $? -eq 0 ] ; then
			OBJECTS2="$OBJECTS2 $OOID '$SYNTAX' $l"
	          else
			echo "Error objects"	
		  fi
	        done
	     done
	  else
	     OBJECTS=''
	  fi
	  echo "$OID $j $OBJECTS2"
  done
done 


