<?php
defined('TYPO3_MODE') or die();

if (!defined ('SR_FEUSER_REGISTER_EXT')) {
	define('SR_FEUSER_REGISTER_EXT', $_EXTKEY);
}

if (!defined ('PATH_BE_srfeuserregister')) {
	define('PATH_BE_srfeuserregister', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY));
}

if (!defined ('PATH_BE_srfeuserregister_rel')) {
	define('PATH_BE_srfeuserregister_rel', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY));
}

if (!defined ('PATH_FE_srfeuserregister_rel')) {
	define('PATH_FE_srfeuserregister_rel', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($_EXTKEY));
}

if (!defined(STATIC_INFO_TABLES_EXT)) {
	define('STATIC_INFO_TABLES_EXT', 'static_info_tables');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY, 'pi1/class.tx_srfeuserregister_pi1.php', '_pi1', 'list_type', 0);

$_EXTCONF =unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['uploadfolder'] = $_EXTCONF['uploadFolder'] ? $_EXTCONF['uploadFolder'] : 'uploads/tx_srfeuserregister';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageMaxSize'] = $_EXTCONF['imageMaxSize'] ? $_EXTCONF['imageMaxSize'] : 250;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageTypes'] = $_EXTCONF['imageTypes'] ? $_EXTCONF['imageTypes'] : 'png,jpeg,jpg,gif,tif,tiff';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['enableDirectMail'] = $_EXTCONF['enableDirectMail'] ? $_EXTCONF['enableDirectMail'] : 0;


	/* Example of configuration of hooks */
/*
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';
*/

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php:&tx_srfeuserregister_hooksHandler';

	// Save extension version and constraints
require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'ext_emconf.php');
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['version'] = $EM_CONF[$_EXTKEY]['version'];
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['constraints'] = $EM_CONF[$_EXTKEY]['constraints'];

	// Set path to extension static_info_tables
if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
	if (!defined ('PATH_BE_static_info_tables')) {
		define('PATH_BE_static_info_tables', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('static_info_tables'));
	}
}


$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['loginSecurityLevels'] = array('normal', 'rsa');

	// Captcha marker hook
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/captcha/class.tx_srfeuserregister_captcha.php:&tx_srfeuserregister_captcha';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['tx_srfeuserregister_pi1']['model'][] = 'EXT:sr_feuser_register/hooks/captcha/class.tx_srfeuserregister_captcha.php:&tx_srfeuserregister_captcha';
	// Freecap marker hook
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['tx_srfeuserregister_pi1']['registrationProcess'][] = 'EXT:sr_feuser_register/hooks/freecap/class.tx_srfeuserregister_freecap.php:&tx_srfeuserregister_freecap';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['tx_srfeuserregister_pi1']['model'][] = 'EXT:sr_feuser_register/hooks/freecap/class.tx_srfeuserregister_freecap.php:&tx_srfeuserregister_freecap';

if (TYPO3_MODE === 'BE') {
	
	// Add Status Report
	// Take note of conflicting extensions
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints'] = $EM_CONF['sr_feuser_register']['constraints'];
	// Register Status Report Hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Front End User Registration'][] = 'SJBR\\SrFeuserRegister\\Reports\\StatusProvider';

	if (!defined($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_users']['MENU'])) {
		$tableArray = array('fe_users', 'fe_groups', 'fe_groups_language_overlay');
		foreach ($tableArray as $theTable) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['LLFile'][$theTable] = 'EXT:sr_feuser_register/Resources/Private/Language/locallang.xlf';
		}

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_users'] = array(
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'username,usergroup,name,cnum,zip,city,email,telephone,gender,uid',
				'icon' => true
			),
			'ext' => array (
				'MENU' => 'm_ext',
				'fList' =>  'username,first_name,middle_name,last_name,title,date_of_birth,comments',
				'icon' => true
			),
			'country' => array(
				'MENU' => 'm_country',
				'fList' =>  'username,static_info_country,zone,language',
				'icon' => true
			),
			'other' => array(
				'MENU' => 'm_other',
				'fList' =>  'username,www,company,status,image,lastlogin,by_invitation,terms_acknowledged,is_online,module_sys_dmail_html',
				'icon' => true
			)
		);

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_groups'] = array(
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'title,description',
				'icon' => true
			),
			'ext' => array(
				'MENU' => 'm_ext',
				'fList' =>  'title,subgroup,lockToDomain,TSconfig',
				'icon' => true
			)
		);

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_groups_language_overlay'] = array(
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'title,fe_group,sys_language_uid',
				'icon' => true
			)
		);
	}
}