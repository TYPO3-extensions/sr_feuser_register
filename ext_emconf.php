<?php

########################################################################
# Extension Manager/Repository config file for ext: "sr_feuser_register"
#
# Auto generated 01-04-2008 21:00
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Frontend User Registration',
	'description' => 'A self-registration variant of Kasper Skårhøj\'s Front End User Admin extension.',
	'category' => 'plugin',
	'shy' => 0,
	'dependencies' => 'cms,static_info_tables',
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
	'author_email' => 'contact@fholzinger.com',
	'author_company' => 'jambage.com',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '2.5.9',
	'_md5_values_when_last_written' => 'a:44:{s:9:"ChangeLog";s:4:"9821";s:16:"contributors.txt";s:4:"c275";s:21:"ext_conf_template.txt";s:4:"3cde";s:12:"ext_icon.gif";s:4:"ad8f";s:17:"ext_localconf.php";s:4:"ac32";s:14:"ext_tables.php";s:4:"8564";s:14:"ext_tables.sql";s:4:"545a";s:13:"locallang.xml";s:4:"f26f";s:16:"locallang_db.xml";s:4:"ec00";s:7:"tca.php";s:4:"aad1";s:14:"doc/manual.sxw";s:4:"7b73";s:38:"lib/class.tx_srfeuserregister_auth.php";s:4:"47f6";s:39:"lib/class.tx_srfeuserregister_email.php";s:4:"26ab";s:38:"lib/class.tx_srfeuserregister_lang.php";s:4:"4c52";s:45:"lib/class.tx_srfeuserregister_passwordmd5.php";s:4:"a0f3";s:37:"lib/class.tx_srfeuserregister_tca.php";s:4:"7cc6";s:37:"pi1/class.tx_srfeuserregister_pi1.php";s:4:"789f";s:48:"pi1/class.tx_srfeuserregister_pi1_adodb_time.php";s:4:"a7cd";s:50:"pi1/class.tx_srfeuserregister_pi1_urlvalidator.php";s:4:"9874";s:23:"pi1/flexform_ds_pi1.xml";s:4:"abb2";s:19:"pi1/icon_delete.gif";s:4:"f914";s:21:"pi1/internal_link.gif";s:4:"12b9";s:32:"pi1/internal_link_new_window.gif";s:4:"402a";s:17:"pi1/locallang.xml";s:4:"4605";s:36:"pi1/tx_srfeuserregister_htmlmail.css";s:4:"0570";s:42:"pi1/tx_srfeuserregister_htmlmail_xhtml.css";s:4:"f65b";s:41:"pi1/tx_srfeuserregister_pi1_css_tmpl.html";s:4:"02df";s:38:"pi1/tx_srfeuserregister_pi1_sample.txt";s:4:"297e";s:37:"pi1/tx_srfeuserregister_pi1_tmpl.tmpl";s:4:"ac0b";s:42:"view/class.tx_srfeuserregister_display.php";s:4:"0a22";s:45:"control/class.tx_srfeuserregister_control.php";s:4:"2c0d";s:46:"control/class.tx_srfeuserregister_setfixed.php";s:4:"d3e5";s:48:"hooks/class.tx_srfeuserregister_hooksHandler.php";s:4:"975f";s:45:"hooks/class.tx_srfeuserregister_hooks_cms.php";s:4:"8a3e";s:47:"model/class.tx_srfeuserregister_controldata.php";s:4:"8deb";s:40:"model/class.tx_srfeuserregister_data.php";s:4:"4f26";s:39:"model/class.tx_srfeuserregister_url.php";s:4:"28a8";s:28:"scripts/jsfunc.updateform.js";s:4:"3552";s:43:"marker/class.tx_srfeuserregister_marker.php";s:4:"7706";s:31:"static/css_styled/constants.txt";s:4:"c01c";s:27:"static/css_styled/setup.txt";s:4:"a956";s:30:"static/old_style/constants.txt";s:4:"2745";s:30:"static/old_style/editorcfg.txt";s:4:"cfad";s:26:"static/old_style/setup.txt";s:4:"e3f3";}',
	'constraints' => array(
		'depends' => array(
			'cms' => '',
			'static_info_tables' => '2.0.5-',
			'php' => '4.2.0-0.0.0',
			'typo3' => '4.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
			'div2007' => '0.1.2-',
		),
	),
	'suggests' => array(
	),
);

?>