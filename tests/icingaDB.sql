
DROP TABLE IF EXISTS `icinga_hostgroup_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `icinga_hostgroup_members` (
  `hostgroup_member_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(20) unsigned DEFAULT '0',
  `hostgroup_id` bigint(20) unsigned DEFAULT '0',
  `host_object_id` bigint(20) unsigned DEFAULT '0',
  PRIMARY KEY (`hostgroup_member_id`),
  KEY `hostgroup_members_i_id_idx` (`instance_id`),
  KEY `hstgrpmbrs_hgid_hoid` (`hostgroup_id`,`host_object_id`),
  KEY `idx_hostgroup_members_object_id` (`host_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COMMENT='Hostgroup members';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `icinga_hostgroup_members`
--

LOCK TABLES `icinga_hostgroup_members` WRITE;
/*!40000 ALTER TABLE `icinga_hostgroup_members` DISABLE KEYS */;
INSERT INTO `icinga_hostgroup_members` VALUES (1,1,2,219),(3,1,3,246),(5,1,3,248);
/*!40000 ALTER TABLE `icinga_hostgroup_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `icinga_hostgroups`
--

DROP TABLE IF EXISTS `icinga_hostgroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `icinga_hostgroups` (
  `hostgroup_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(20) unsigned DEFAULT '0',
  `config_type` smallint(6) DEFAULT '0',
  `hostgroup_object_id` bigint(20) unsigned DEFAULT '0',
  `alias` varchar(255) DEFAULT '',
  `notes` text,
  `notes_url` text,
  `action_url` text,
  `config_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`hostgroup_id`),
  UNIQUE KEY `instance_id` (`instance_id`,`hostgroup_object_id`),
  KEY `hostgroups_i_id_idx` (`instance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COMMENT='Hostgroup definitions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `icinga_hostgroups`
--

LOCK TABLES `icinga_hostgroups` WRITE;
/*!40000 ALTER TABLE `icinga_hostgroups` DISABLE KEYS */;
INSERT INTO `icinga_hostgroups` VALUES (3,1,1,251,'Trap Group','','','','8ba76508b1868716c37853f9c773c4c4bfc3c455de87e3f8288781c993c30100');
/*!40000 ALTER TABLE `icinga_hostgroups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `icinga_hosts`
--

DROP TABLE IF EXISTS `icinga_hosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `icinga_hosts` (
  `host_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(20) unsigned DEFAULT '0',
  `config_type` smallint(6) DEFAULT '0',
  `host_object_id` bigint(20) unsigned DEFAULT '0',
  `alias` varchar(255) DEFAULT '',
  `display_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT '',
  `address` varchar(128) DEFAULT '',
  `address6` varchar(128) DEFAULT '',
  `check_command_object_id` bigint(20) unsigned DEFAULT '0',
  `check_command_args` text,
  `eventhandler_command_object_id` bigint(20) unsigned DEFAULT '0',
  `eventhandler_command_args` text,
  `notification_timeperiod_object_id` bigint(20) unsigned DEFAULT '0',
  `check_timeperiod_object_id` bigint(20) unsigned DEFAULT '0',
  `failure_prediction_options` varchar(128) DEFAULT '',
  `check_interval` double DEFAULT '0',
  `retry_interval` double DEFAULT '0',
  `max_check_attempts` smallint(6) DEFAULT '0',
  `first_notification_delay` double DEFAULT '0',
  `notification_interval` double DEFAULT '0',
  `notify_on_down` smallint(6) DEFAULT '0',
  `notify_on_unreachable` smallint(6) DEFAULT '0',
  `notify_on_recovery` smallint(6) DEFAULT '0',
  `notify_on_flapping` smallint(6) DEFAULT '0',
  `notify_on_downtime` smallint(6) DEFAULT '0',
  `stalk_on_up` smallint(6) DEFAULT '0',
  `stalk_on_down` smallint(6) DEFAULT '0',
  `stalk_on_unreachable` smallint(6) DEFAULT '0',
  `flap_detection_enabled` smallint(6) DEFAULT '0',
  `flap_detection_on_up` smallint(6) DEFAULT '0',
  `flap_detection_on_down` smallint(6) DEFAULT '0',
  `flap_detection_on_unreachable` smallint(6) DEFAULT '0',
  `low_flap_threshold` double DEFAULT '0',
  `high_flap_threshold` double DEFAULT '0',
  `process_performance_data` smallint(6) DEFAULT '0',
  `freshness_checks_enabled` smallint(6) DEFAULT '0',
  `freshness_threshold` int(11) DEFAULT '0',
  `passive_checks_enabled` smallint(6) DEFAULT '0',
  `event_handler_enabled` smallint(6) DEFAULT '0',
  `active_checks_enabled` smallint(6) DEFAULT '0',
  `retain_status_information` smallint(6) DEFAULT '0',
  `retain_nonstatus_information` smallint(6) DEFAULT '0',
  `notifications_enabled` smallint(6) DEFAULT '0',
  `obsess_over_host` smallint(6) DEFAULT '0',
  `failure_prediction_enabled` smallint(6) DEFAULT '0',
  `notes` text,
  `notes_url` text,
  `action_url` text,
  `icon_image` text,
  `icon_image_alt` text,
  `vrml_image` text,
  `statusmap_image` text,
  `have_2d_coords` smallint(6) DEFAULT '0',
  `x_2d` smallint(6) DEFAULT '0',
  `y_2d` smallint(6) DEFAULT '0',
  `have_3d_coords` smallint(6) DEFAULT '0',
  `x_3d` double DEFAULT '0',
  `y_3d` double DEFAULT '0',
  `z_3d` double DEFAULT '0',
  `config_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`host_id`),
  UNIQUE KEY `instance_id` (`instance_id`,`config_type`,`host_object_id`),
  KEY `host_object_id` (`host_object_id`),
  KEY `hosts_i_id_idx` (`instance_id`),
  KEY `hosts_host_object_id_idx` (`host_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COMMENT='Host definitions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `icinga_hosts`
--

LOCK TABLES `icinga_hosts` WRITE;
/*!40000 ALTER TABLE `icinga_hosts` DISABLE KEYS */;
INSERT INTO `icinga_hosts` VALUES (1,1,1,219,'icinga.maisondubonheur.local','icinga.maisondubonheur.local','127.0.0.1','::1',146,NULL,0,NULL,0,0,'',1,0.5,3,0,30,1,1,1,1,1,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','',NULL,NULL,0,0,0,0,0,0,0,'0deafb03a2a009a4da69dd8093f1c718b159f677a0af71832348d8bfdf4c2cc6'),(2,1,1,246,'Icinga host','Icinga host','127.0.0.1','',198,NULL,0,NULL,0,0,'',60,1,3,0,1,0,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','',NULL,NULL,0,0,0,0,0,0,0,'9032c2e0ed8117a692813e4808caf1f7f9bf9fb159a8541ec40e9ebfa901347c'),(3,1,1,248,'Test Trap Host','Test Trap Host','192.168.56.101','',198,NULL,0,NULL,0,0,'',60,1,3,0,1,0,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','',NULL,NULL,0,0,0,0,0,0,0,'91016673c58a61d106567b211c2d712e6f64c68b306d2f407c70b5365d1f3b9c');
/*!40000 ALTER TABLE `icinga_hosts` ENABLE KEYS */;
UNLOCK TABLES;


DROP TABLE IF EXISTS `icinga_objects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `icinga_objects` (
  `object_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(20) unsigned DEFAULT '0',
  `objecttype_id` bigint(20) unsigned DEFAULT '0',
  `name1` varchar(128) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT '',
  `name2` varchar(128) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  `is_active` smallint(6) DEFAULT '0',
  PRIMARY KEY (`object_id`),
  KEY `objecttype_id` (`objecttype_id`,`name1`,`name2`),
  KEY `objects_objtype_id_idx` (`objecttype_id`),
  KEY `objects_name1_idx` (`name1`),
  KEY `objects_name2_idx` (`name2`),
  KEY `objects_inst_id_idx` (`instance_id`),
  KEY `sla_idx_obj` (`objecttype_id`,`is_active`,`name1`)
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=latin1 COMMENT='Current and historical objects of all kinds';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `icinga_objects`
--

LOCK TABLES `icinga_objects` WRITE;
/*!40000 ALTER TABLE `icinga_objects` DISABLE KEYS */;
INSERT INTO `icinga_objects` VALUES (1,1,12,'check_ido',NULL,1),(2,1,12,'check_smart',NULL,1),(3,1,12,'check_nscp',NULL,1),(4,1,12,'check_dhcp',NULL,1),(5,1,12,'check_smtp',NULL,1),(6,1,12,'check_vmware-esx-soap-vm-net-usage',NULL,1),(7,1,12,'check_lsi-raid',NULL,1),(8,1,12,'check_load-windows',NULL,1),(9,1,12,'check_breeze',NULL,1),(10,1,12,'check_dummy',NULL,1),(11,1,12,'check_iftraffic64',NULL,1),(12,1,12,'check_nrpe',NULL,1),(13,1,12,'check_service-windows',NULL,1),(14,1,12,'check_vmware-esx-soap-host-io-write-latency',NULL,1),(15,1,12,'check_hpjd',NULL,1),(16,1,12,'check_vmware-esx-soap-vm-runtime-gueststate',NULL,1),(17,1,12,'check_running_kernel',NULL,1),(18,1,12,'check_vmware-esx-soap-host-io-usage',NULL,1),(19,1,12,'check_rbl',NULL,1),(20,1,12,'check_vmware-esx-dc-runtime-listcluster',NULL,1),(21,1,12,'check_vmware-esx-dc-runtime-tools',NULL,1),(22,1,12,'check_vmware-esx-soap-vm-io',NULL,1),(23,1,12,'check_vmware-esx-soap-host-io-resets',NULL,1),(24,1,12,'check_snmp-load',NULL,1),(25,1,12,'check_vmware-esx-soap-host-mem-consumed',NULL,1),(26,1,12,'check_redis',NULL,1),(27,1,12,'check_nwc_health',NULL,1),(28,1,12,'check_vmware-esx-soap-host-io-write',NULL,1),(29,1,12,'check_perfmon-windows',NULL,1),(30,1,12,'check_vmware-esx-dc-runtime-listhost',NULL,1),(31,1,12,'check_ipmi-alive',NULL,1),(32,1,12,'check_vmware-esx-soap-host-io',NULL,1),(33,1,12,'check_by_ssh',NULL,1),(34,1,12,'check_mysql',NULL,1),(35,1,12,'check_mailq',NULL,1),(36,1,12,'check_users',NULL,1),(37,1,12,'check_disk_smb',NULL,1),(38,1,12,'check_icinga',NULL,1),(39,1,12,'check_vmware-esx-soap-host-net-send',NULL,1),(40,1,12,'check_snmp-memory',NULL,1),(41,1,12,'check_game',NULL,1),(42,1,12,'check_hpasm',NULL,1),(43,1,12,'check_webinject',NULL,1),(44,1,12,'check_vmware-esx-soap-host-runtime-con',NULL,1),(45,1,12,'check_passive',NULL,1),(46,1,12,'check_vmware-esx-dc-runtime-listvms',NULL,1),(47,1,12,'check_cluster-zone',NULL,1),(48,1,12,'check_snmpv3',NULL,1),(49,1,12,'check_spop',NULL,1),(50,1,12,'check_oracle_health',NULL,1),(51,1,12,'check_ping6-windows',NULL,1),(52,1,12,'check_nscp-local-version',NULL,1),(53,1,12,'check_ping6',NULL,1),(54,1,12,'check_vmware-esx-soap-vm-cpu-ready',NULL,1),(55,1,12,'check_update-windows',NULL,1),(56,1,12,'check_ping-windows',NULL,1),(57,1,12,'check_network-windows',NULL,1),(58,1,12,'check_vmware-esx-soap-host-mem-swapused',NULL,1),(59,1,12,'check_vmware-esx-soap-host-media',NULL,1),(60,1,12,'check_kdc',NULL,1),(61,1,12,'check_logstash',NULL,1),(62,1,12,'check_nginx_status',NULL,1),(63,1,12,'check_squid',NULL,1),(64,1,12,'check_ipmi-sensor',NULL,1),(65,1,12,'check_vmware-esx-soap-host-io-read-latency',NULL,1),(66,1,12,'check_vmware-esx-soap-host-io-kernel-latency',NULL,1),(67,1,12,'check_radius',NULL,1),(68,1,12,'check_users-windows',NULL,1),(69,1,12,'check_nscp-local-counter',NULL,1),(70,1,12,'check_db2_health',NULL,1),(71,1,12,'check_cloudera_service_status',NULL,1),(72,1,12,'check_vmware-esx-soap-host-cpu-usage',NULL,1),(73,1,12,'check_iostat',NULL,1),(74,1,12,'check_hostalive4',NULL,1),(75,1,12,'check_swap-windows',NULL,1),(76,1,12,'check_dig',NULL,1),(77,1,12,'check_vmware-esx-soap-host-mem-usage',NULL,1),(78,1,12,'check_ssmtp',NULL,1),(79,1,12,'check_uptime-windows',NULL,1),(80,1,12,'check_rpc',NULL,1),(81,1,12,'check_snmp-service',NULL,1),(82,1,12,'check_vmware-esx-soap-vm-io-read',NULL,1),(83,1,12,'check_nscp-local-disk',NULL,1),(84,1,12,'check_nscp-local-os-version',NULL,1),(85,1,12,'check_vmware-esx-soap-host-runtime-health-listsensors',NULL,1),(86,1,12,'check_vmware-esx-soap-host-runtime-storagehealth',NULL,1),(87,1,12,'check_mem',NULL,1),(88,1,12,'check_vmware-esx-soap-vm-mem',NULL,1),(89,1,12,'check_iostats',NULL,1),(90,1,12,'check_ftp',NULL,1),(91,1,12,'check_vmware-esx-soap-host-runtime-health-nostoragestatus',NULL,1),(92,1,12,'check_vmware-esx-dc-runtime-status',NULL,1),(93,1,12,'check_vmware-esx-soap-host-check',NULL,1),(94,1,12,'check_nscp-local-cpu',NULL,1),(95,1,12,'check_procs-windows',NULL,1),(96,1,12,'check_vmware-esx-soap-vm-runtime-consoleconnections',NULL,1),(97,1,12,'check_load',NULL,1),(98,1,12,'check_vmware-esx-soap-vm-cpu-wait',NULL,1),(99,1,12,'check_mysql_query',NULL,1),(100,1,12,'check_nscp-local',NULL,1),(101,1,12,'check_vmware-esx-soap-vm-runtime-powerstate',NULL,1),(102,1,12,'check_vmware-esx-soap-vm-cpu-usage',NULL,1),(103,1,12,'check_dns',NULL,1),(104,1,12,'check_graphite',NULL,1),(105,1,12,'check_nscp-local-uptime',NULL,1),(106,1,12,'check_imap',NULL,1),(107,1,12,'check_vmware-esx-soap-host-runtime-issues',NULL,1),(108,1,12,'check_vmware-esx-soap-vm-runtime-con',NULL,1),(109,1,12,'check_icingacli-businessprocess',NULL,1),(110,1,12,'check_ssl_cert',NULL,1),(111,1,12,'check_snmp-env',NULL,1),(112,1,12,'check_vmware-esx-soap-host-net-receive',NULL,1),(113,1,12,'check_vmware-esx-soap-vm-io-usage',NULL,1),(114,1,12,'check_apt',NULL,1),(115,1,12,'check_fping4',NULL,1),(116,1,12,'check_vmware-esx-soap-host-service',NULL,1),(117,1,12,'check_glusterfs',NULL,1),(118,1,12,'check_hostalive6',NULL,1),(119,1,12,'check_vmware-esx-soap-host-cpu',NULL,1),(120,1,12,'check_openmanage',NULL,1),(121,1,12,'check_pgsql',NULL,1),(122,1,12,'check_ping4',NULL,1),(123,1,12,'check_memory-windows',NULL,1),(124,1,12,'check_disk-windows',NULL,1),(125,1,12,'check_interfaces',NULL,1),(126,1,12,'check_swap',NULL,1),(127,1,12,'check_mongodb',NULL,1),(128,1,12,'check_snmp-storage',NULL,1),(129,1,12,'check_vmware-esx-soap-host-storage-lun',NULL,1),(130,1,12,'check_vmware-esx-soap-host-mem-memctl',NULL,1),(131,1,12,'check_ntp_time',NULL,1),(132,1,12,'check_vmware-esx-soap-host-storage-path',NULL,1),(133,1,12,'check_udp',NULL,1),(134,1,12,'check_nscp-local-process',NULL,1),(135,1,12,'check_nscp-local-pagefile',NULL,1),(136,1,12,'check_negate',NULL,1),(137,1,12,'check_vmware-esx-dc-runtime-issues',NULL,1),(138,1,12,'check_vmware-esx-soap-host-runtime-status',NULL,1),(139,1,12,'check_vmware-esx-soap-host-io-device-latency',NULL,1),(140,1,12,'check_vmware-esx-soap-vm-runtime-status',NULL,1),(141,1,12,'check_vmware-esx-soap-host-io-total-latency',NULL,1),(142,1,12,'check_vmware-esx-soap-vm-net-receive',NULL,1),(143,1,12,'check_vmware-esx-soap-vm-mem-usage',NULL,1),(144,1,12,'check_vmware-esx-soap-vm-runtime-tools',NULL,1),(145,1,12,'check_ups',NULL,1),(146,1,12,'check_hostalive',NULL,1),(147,1,12,'check_vmware-esx-soap-host-runtime-listvms',NULL,1),(148,1,12,'check_cluster',NULL,1),(149,1,12,'check_vmware-esx-soap-vm-runtime-issues',NULL,1),(150,1,12,'check_jmx4perl',NULL,1),(151,1,12,'check_vmware-esx-soap-vm-net-send',NULL,1),(152,1,12,'check_vmware-esx-soap-host-runtime-health',NULL,1),(153,1,12,'check_vmware-esx-soap-host-storage-adapter',NULL,1),(154,1,12,'check_mysql_health',NULL,1),(155,1,12,'check_disk',NULL,1),(156,1,12,'check_file_age',NULL,1),(157,1,12,'check_ntp_peer',NULL,1),(158,1,12,'check_vmware-esx-dc-runtime-info',NULL,1),(159,1,12,'check_vmware-esx-soap-host-runtime',NULL,1),(160,1,12,'check_vmware-esx-soap-host-net-nic',NULL,1),(161,1,12,'check_ping',NULL,1),(162,1,12,'check_vmware-esx-soap-vm-net',NULL,1),(163,1,12,'check_vmware-esx-soap-vm-cpu',NULL,1),(164,1,12,'check_nscp-local-memory',NULL,1),(165,1,12,'check_flexlm',NULL,1),(166,1,12,'check_vmware-esx-soap-host-io-queue-latency',NULL,1),(167,1,12,'check_ssl',NULL,1),(168,1,12,'check_vmware-esx-soap-host-cpu-wait',NULL,1),(169,1,12,'check_vmware-esx-soap-host-mem-overhead',NULL,1),(170,1,12,'check_postgres',NULL,1),(171,1,12,'check_vmware-esx-soap-vm-io-write',NULL,1),(172,1,12,'check_vmware-esx-soap-vm-mem-memctl',NULL,1),(173,1,12,'check_vmware-esx-soap-host-runtime-temp',NULL,1),(174,1,12,'check_adaptec-raid',NULL,1),(175,1,12,'check_simap',NULL,1),(176,1,12,'check_snmp',NULL,1),(177,1,12,'check_mssql_health',NULL,1),(178,1,12,'check_elasticsearch',NULL,1),(179,1,12,'check_esxi_hardware',NULL,1),(180,1,12,'check_vmware-esx-soap-host-volumes',NULL,1),(181,1,12,'check_vmware-esx-soap-vm-runtime',NULL,1),(182,1,12,'check_http',NULL,1),(183,1,12,'check_ping4-windows',NULL,1),(184,1,12,'check_iftraffic',NULL,1),(185,1,12,'check_vmware-esx-soap-host-uptime',NULL,1),(186,1,12,'check_exception',NULL,1),(187,1,12,'check_nscp_api',NULL,1),(188,1,12,'check_vmware-esx-soap-host-net',NULL,1),(189,1,12,'check_icingacli-director',NULL,1),(190,1,12,'check_clamd',NULL,1),(191,1,12,'check_pop',NULL,1),(192,1,12,'check_tcp',NULL,1),(193,1,12,'check_vmware-esx-soap-host-storage',NULL,1),(194,1,12,'check_yum',NULL,1),(195,1,12,'check_vmware-esx-soap-host-cpu-ready',NULL,1),(196,1,12,'check_apache-status',NULL,1),(197,1,12,'check_random',NULL,1),(198,1,12,'check_icmp',NULL,1),(199,1,12,'check_nscp-local-service',NULL,1),(200,1,12,'check_snmp-interface',NULL,1),(201,1,12,'check_procs',NULL,1),(202,1,12,'check_vmware-esx-soap-host-io-aborted',NULL,1),(203,1,12,'check_snmp-uptime',NULL,1),(204,1,12,'check_snmp-process',NULL,1),(205,1,12,'check_vmware-esx-dc-volumes',NULL,1),(206,1,12,'check_fping6',NULL,1),(207,1,12,'check_vmware-esx-soap-host-io-read',NULL,1),(208,1,12,'check_ssh',NULL,1),(209,1,12,'check_vmware-esx-soap-vm-mem-consumed',NULL,1),(210,1,12,'check_vmware-esx-soap-host-mem',NULL,1),(211,1,12,'check_ldap',NULL,1),(212,1,12,'check_interfacetable',NULL,1),(213,1,12,'check_vmware-esx-soap-host-net-usage',NULL,1),(214,1,12,'check_ceph',NULL,1),(215,1,12,'check_nscp-local-tasksched',NULL,1),(216,1,12,'check_smart-attributes',NULL,1),(217,1,13,'icinga.maisondubonheur.local',NULL,1),(218,1,14,'icinga.maisondubonheur.local',NULL,0),(219,1,1,'icinga.maisondubonheur.local',NULL,0),(220,1,3,'windows-servers',NULL,0),(221,1,3,'linux-servers',NULL,0),(222,1,12,'notification_mail-service-notification',NULL,0),(223,1,12,'notification_mail-host-notification',NULL,0),(224,1,2,'icinga.maisondubonheur.local','load',0),(225,1,2,'icinga.maisondubonheur.local','icinga',0),(226,1,2,'icinga.maisondubonheur.local','disk',0),(227,1,2,'icinga.maisondubonheur.local','disk /',0),(228,1,2,'icinga.maisondubonheur.local','ping6',0),(229,1,2,'icinga.maisondubonheur.local','http',0),(230,1,2,'icinga.maisondubonheur.local','swap',0),(231,1,2,'icinga.maisondubonheur.local','ssh',0),(232,1,2,'icinga.maisondubonheur.local','ping4',0),(233,1,2,'icinga.maisondubonheur.local','procs',0),(234,1,2,'icinga.maisondubonheur.local','users',0),(235,1,4,'ping',NULL,0),(236,1,4,'http',NULL,0),(237,1,4,'disk',NULL,0),(238,1,9,'never',NULL,0),(239,1,9,'9to5',NULL,0),(240,1,9,'24x7',NULL,0),(241,1,10,'icingaadmin',NULL,0),(242,1,11,'icingaadmins',NULL,0),(243,1,14,'director-global',NULL,1),(244,1,14,'global-templates',NULL,1),(245,1,14,'master',NULL,1),(246,1,1,'Icinga host',NULL,1),(247,1,2,'Icinga host','Ping_host',1),(248,1,1,'trap_test',NULL,1),(249,1,2,'trap_test','Ping_host',1),(250,1,2,'Icinga host','LinkTrapStatus',1),(251,1,3,'test_trap',NULL,1),(252,1,2,'Icinga host','hostgroup_service_trap',1),(253,1,2,'trap_test','hostgroup_service_trap',1),(254,1,2,'trap_test','test_delete_service',1),(255,1,2,'Icinga host','test_delete_service',1),(256,1,2,'trap_test','NetBotz SNMP Traps',1),(257,1,2,'Icinga host','NetBotz SNMP Traps',1);
UNLOCK TABLES;


DROP TABLE IF EXISTS `icinga_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `icinga_services` (
  `service_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(20) unsigned DEFAULT '0',
  `config_type` smallint(6) DEFAULT '0',
  `host_object_id` bigint(20) unsigned DEFAULT '0',
  `service_object_id` bigint(20) unsigned DEFAULT '0',
  `display_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT '',
  `check_command_object_id` bigint(20) unsigned DEFAULT '0',
  `check_command_args` text,
  `eventhandler_command_object_id` bigint(20) unsigned DEFAULT '0',
  `eventhandler_command_args` text,
  `notification_timeperiod_object_id` bigint(20) unsigned DEFAULT '0',
  `check_timeperiod_object_id` bigint(20) unsigned DEFAULT '0',
  `failure_prediction_options` varchar(64) DEFAULT '',
  `check_interval` double DEFAULT '0',
  `retry_interval` double DEFAULT '0',
  `max_check_attempts` smallint(6) DEFAULT '0',
  `first_notification_delay` double DEFAULT '0',
  `notification_interval` double DEFAULT '0',
  `notify_on_warning` smallint(6) DEFAULT '0',
  `notify_on_unknown` smallint(6) DEFAULT '0',
  `notify_on_critical` smallint(6) DEFAULT '0',
  `notify_on_recovery` smallint(6) DEFAULT '0',
  `notify_on_flapping` smallint(6) DEFAULT '0',
  `notify_on_downtime` smallint(6) DEFAULT '0',
  `stalk_on_ok` smallint(6) DEFAULT '0',
  `stalk_on_warning` smallint(6) DEFAULT '0',
  `stalk_on_unknown` smallint(6) DEFAULT '0',
  `stalk_on_critical` smallint(6) DEFAULT '0',
  `is_volatile` smallint(6) DEFAULT '0',
  `flap_detection_enabled` smallint(6) DEFAULT '0',
  `flap_detection_on_ok` smallint(6) DEFAULT '0',
  `flap_detection_on_warning` smallint(6) DEFAULT '0',
  `flap_detection_on_unknown` smallint(6) DEFAULT '0',
  `flap_detection_on_critical` smallint(6) DEFAULT '0',
  `low_flap_threshold` double DEFAULT '0',
  `high_flap_threshold` double DEFAULT '0',
  `process_performance_data` smallint(6) DEFAULT '0',
  `freshness_checks_enabled` smallint(6) DEFAULT '0',
  `freshness_threshold` int(11) DEFAULT '0',
  `passive_checks_enabled` smallint(6) DEFAULT '0',
  `event_handler_enabled` smallint(6) DEFAULT '0',
  `active_checks_enabled` smallint(6) DEFAULT '0',
  `retain_status_information` smallint(6) DEFAULT '0',
  `retain_nonstatus_information` smallint(6) DEFAULT '0',
  `notifications_enabled` smallint(6) DEFAULT '0',
  `obsess_over_service` smallint(6) DEFAULT '0',
  `failure_prediction_enabled` smallint(6) DEFAULT '0',
  `notes` text,
  `notes_url` text,
  `action_url` text,
  `icon_image` text,
  `icon_image_alt` text,
  `config_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `instance_id` (`instance_id`,`config_type`,`service_object_id`),
  KEY `service_object_id` (`service_object_id`),
  KEY `services_i_id_idx` (`instance_id`),
  KEY `services_host_object_id_idx` (`host_object_id`),
  KEY `services_combined_object_idx` (`service_object_id`,`host_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COMMENT='Service definitions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `icinga_services`
--

LOCK TABLES `icinga_services` WRITE;
/*!40000 ALTER TABLE `icinga_services` DISABLE KEYS */;
INSERT INTO `icinga_services` VALUES (1,1,1,219,224,'load',97,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','de54b410cd12bab31eb37e4635c640b41cb57fe581b2f8b04c88438fa1547b71'),(2,1,1,219,225,'icinga',38,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','00bd4cc171927480a5b8b5fc90160001dea1c7b9cc1818ec397c992e48854a60'),(3,1,1,219,226,'disk',155,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','34f9d861ac9dcf2cbd1d7773f8c12bdd3383dcadfba930596c2526947c018545'),(4,1,1,219,227,'disk /',155,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','896cd1b199d2737af9584c09ed702aa89ade14eb4a9d8d19f94fdec5a388e5fc'),(5,1,1,219,228,'ping6',53,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','1f7c4fb6a84184470e201f81057bbe863a312598a0b4c187c332b4ca3a4260ff'),(6,1,1,219,229,'http',182,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','8d8c8782d5a643cea59c41980c969a68dcb6c81edd8d4b408b31f012e91a7c96'),(7,1,1,219,230,'swap',126,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','bb826ab89ab399ed2f963c08071db03a4971c61b52c11c7707dd0b5f857f3c47'),(8,1,1,219,231,'ssh',208,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','ad7ad115e08dd216269d060b5e50431cad3620cced61338409c11386a1587ca1'),(9,1,1,219,232,'ping4',122,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','9dad34a6e1ebd53fea567963a0786c0b90be1015c5906bfd24d98dfa72563d08'),(10,1,1,219,233,'procs',201,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','9e6f167ac0128ba350b3c66f22f6dc2c5e23c1a494da9b7f976d509008ac4fca'),(11,1,1,219,234,'users',36,NULL,0,NULL,0,0,'',1,0.5,5,0,30,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,25,25,1,1,60,1,1,1,0,0,1,0,0,'','','','','','39d27172a0f6e72627b9ba2294233669a9a47ab49db4c45564911aface7b7f78'),(12,1,1,246,247,'Ping_host',198,NULL,0,NULL,0,0,'',5,0.5,3,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,300,1,1,1,0,0,1,0,0,'','','','','','acc1a6fdf0414418d93b2fcf242ad82861334e8dd574abcee86bc6cb99c1c674'),(13,1,1,248,249,'Ping_host',198,NULL,0,NULL,0,0,'',5,0.5,3,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,300,1,1,1,0,0,1,0,0,'','','','','','8376b1e7fcf9d0ac7ffb525b05a4347bd218111f48e9b0176eab00952fe8ce5d'),(14,1,1,246,250,'LinkTrapStatus',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','65efc55815d0e2a9cd237c096aad90190f032e2729cdfe9fff303fd57bcc8f5c'),(15,1,1,246,252,'hostgroup_service_trap',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','68395fbcc435ec4c9b50dc7a155303b288ea9de0c55d70ba9732cb5040f7aaf0'),(16,1,1,248,253,'hostgroup_service_trap',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','8a1ce8cf9db7b441c44873f11260c8823a15fff9507810123f10ad7eda6b1c10'),(17,1,1,248,254,'test_delete_service',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','d2165df62e52576975b09203e2379a9f96dc2101add6335f9ef2db92e0a6f3b1'),(18,1,1,246,255,'test_delete_service',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','af9ed4babc11e5431fef3947e90e9773a96bb7929787043a61030d81a79865c5'),(19,1,1,248,256,'NetBotz SNMP Traps',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','b9baa5ccb7f910515bf1adfa6efca71214dfad1d8eff2a6d4006036074f6b91c'),(20,1,1,246,257,'NetBotz SNMP Traps',10,NULL,0,NULL,0,0,'',60,60,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,25,25,1,1,3600,1,1,1,0,0,1,0,0,'','','','','','38fc1dadd85649c0693420a1f39ada500a2a55281be073068f12db132cb9607b');
/*!40000 ALTER TABLE `icinga_services` ENABLE KEYS */;
UNLOCK TABLES;
