<?php
	if (!defined ('TYPO3_MODE')) die ('Access denied.');
	
	t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_srfeuserregister_pi1.php', '_pi1', 'list_type', 0);
	
	$_EXTCONF = unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['uploadFolder'] = $_EXTCONF['uploadFolder'] ? $_EXTCONF['uploadFolder'] : 'uploads/tx_srfeuserregister';
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageMaxSize'] = $_EXTCONF['imageMaxSize'] ? $_EXTCONF['imageMaxSize'] : 250;
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageTypes'] = $_EXTCONF['imageTypes'] ? $_EXTCONF['imageTypes'] : 'png,jpeg,jpg,gif,tif,tiff';
	
		/* Example of configuration of hooks
	$TYPO3_CONF_VARS['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
	$TYPO3_CONF_VARS['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
		*/
?>
