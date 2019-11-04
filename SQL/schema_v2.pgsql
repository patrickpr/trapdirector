CREATE TABLE #PREFIX#db_config (
	id serial NOT NULL,
    name character varying(100) NOT NULL,
    value text,
    PRIMARY KEY (id)
);

INSERT INTO #PREFIX#db_config (name,value) VALUES ('db_version',2);

CREATE OR REPLACE FUNCTION unix_timestamp(timestamp with time zone) RETURNS bigint AS '
        SELECT EXTRACT(EPOCH FROM $1)::bigint AS result
' LANGUAGE sql;

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

CREATE TYPE trapstate AS ENUM
    ('done', 'waiting', 'unknown', 'error');

CREATE TABLE #PREFIX#received (
  id serial NOT NULL,
  source_ip character varying(45) DEFAULT NULL,
  source_port integer  DEFAULT NULL,
  destination_ip character varying(45) DEFAULT NULL,
  destination_port integer DEFAULT NULL,
  trap_oid character varying(256) DEFAULT NULL,
  date_received TIMESTAMPTZ NOT NULL,
  status trapstate NOT NULL DEFAULT 'waiting',
  trap_name character varying(256) DEFAULT NULL,
  source_name character varying(256) DEFAULT NULL,
  trap_name_mib character varying(100) DEFAULT NULL,
  process_time float DEFAULT '0',
  status_detail character varying(256) DEFAULT NULL,
  PRIMARY KEY (id)
) ;

CREATE TABLE #PREFIX#received_data (
  id serial NOT NULL,
  oid character varying(256) DEFAULT NULL,
  value character varying(1024) DEFAULT NULL,
  trap_id bigint  NOT NULL,
  oid_name character varying(256) DEFAULT NULL,
  oid_name_mib character varying(100) DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_trap_id FOREIGN KEY (trap_id) 
	REFERENCES #PREFIX#received (id) 
	ON DELETE CASCADE
) ;

CREATE TABLE #PREFIX#rules (
  id serial NOT NULL,
  ip4 character varying(20) DEFAULT NULL,
  ip6 character varying(42) DEFAULT NULL,
  trap_oid character varying(256) NOT NULL,
  host_name character varying(256) DEFAULT NULL,
  host_group_name character varying(256) DEFAULT NULL,
  rule text,
  action_match smallint NOT NULL DEFAULT '-1',
  action_nomatch smallint NOT NULL DEFAULT '-1',
  service_name character varying(256) NOT NULL,
  revert_ok bigint NOT NULL DEFAULT '3600',
  display_nok text,
  display text,
  created TIMESTAMPTZ DEFAULT NULL,
  modified TIMESTAMPTZ DEFAULT NULL,
  modifier character varying(100) DEFAULT NULL,
  num_match bigint DEFAULT '0',
  num_match_nok bigint DEFAULT NULL,
  comment text,
  rule_type integer DEFAULT NULL,
  PRIMARY KEY (id)
) ;
