<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

if (!defined ('SR_FEUSER_REGISTER_EXTkey')) {
	define('SR_FEUSER_REGISTER_EXTkey',$_EXTKEY);
}

if (!defined ('PATH_BE_srfeuserregister')) {
	define('PATH_BE_srfeuserregister', t3lib_extMgm::extPath(SR_FEUSER_REGISTER_EXTkey));
}

if (!defined ('PATH_BE_srfeuserregister_rel')) {
	define('PATH_BE_srfeuserregister_rel', t3lib_extMgm::extRelPath(SR_FEUSER_REGISTER_EXTkey));
}

if (!defined ('PATH_FE_srfeuserregister_rel')) {
	define('PATH_FE_srfeuserregister_rel', t3lib_extMgm::siteRelPath(SR_FEUSER_REGISTER_EXTkey));
}

t3lib_extMgm::addPItoST43(SR_FEUSER_REGISTER_EXTkey, 'pi1/class.tx_srfeuserregister_pi1.php', '_pi1', 'list_type', 0);

$_EXTCONF = unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:
$TYPO3_CONF_VARS['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['uploadfolder'] = $_EXTCONF['uploadFolder'] ? $_EXTCONF['uploadFolder'] : 'uploads/tx_srfeuserregister';
$TYPO3_CONF_VARS['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageMaxSize'] = $_EXTCONF['imageMaxSize'] ? $_EXTCONF['imageMaxSize'] : 250;
$TYPO3_CONF_VARS['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageTypes'] = $_EXTCONF['imageTypes'] ? $_EXTCONF['imageTypes'] : 'png,jpeg,jpg,gif,tif,tiff';

	/* Example of configuration of hooks
$TYPO3_CONF_VARS['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
$TYPO3_CONF_VARS['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
	*/

	// turn the use of flexforms on:

if (!defined ('FH_LIBRARY_EXTkey')) {
	define('FH_LIBRARY_EXTkey','fh_library');
}

if (!defined ('STATIC_INFO_TABLES_EXTkey')) {
	define('STATIC_INFO_TABLES_EXTkey','static_info_tables');
}

if (t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
	$TYPO3_CONF_VARS['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] = $_EXTCONF['useFlexforms'];
	if (!defined ('PATH_BE_fh_library')) {
		define('PATH_BE_fh_library', t3lib_extMgm::extPath(FH_LIBRARY_EXTkey));
	}
} else {
	$TYPO3_CONF_VARS['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] = 0;
}

if (t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey)) {
	if (!defined ('PATH_BE_static_info_tables')) {
		define('PATH_BE_static_info_tables', t3lib_extMgm::extPath(STATIC_INFO_TABLES_EXTkey));
	}
}


if (t3lib_extMgm::isLoaded('tt_products')) {
	$TYPO3_CONF_VARS['EXTCONF']['tt_products']['extendingTCA'][] = SR_FEUSER_REGISTER_EXTkey;
}


?>
