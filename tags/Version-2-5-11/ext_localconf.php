<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

if (!defined ('SR_FEUSER_REGISTER_EXTkey')) {
	define('SR_FEUSER_REGISTER_EXTkey',$_EXTKEY);
}

if (!defined ('DIV2007_EXTkey')) {
	define('DIV2007_EXTkey','div2007');
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
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['uploadfolder'] = $_EXTCONF['uploadFolder'] ? $_EXTCONF['uploadFolder'] : 'uploads/tx_srfeuserregister';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageMaxSize'] = $_EXTCONF['imageMaxSize'] ? $_EXTCONF['imageMaxSize'] : 250;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageTypes'] = $_EXTCONF['imageTypes'] ? $_EXTCONF['imageTypes'] : 'png,jpeg,jpg,gif,tif,tiff';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useMd5Password'] = $_EXTCONF['useMd5Password'] ? $_EXTCONF['useMd5Password'] : 0;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] = $_EXTCONF['useFlexforms'];

	/* Example of configuration of hooks */
/*
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
*/

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';

if (!defined ('STATIC_INFO_TABLES_EXTkey')) {
	define('STATIC_INFO_TABLES_EXTkey','static_info_tables');
}

$bPhp5 = version_compare(phpversion(), '5.0.0', '>=');
if (t3lib_extMgm::isLoaded(DIV2007_EXTkey)) {
	if (!defined ('PATH_BE_div2007')) {
		define('PATH_BE_div2007', t3lib_extMgm::extPath(DIV2007_EXTkey));
	}
} else {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] = 0;
}

if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] && $bPhp5)	{
	// replace the output of the former CODE field with the flexform
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][SR_FEUSER_REGISTER_EXTkey.'_pi1'][] = 'EXT:'.SR_FEUSER_REGISTER_EXTkey.'/hooks/class.tx_srfeuserregister_hooks_cms.php:&tx_srfeuserregister_hooks_cms->pmDrawItem';
}

if (t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey)) {
	if (!defined ('PATH_BE_static_info_tables')) {
		define('PATH_BE_static_info_tables', t3lib_extMgm::extPath(STATIC_INFO_TABLES_EXTkey));
	}
}

if (t3lib_extMgm::isLoaded('tt_products')) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['extendingTCA'][] = SR_FEUSER_REGISTER_EXTkey;
}

?>
