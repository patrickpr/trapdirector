ALTER TABLE `traps_mib_cache` 
ADD COLUMN `description` TEXT NULL DEFAULT NULL AFTER `type_enum`;
ADD COLUMN `syntax` VARCHAR(256) NULL DEFAULT NULL AFTER `display_hint`,
CHANGE COLUMN `type` `type` VARCHAR(256) NULL DEFAULT NULL ,
CHANGE COLUMN `textual_convention` `textual_convention` VARCHAR(256) NULL DEFAULT NULL ;

DROP TABLE `traps_mib_cache_syntax`;
DROP TABLE `traps_mib_cache_tc`;
DROP TABLE `traps_mib_cache_trap_object`

CREATE TABLE `traps_mib_cache_trap_object` (
  `id` int(12) NOT NULL,
  `trap_id` int(11) NOT NULL,
  `object_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_trap_id_obj_idx` (`trap_id`),
  KEY `FK_object_id_obj_idx` (`object_id`),
  CONSTRAINT `FK_trap_id_obj` FOREIGN KEY (`trap_id`) REFERENCES `traps_mib_cache` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

