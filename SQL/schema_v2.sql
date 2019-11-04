CREATE TABLE `#PREFIX#db_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `value` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

INSERT INTO #PREFIX#db_config (`name`,`value`) VALUES ('db_version',2);

CREATE TABLE #PREFIX#mib_cache (
  id int(11) NOT NULL AUTO_INCREMENT,
  oid varchar(256) NOT NULL,
  mib varchar(256) NOT NULL,
  name varchar(512) NOT NULL,
  type varchar(256) DEFAULT NULL,
  textual_convention varchar(256) DEFAULT NULL,
  display_hint varchar(256) DEFAULT NULL,
  syntax varchar(256) DEFAULT NULL,
  type_enum text,
  description text,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE #PREFIX#mib_cache_trap_object (
  id int(12) NOT NULL AUTO_INCREMENT,
  trap_id int(11) NOT NULL,
  object_id int(11) NOT NULL,
  PRIMARY KEY (id),
  KEY FK_trap_id_obj_idx (trap_id),
  KEY FK_object_id_obj_idx (object_id),
  CONSTRAINT FK_object_id_obj FOREIGN KEY (object_id) REFERENCES #PREFIX#mib_cache (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_trap_id_obj FOREIGN KEY (trap_id) REFERENCES #PREFIX#mib_cache (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `#PREFIX#received` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `source_ip` varchar(45) DEFAULT NULL,
  `source_port` smallint(5) unsigned DEFAULT NULL,
  `destination_ip` varchar(45) DEFAULT NULL,
  `destination_port` smallint(5) unsigned DEFAULT NULL,
  `trap_oid` varchar(256) DEFAULT NULL,
  `date_received` datetime NOT NULL,
  `status` enum('done','waiting','unknown','error') NOT NULL DEFAULT 'waiting',
  `trap_name` varchar(256) DEFAULT NULL,
  `source_name` varchar(256) DEFAULT NULL,
  `trap_name_mib` varchar(100) DEFAULT NULL,
  `process_time` float DEFAULT '0',
  `status_detail` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `#PREFIX#received_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `oid` varchar(256) DEFAULT NULL,
  `value` varchar(1024) DEFAULT NULL,
  `trap_id` int(11) unsigned NOT NULL,
  `oid_name` varchar(256) DEFAULT NULL,
  `oid_name_mib` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_trap_id_idx` (`trap_id`),
  CONSTRAINT `FK_trap_id` FOREIGN KEY (`trap_id`) REFERENCES `#PREFIX#received` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `#PREFIX#rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip4` varchar(20) DEFAULT NULL,
  `ip6` varchar(42) DEFAULT NULL,
  `trap_oid` varchar(256) NOT NULL,
  `host_name` varchar(256) DEFAULT NULL,
  `host_group_name` varchar(256) DEFAULT NULL,
  `rule` text,
  `action_match` tinyint(5) NOT NULL DEFAULT '-1',
  `action_nomatch` tinyint(5) NOT NULL DEFAULT '-1',
  `service_name` varchar(256) NOT NULL,
  `revert_ok` int(11) NOT NULL DEFAULT '3600',
  `display_nok` text,
  `display` text,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modifier` varchar(100) DEFAULT NULL,
  `num_match` int(16) DEFAULT '0',
  `num_match_nok` int(16) DEFAULT NULL,
  `comment` text,
  `rule_type` int(8) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

