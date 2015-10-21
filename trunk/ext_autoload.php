<?php
/*
 * Register necessary class names with autoloader
 *
 * $Id: ext_autoload.php $
 */
$sr_feuser_registerExtensionPath = t3lib_extMgm::extPath('sr_feuser_register');
return array(
	'tx_srfeuserregister_hooks_cms' => $sr_feuser_registerExtensionPath . 'hooks/class.tx_srfeuserregister_hooks_cms.php',
	'tx_srfeuserregister_hookshandler' => $sr_feuser_registerExtensionPath . 'hooks/class.tx_srfeuserregister_hooksHandler.php',
	'tx_srfeuserregister_control_main' => $sr_feuser_registerExtensionPath . 'control/class.tx_srfeuserregister_control_main.php',
	'tx_srfeuserregister_control' => $sr_feuser_registerExtensionPath . 'control/class.tx_srfeuserregister_control.php',
	'tx_srfeuserregister_setfixed' => $sr_feuser_registerExtensionPath . 'control/class.tx_srfeuserregister_setfixed.php',
	'tx_srfeuserregister_auth' => $sr_feuser_registerExtensionPath . 'lib/class.tx_srfeuserregister_auth.php',
	'tx_srfeuserregister_conf' => $sr_feuser_registerExtensionPath . 'lib/class.tx_srfeuserregister_conf.php',
	'tx_srfeuserregister_email' => $sr_feuser_registerExtensionPath . 'lib/class.tx_srfeuserregister_email.php',
	'tx_srfeuserregister_lib_tables' => $sr_feuser_registerExtensionPath . 'lib/class.tx_srfeuserregister_lib_tables.php',
	'tx_srfeuserregister_tca' => $sr_feuser_registerExtensionPath . 'lib/class.tx_srfeuserregister_tca.php',
	'tx_srfeuserregister_marker' => $sr_feuser_registerExtensionPath . 'marker/class.tx_srfeuserregister_marker.php',
	'tx_srfeuserregister_controldata' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_controldata.php',
	'tx_srfeuserregister_data' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_data.php',
	'tx_srfeuserregister_model_feusers' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_model_feusers.php',
	'tx_srfeuserregister_model_setfixed' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_model_setfixed.php',
	'tx_srfeuserregister_model_table_base' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_model_table_base.php',
	'tx_srfeuserregister_url' => $sr_feuser_registerExtensionPath . 'model/class.tx_srfeuserregister_url.php',
	'tx_srfeuserregister_model_field_base' => $sr_feuser_registerExtensionPath . 'model/field/class.tx_srfeuserregister_model_field_base.php',
	'tx_srfeuserregister_model_field_usergroup' => $sr_feuser_registerExtensionPath . 'model/field/class.tx_srfeuserregister_model_field_usergroup.php',
	'tx_srfeuserregister_dmstatic' => $sr_feuser_registerExtensionPath . 'scripts/class.tx_srfeuserregister_dmstatic.php',
	'tx_srfeuserregister_select_dmcategories' => $sr_feuser_registerExtensionPath . 'scripts/class.tx_srfeuserregister_select_dmcategories.php',
	'tx_srfeuserregister_display' => $sr_feuser_registerExtensionPath . 'view/class.tx_srfeuserregister_display.php',
	'tx_srfeuserregister_utility_urlutility' => $sr_feuser_registerExtensionPath . 'Classes/Utility/UrlUtility.php',
);