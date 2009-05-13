<?php

########################################################################
# Extension Manager/Repository config file for ext: "sr_feuser_register"
#
# Auto generated 13-05-2009 13:40
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
	'dependencies' => 'cms,static_info_tables,div2007',
	'conflicts' => 'germandates,rlmp_language_detection',
	'suggests' => array(
		'0' => 'patch1822',
	),
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
	'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '2.5.22',
	'_md5_values_when_last_written' => 'a:54:{s:9:"ChangeLog";s:4:"79b5";s:16:"contributors.txt";s:4:"c275";s:21:"ext_conf_template.txt";s:4:"b429";s:12:"ext_icon.gif";s:4:"ad8f";s:17:"ext_localconf.php";s:4:"563a";s:14:"ext_tables.php";s:4:"3c3d";s:14:"ext_tables.sql";s:4:"3979";s:13:"locallang.xml";s:4:"ee5f";s:16:"locallang_db.xml";s:4:"098c";s:7:"tca.php";s:4:"aad1";s:14:"doc/manual.sxw";s:4:"afa7";s:38:"lib/class.tx_srfeuserregister_auth.php";s:4:"3fff";s:39:"lib/class.tx_srfeuserregister_email.php";s:4:"03a6";s:38:"lib/class.tx_srfeuserregister_lang.php";s:4:"7b4c";s:44:"lib/class.tx_srfeuserregister_lib_tables.php";s:4:"3aa3";s:45:"lib/class.tx_srfeuserregister_passwordmd5.php";s:4:"da2f";s:37:"lib/class.tx_srfeuserregister_tca.php";s:4:"1801";s:25:"pi1/address_css_tmpl.html";s:4:"a226";s:37:"pi1/class.tx_srfeuserregister_pi1.php";s:4:"1646";s:48:"pi1/class.tx_srfeuserregister_pi1_adodb_time.php";s:4:"06b2";s:42:"pi1/class.tx_srfeuserregister_pi1_base.php";s:4:"f153";s:50:"pi1/class.tx_srfeuserregister_pi1_urlvalidator.php";s:4:"b79f";s:23:"pi1/flexform_ds_pi1.xml";s:4:"abb2";s:19:"pi1/icon_delete.gif";s:4:"f914";s:21:"pi1/internal_link.gif";s:4:"12b9";s:32:"pi1/internal_link_new_window.gif";s:4:"402a";s:17:"pi1/locallang.xml";s:4:"13f1";s:36:"pi1/tx_srfeuserregister_htmlmail.css";s:4:"0570";s:42:"pi1/tx_srfeuserregister_htmlmail_xhtml.css";s:4:"f65b";s:41:"pi1/tx_srfeuserregister_pi1_css_tmpl.html";s:4:"508f";s:38:"pi1/tx_srfeuserregister_pi1_sample.txt";s:4:"297e";s:37:"pi1/tx_srfeuserregister_pi1_tmpl.tmpl";s:4:"97f8";s:42:"view/class.tx_srfeuserregister_display.php";s:4:"3b90";s:45:"control/class.tx_srfeuserregister_control.php";s:4:"b305";s:50:"control/class.tx_srfeuserregister_control_main.php";s:4:"ca5d";s:46:"control/class.tx_srfeuserregister_setfixed.php";s:4:"dcbb";s:48:"hooks/class.tx_srfeuserregister_hooksHandler.php";s:4:"4211";s:45:"hooks/class.tx_srfeuserregister_hooks_cms.php";s:4:"0f79";s:47:"model/class.tx_srfeuserregister_controldata.php";s:4:"b1e9";s:40:"model/class.tx_srfeuserregister_data.php";s:4:"2b84";s:46:"model/class.tx_srfeuserregister_model_conf.php";s:4:"d2b1";s:49:"model/class.tx_srfeuserregister_model_feusers.php";s:4:"cb7e";s:50:"model/class.tx_srfeuserregister_model_setfixed.php";s:4:"08e4";s:52:"model/class.tx_srfeuserregister_model_table_base.php";s:4:"cf66";s:39:"model/class.tx_srfeuserregister_url.php";s:4:"1267";s:58:"model/field/class.tx_srfeuserregister_model_field_base.php";s:4:"efe1";s:63:"model/field/class.tx_srfeuserregister_model_field_usergroup.php";s:4:"3def";s:28:"scripts/jsfunc.updateform.js";s:4:"3552";s:43:"marker/class.tx_srfeuserregister_marker.php";s:4:"c84d";s:31:"static/css_styled/constants.txt";s:4:"cef0";s:27:"static/css_styled/setup.txt";s:4:"4401";s:30:"static/old_style/constants.txt";s:4:"1f68";s:30:"static/old_style/editorcfg.txt";s:4:"cfad";s:26:"static/old_style/setup.txt";s:4:"76da";}',
	'constraints' => array(
		'depends' => array(
			'cms' => '',
			'static_info_tables' => '2.0.5-',
			'php' => '4.2.0-0.0.0',
			'typo3' => '4.0-0.0.0',
			'div2007' => '0.1.19-',
		),
		'conflicts' => array(
			'germandates' => '0.0.0-1.0.1',
			'rlmp_language_detection' => '0.0.0-1.2.99',
		),
		'suggests' => array(
			'patch1822' => '0.0.3-',
		),
	),
);

?>