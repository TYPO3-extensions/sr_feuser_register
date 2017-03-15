
# THESE create statements will be detected by the Typo3 Install Tool and through that
# you should upgrade the tables to content these fields.

CREATE TABLE fe_users (
	static_info_country varchar(3) DEFAULT '' NOT NULL,
	zone varchar(45) DEFAULT '' NOT NULL,
	language varchar(5) DEFAULT '' NOT NULL,
	gender int(11) DEFAULT '99' NOT NULL,
	cnum varchar(50) DEFAULT '' NOT NULL,
	name varchar(100) DEFAULT '' NOT NULL,
	first_name varchar(50) DEFAULT '' NOT NULL,
	last_name varchar(50) DEFAULT '' NOT NULL,
	status int(11) DEFAULT '0' NOT NULL,
	city varchar(40) DEFAULT '' NOT NULL,
	country varchar(60) DEFAULT '' NOT NULL,
	house_no varchar(20) DEFAULT '' NOT NULL,
	zip varchar(20) DEFAULT '' NOT NULL,
	telephone varchar(25) DEFAULT '' NOT NULL,
	fax varchar(25) DEFAULT '' NOT NULL,
	email varchar(255) DEFAULT '' NOT NULL,
	company varchar(50) DEFAULT '' NOT NULL,
	date_of_birth int(11) DEFAULT '0' NOT NULL,
	comments varchar(1024) DEFAULT '' NOT NULL,
	by_invitation smallint(6) DEFAULT '0' NOT NULL,
	module_sys_dmail_html smallint(6) DEFAULT '0' NOT NULL,
	terms_acknowledged smallint(6) DEFAULT '0' NOT NULL,
	token varchar(32) DEFAULT '' NOT NULL,
	tx_srfeuserregister_password blob
);


CREATE TABLE fe_groups_language_overlay (
	uid int(11) DEFAULT '0' NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	deleted smallint(6) DEFAULT '0' NOT NULL,
	hidden smallint(6) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	fe_group int(11) DEFAULT '0' NOT NULL,
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	title text NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);