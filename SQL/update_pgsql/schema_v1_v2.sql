#MESSAGE : This upgrade will drop all mib cache table. You will have to do a mib database update in status&mibs -> MIB Management -> Update.
#PRE-SCRIPT :
#POST-SCRIPT :

DROP TABLE #PREFIX#mib_cache_syntax;
DROP TABLE #PREFIX#mib_cache_tc;
DROP TABLE #PREFIX#mib_cache_trap_object;
DROP TABLE #PREFIX#mib_cache;

CREATE TABLE #PREFIX#mib_cache (
  id serial NOT NULL,
  oid character varying(256) NOT NULL,
  mib character varying(256) NOT NULL,
  name character varying(512) NOT NULL,
  type character varying(256) DEFAULT NULL,
  textual_convention character varying(256) DEFAULT NULL,
  display_hint character varying(256) DEFAULT NULL,
  syntax character varying(256) DEFAULT NULL,
  type_enum text,
  description text,
  PRIMARY KEY (id)
) ;

CREATE TABLE #PREFIX#mib_cache_trap_object (
  id serial NOT NULL,
  trap_id bigint NOT NULL,
  object_id bigint NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_object_id_obj FOREIGN KEY (object_id)
    REFERENCES #PREFIX#mib_cache (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,  
  CONSTRAINT FK_trap_id_obj FOREIGN KEY (trap_id) 
	REFERENCES #PREFIX#mib_cache (id) 
	ON DELETE CASCADE 
	ON UPDATE CASCADE
) ;

