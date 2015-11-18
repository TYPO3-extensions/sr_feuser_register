<?php
defined('TYPO3_MODE') or die();

// Unserialize the configuration so we can use it here
$_EXTCONF = unserialize($_EXTCONF);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['uploadfolder'] = $_EXTCONF['uploadFolder'] ? $_EXTCONF['uploadFolder'] : 'uploads/tx_srfeuserregister';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageMaxSize'] = $_EXTCONF['imageMaxSize'] ? $_EXTCONF['imageMaxSize'] : 250;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['imageTypes'] = $_EXTCONF['imageTypes'] ? $_EXTCONF['imageTypes'] : 'png,jpeg,jpg,gif,tif,tiff';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['enableDirectMail'] = $_EXTCONF['enableDirectMail'] ? $_EXTCONF['enableDirectMail'] : 0;

// Configure the plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY, 'Classes/Controller/RegisterPluginController.php', '_pi1', 'list_type', 0);

// Example of configuration of hooks
// $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['confirmRegistrationClass'][] = 'SJBR\\SrFeuserRegister\\Hooks\\Handler';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['registrationProcess'][] = 'SJBR\\SrFeuserRegister\\Hooks\\RegistrationProcessHooks';

// Save extension version and constraints
require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'ext_emconf.php');
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['version'] = $EM_CONF[$_EXTKEY]['version'];
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['constraints'] = $EM_CONF[$_EXTKEY]['constraints'];

// Set possible login security levels
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['loginSecurityLevels'] = array('normal', 'rsa');

// Configure captcha hooks
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['captcha'])) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['captcha'] = array();
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['captcha'][] = 'SJBR\\SrFeuserRegister\\Captcha\\Captcha';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['captcha'][] = 'SJBR\\SrFeuserRegister\\Captcha\\Freecap';

// Configure usergroup hooks
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['configuration'][] = 'SJBR\\SrFeuserRegister\\Hooks\\UsergroupHooks';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['tx_srfeuserregister_pi1']['fe_users']['usergroup'][] = 'SJBR\\SrFeuserRegister\\Hooks\\UsergroupHooks';

if (TYPO3_MODE === 'BE') {
	// Take note of conflicting extensions
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints'] = $EM_CONF['sr_feuser_register']['constraints'];
	// Register Status Report Hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Front End User Registration'][] = 'SJBR\\SrFeuserRegister\\Configuration\\Reports\\StatusProvider';

	if (!defined($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_users']['MENU'])) {
		$tableArray = array('fe_users', 'fe_groups', 'fe_groups_language_overlay');
		foreach ($tableArray as $theTable) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['LLFile'][$theTable] = 'EXT:sr_feuser_register/Resources/Private/Language/locallang_db_layout.xlf';
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