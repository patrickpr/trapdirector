#MESSAGE : This upgrade will drop all mib cache table. You will have to do a mib database update in status&mibs -> MIB Management -> Update.
#PRE-SCRIPT :
#POST-SCRIPT :

DROP TABLE #PREFIX#mib_cache_syntax;
DROP TABLE #PREFIX#mib_cache_tc;
DROP TABLE #PREFIX#mib_cache_trap_object;
DROP TABLE #PREFIX#mib_cache;

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

