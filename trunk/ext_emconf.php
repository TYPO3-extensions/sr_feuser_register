<?php

########################################################################
# Extension Manager/Repository config file for ext "sr_feuser_register".
#
# Auto generated 04-05-2012 15:13
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Front End User Registration',
	'description' => 'A self-registration variant of Kasper Skårhøj\'s Front End User Admin extension.',
	'category' => 'plugin',
	'shy' => 0,
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
	'version' => '3.0.1',
	'_md5_values_when_last_written' => 'a:55:{s:9:"ChangeLog";s:4:"9c06";s:16:"contributors.txt";s:4:"14b1";s:16:"ext_autoload.php";s:4:"83d0";s:21:"ext_conf_template.txt";s:4:"9f3a";s:12:"ext_icon.gif";s:4:"ad8f";s:17:"ext_localconf.php";s:4:"99e2";s:14:"ext_tables.php";s:4:"4104";s:14:"ext_tables.sql";s:4:"3a13";s:13:"locallang.xml";s:4:"ee5f";s:16:"locallang_db.xml";s:4:"9f44";s:7:"tca.php";s:4:"aad1";s:45:"control/class.tx_srfeuserregister_control.php";s:4:"47cc";s:50:"control/class.tx_srfeuserregister_control_main.php";s:4:"b82a";s:46:"control/class.tx_srfeuserregister_setfixed.php";s:4:"ae60";s:14:"doc/manual.sxw";s:4:"0a2b";s:48:"hooks/class.tx_srfeuserregister_hooksHandler.php";s:4:"b426";s:45:"hooks/class.tx_srfeuserregister_hooks_cms.php";s:4:"e912";s:51:"hooks/captcha/class.tx_srfeuserregister_captcha.php";s:4:"a2bc";s:51:"hooks/freecap/class.tx_srfeuserregister_freecap.php";s:4:"6344";s:61:"hooks/statusreport/class.tx_srfeuserregister_statusReport.php";s:4:"b12f";s:36:"hooks/statusreport/ext_localconf.php";s:4:"91d6";s:32:"hooks/statusreport/locallang.xlf";s:4:"11b1";s:38:"lib/class.tx_srfeuserregister_auth.php";s:4:"6031";s:38:"lib/class.tx_srfeuserregister_conf.php";s:4:"5775";s:39:"lib/class.tx_srfeuserregister_email.php";s:4:"fde8";s:38:"lib/class.tx_srfeuserregister_lang.php";s:4:"bac6";s:44:"lib/class.tx_srfeuserregister_lib_tables.php";s:4:"355d";s:37:"lib/class.tx_srfeuserregister_tca.php";s:4:"2d7e";s:43:"marker/class.tx_srfeuserregister_marker.php";s:4:"8de6";s:47:"model/class.tx_srfeuserregister_controldata.php";s:4:"59e5";s:40:"model/class.tx_srfeuserregister_data.php";s:4:"ec94";s:49:"model/class.tx_srfeuserregister_model_feusers.php";s:4:"48fd";s:50:"model/class.tx_srfeuserregister_model_setfixed.php";s:4:"afc0";s:52:"model/class.tx_srfeuserregister_model_table_base.php";s:4:"6843";s:52:"model/class.tx_srfeuserregister_storage_security.php";s:4:"5c13";s:57:"model/class.tx_srfeuserregister_transmission_security.php";s:4:"c432";s:39:"model/class.tx_srfeuserregister_url.php";s:4:"0d5e";s:58:"model/field/class.tx_srfeuserregister_model_field_base.php";s:4:"ab6f";s:63:"model/field/class.tx_srfeuserregister_model_field_usergroup.php";s:4:"f55e";s:37:"pi1/class.tx_srfeuserregister_pi1.php";s:4:"2ef3";s:42:"pi1/class.tx_srfeuserregister_pi1_base.php";s:4:"9b42";s:23:"pi1/flexform_ds_pi1.xml";s:4:"3c93";s:19:"pi1/icon_delete.gif";s:4:"f914";s:21:"pi1/internal_link.gif";s:4:"12b9";s:32:"pi1/internal_link_new_window.gif";s:4:"402a";s:17:"pi1/locallang.xml";s:4:"fcc9";s:42:"pi1/tx_srfeuserregister_htmlmail_xhtml.css";s:4:"f65b";s:41:"pi1/tx_srfeuserregister_pi1_css_tmpl.html";s:4:"695b";s:38:"pi1/tx_srfeuserregister_pi1_sample.txt";s:4:"297e";s:37:"pi1/tx_srfeuserregister_pi1_terms.txt";s:4:"1cac";s:28:"scripts/jsfunc.updateform.js";s:4:"aef6";s:18:"scripts/rsaauth.js";s:4:"ec71";s:31:"static/css_styled/constants.txt";s:4:"67c9";s:27:"static/css_styled/setup.txt";s:4:"9db8";s:42:"view/class.tx_srfeuserregister_display.php";s:4:"4fec";}',
	'constraints' => array(
		'depends' => array(
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.4.0-4.7.99',
			'cms' => '',
			'static_info_tables' => '2.3.0-',
			'div2007' => '0.10.1-',
		),
		'conflicts' => array(
			'germandates' => '0.0.0-1.0.1',
			'rlmp_language_detection' => '0.0.0-1.2.99',
			'newloginbox' => '',
			'kb_md5fepw' => '',
			'srfeuserregister_t3secsaltedpw' => '',
			'patch1822' => '',
			'cc_random_image' => ''
		),
		'suggests' => array(
			'addons_feusers' => '',
			'felogin' => '',
			'rsaauth' => '',
			'saltedpasswords' => '',
			'sr_freecap' => '1.5.3-',
		),
	),
	'suggests' => array(
	),
);

?>