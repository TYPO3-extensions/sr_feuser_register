<?php

########################################################################
# Extension Manager/Repository config file for ext: "sr_feuser_register"
#
# Auto generated 17-10-2007 23:00
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Frontend User Registration',
	'description' => 'A front end user self-registration variant of Kasper Skårhøj\'s Front End User Admin extension.',
	'category' => 'plugin',
	'shy' => 0,
	'dependencies' => 'cms,fh_library,static_info_tables',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_srfeuserregister',
	'modify_tables' => 'fe_users',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author' => 'Stanislas Rolland / Franz Holzinger',
	'author_email' => 'kontakt@fholzinger.com',
	'author_company' => 'Freelancer',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '2.5.8',
	'_md5_values_when_last_written' => 'a:43:{s:9:"ChangeLog";s:4:"b24f";s:16:"contributors.txt";s:4:"0f45";s:21:"ext_conf_template.txt";s:4:"d4b6";s:12:"ext_icon.gif";s:4:"ad8f";s:17:"ext_localconf.php";s:4:"617a";s:14:"ext_tables.php";s:4:"c471";s:14:"ext_tables.sql";s:4:"545a";s:13:"locallang.xml";s:4:"f26f";s:16:"locallang_db.xml";s:4:"3905";s:7:"tca.php";s:4:"aad1";s:14:"doc/manual.sxw";s:4:"fda1";s:38:"lib/class.tx_srfeuserregister_auth.php";s:4:"885e";s:39:"lib/class.tx_srfeuserregister_email.php";s:4:"efa3";s:38:"lib/class.tx_srfeuserregister_lang.php";s:4:"b483";s:37:"lib/class.tx_srfeuserregister_tca.php";s:4:"a8e1";s:25:"pi1/address_css_tmpl.html";s:4:"7b75";s:37:"pi1/class.tx_srfeuserregister_pi1.php";s:4:"a208";s:48:"pi1/class.tx_srfeuserregister_pi1_adodb_time.php";s:4:"0e9f";s:50:"pi1/class.tx_srfeuserregister_pi1_urlvalidator.php";s:4:"eca0";s:23:"pi1/flexform_ds_pi1.xml";s:4:"abb2";s:19:"pi1/icon_delete.gif";s:4:"f914";s:21:"pi1/internal_link.gif";s:4:"12b9";s:32:"pi1/internal_link_new_window.gif";s:4:"402a";s:17:"pi1/locallang.xml";s:4:"2a3c";s:36:"pi1/tx_srfeuserregister_htmlmail.css";s:4:"0570";s:42:"pi1/tx_srfeuserregister_htmlmail_xhtml.css";s:4:"f65b";s:41:"pi1/tx_srfeuserregister_pi1_css_tmpl.html";s:4:"214b";s:38:"pi1/tx_srfeuserregister_pi1_sample.txt";s:4:"297e";s:37:"pi1/tx_srfeuserregister_pi1_tmpl.tmpl";s:4:"c259";s:42:"view/class.tx_srfeuserregister_display.php";s:4:"33eb";s:45:"control/class.tx_srfeuserregister_control.php";s:4:"c008";s:46:"control/class.tx_srfeuserregister_setfixed.php";s:4:"e156";s:48:"hooks/class.tx_srfeuserregister_hooksHandler.php";s:4:"d960";s:47:"model/class.tx_srfeuserregister_controldata.php";s:4:"955a";s:40:"model/class.tx_srfeuserregister_data.php";s:4:"6741";s:39:"model/class.tx_srfeuserregister_url.php";s:4:"fa06";s:28:"scripts/jsfunc.updateform.js";s:4:"a9aa";s:43:"marker/class.tx_srfeuserregister_marker.php";s:4:"cf1b";s:31:"static/css_styled/constants.txt";s:4:"1bc4";s:27:"static/css_styled/setup.txt";s:4:"080d";s:30:"static/old_style/constants.txt";s:4:"4f5e";s:30:"static/old_style/editorcfg.txt";s:4:"cfad";s:26:"static/old_style/setup.txt";s:4:"9e26";}',
	'constraints' => array(
		'depends' => array(
			'cms' => '',
			'fh_library' => '0.0.4-',
			'static_info_tables' => '2.0.5-',
			'php' => '4.1.0-0.0.0',
			'typo3' => '4.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>