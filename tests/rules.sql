INSERT INTO traps_rules
( ip4 , 		trap_oid , 		host_name , 	host_group_name , 	action_match , action_nomatch ,	service_name ,		rule ,   			display)
VALUES 
( '127.0.0.1' ,	'.1.3.6.31.1',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	''	,						'OK 1'), 
( '127.0.0.1' ,	'.1.3.6.31.2',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.32.1) = 3'	, 	'OK _OID(.1.3.6.32.2)'), 
( '127.0.0.1' ,	'.1.3.6.31.3',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.32.1) >< "test"'	,	'OK 1'), 
( '127.0.0.1' ,	'.1.3.6.31.4',	'Icinga host', 	NULL, 				0 , 			1	, 			'LinkTrapStatus',	'_OID(.1.3.6.*.2) = 3'	,	 'OK _OID(.1.3.6.**)'); 