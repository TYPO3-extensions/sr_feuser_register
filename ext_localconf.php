<?php
defined('TYPO3_MODE') or die();

call_user_func(
    function($extKey)
    {
    	$extConf = (bool)\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get($extKey);
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['uploadfolder'] = $extConf['uploadFolder'] ? $extConf['uploadFolder'] : '2:/tx_srfeuserregister/';
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['imageMaxSize'] = $extConf['imageMaxSize'] ? $extConf['imageMaxSize'] : 7168;
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['imageTypes'] = $extConf['imageTypes'] ? $extConf['imageTypes'] : 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,ai,svg';
		// Example of configuration of hooks
		// $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'SJBR\\SrFeuserRegister\\Hooks\\Handler';
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['registrationProcess'][] = \SJBR\SrFeuserRegister\Hooks\RegistrationProcessHooks::class;
		// Save extension version and constraints
		$emConfUtility = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extensionmanager\Utility\EmConfUtility::class);
		$emConf = $emConfUtility->includeEmConf(['key' => $extKey, 'siteRelPath' =>  \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extKey))]);
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['version'] = $emConf[$extKey]['version'];
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['constraints'] = $emConf[$extKey]['constraints'];
		// Set possible login security levels
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['loginSecurityLevels'] = array('normal', 'rsa');
		// Configure captcha hooks
		if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'])) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'] = [];
		}
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'][] = \SJBR\SrFeuserRegister\Captcha\Captcha::class;
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'][] = \SJBR\SrFeuserRegister\Captcha\Freecap::class;
		// Configure usergroup hooks
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['configuration'][] = \SJBR\SrFeuserRegister\Hooks\UsergroupHooks::class;
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['fe_users']['usergroup'][] = \SJBR\SrFeuserRegister\Hooks\UsergroupHooks::class;
		// Configure upload hooks
		if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['model'])) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['model'] = [];
		}
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['tx_srfeuserregister_pi1']['model'][] = \SJBR\SrFeuserRegister\Hooks\FileUploadHooks::class;
		if (TYPO3_MODE === 'BE') {
			// Take note of conflicting extensions
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['constraints'] = $emConf[$extKey]['constraints'];
			// Register Status Report Hook
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Front End User Registration'][] = 'SJBR\\SrFeuserRegister\\Configuration\\Reports\\StatusProvider';
		}
	},
	'sr_feuser_register'
);