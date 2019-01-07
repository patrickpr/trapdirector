CREATE TABLE `traps_db_config` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `traps_received` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8;

CREATE TABLE `traps_received_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `oid` varchar(256) DEFAULT NULL,
  `value` varchar(1024) DEFAULT NULL,
  `trap_id` int(11) unsigned NOT NULL,
  `oid_name` varchar(256) DEFAULT NULL,
  `oid_name_mib` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_trap_id_idx` (`trap_id`),
  CONSTRAINT `FK_trap_id` FOREIGN KEY (`trap_id`) REFERENCES `traps_received` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8;

CREATE TABLE `traps_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip4` varchar(20) DEFAULT NULL,
  `ip6` varchar(42) DEFAULT NULL,
  `trap_oid` varchar(256) NOT NULL,
  `host_name` varchar(256) NOT NULL,
  `host_group_name` varchar(256) DEFAULT NULL,
  `rule` text,
  `action_match` tinyint(5) NOT NULL DEFAULT '-1',
  `action_nomatch` tinyint(5) NOT NULL DEFAULT '-1',
  `service_name` varchar(256) NOT NULL,
  `revert_ok` int(11) NOT NULL DEFAULT '3600',
  `display` text,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modifier` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;
