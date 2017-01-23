<?php
defined('TYPO3_MODE') or die();

// Get the extensions's configuration
$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sr_feuser_register']);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'] = $extConf['uploadFolder'] ? $extConf['uploadFolder'] : '2:/tx_srfeuserregister/';
if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getNumericTypo3Version()) < 8000000) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'] = $extConf['uploadFolder'] ? $extConf['uploadFolder'] : 'uploads/tx_srfeuserregister';
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageMaxSize'] = $extConf['imageMaxSize'] ? $extConf['imageMaxSize'] : 7168;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageTypes'] = $extConf['imageTypes'] ? $extConf['imageTypes'] : 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,ai,svg';

// Example of configuration of hooks
// $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'SJBR\\SrFeuserRegister\\Hooks\\Handler';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = \SJBR\SrFeuserRegister\Hooks\RegistrationProcessHooks::class;

// Save extension version and constraints
$emConfUtility = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extensionmanager\Utility\EmConfUtility::class);
$emConf = $emConfUtility->includeEmConf(['key' => 'sr_feuser_register', 'siteRelPath' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('sr_feuser_register')]);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['version'] = $emConf['sr_feuser_register']['version'];
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints'] = $emConf['sr_feuser_register']['constraints'];

// Set possible login security levels
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['loginSecurityLevels'] = array('normal', 'rsa');

// Configure captcha hooks
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['captcha'])) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['captcha'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['captcha'][] = \SJBR\SrFeuserRegister\Captcha\Captcha::class;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['captcha'][] = \SJBR\SrFeuserRegister\Captcha\Freecap::class;

// Configure usergroup hooks
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['configuration'][] = \SJBR\SrFeuserRegister\Hooks\UsergroupHooks::class;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['fe_users']['usergroup'][] = \SJBR\SrFeuserRegister\Hooks\UsergroupHooks::class;

// Configure upload hooks
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['model'])) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['model'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['model'][] = \SJBR\SrFeuserRegister\Hooks\FileUploadHooks::class;

if (TYPO3_MODE === 'BE') {
	// Take note of conflicting extensions
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints'] = $emConf['sr_feuser_register']['constraints'];
	// Register Status Report Hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Front End User Registration'][] = 'SJBR\\SrFeuserRegister\\Configuration\\Reports\\StatusProvider';
}
unset($extConf);
unset($emConfUtility);
unset($emConf);