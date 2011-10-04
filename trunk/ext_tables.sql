
# THESE create statements will NOT work if this file is piped into MySQL.
# Rather they will be detected by the Typo3 Install Tool and through that
# you should upgrade the tables to content these fields.

CREATE TABLE fe_users (
	static_info_country char(3) DEFAULT '' NOT NULL,
	zone varchar(45) DEFAULT '' NOT NULL,
	language char(2) DEFAULT '' NOT NULL,
	gender int(11) unsigned DEFAULT '99' NOT NULL,
	cnum varchar(50) DEFAULT '' NOT NULL,
	name varchar(100) DEFAULT '' NOT NULL,
	first_name varchar(50) DEFAULT '' NOT NULL,
	last_name varchar(50) DEFAULT '' NOT NULL,
	status int(11) unsigned DEFAULT '0' NOT NULL,
	country varchar(60) DEFAULT '' NOT NULL,
	zip varchar(20) DEFAULT '' NOT NULL,
	date_of_birth int(11) DEFAULT '0' NOT NULL,
	comments text NOT NULL,
	by_invitation tinyint(4) unsigned DEFAULT '0' NOT NULL,
	module_sys_dmail_html tinyint(3) unsigned DEFAULT '0' NOT NULL,
	terms_acknowledged tinyint(4) unsigned DEFAULT '0' NOT NULL
);


CREATE TABLE fe_groups_language_overlay (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	sorting int(10) unsigned DEFAULT '0' NOT NULL,
	fe_group int(11) unsigned DEFAULT '0' NOT NULL,
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	title tinytext NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);




