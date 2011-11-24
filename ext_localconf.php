<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

$typoVersion = class_exists('t3lib_utility_VersionNumber') ? t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) : t3lib_div::int_from_ver(TYPO3_version);


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

if (t3lib_extMgm::isLoaded(DIV2007_EXTkey)) {
	if (!defined ('PATH_BE_div2007')) {
		define('PATH_BE_div2007', t3lib_extMgm::extPath(DIV2007_EXTkey));
	}
} else {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] = 0;
}

if (TYPO3_MODE=='BE') {

	if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms'] && defined('PATH_BE_div2007'))	{
		// replace the output of the former CODE field with the flexform
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][SR_FEUSER_REGISTER_EXTkey.'_pi1'][] = 'EXT:'.SR_FEUSER_REGISTER_EXTkey.'/hooks/class.tx_srfeuserregister_hooks_cms.php:&tx_srfeuserregister_hooks_cms->pmDrawItem';
	}
		// For backward compatibility, test on extension patch1822 (obsolete as of TYPO3 4.4)
		// Next step is to make the extension incompatible with sr_feuser_register
	if (($typoVersion >= 4004000 || t3lib_extMgm::isLoaded('patch1822')) && !defined($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_users']['MENU'])) {
		$tableArray = array('fe_users', 'fe_groups', 'fe_groups_language_overlay');
		foreach ($tableArray as $theTable) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['LLFile'][$theTable] = 'EXT:'.SR_FEUSER_REGISTER_EXTkey.'/locallang.xml';
		}

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_users'] = array (
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'username,usergroup,name,zip,city,email,telephone,gender,uid',
				'icon' => TRUE
			),
			'ext' => array (
				'MENU' => 'm_ext',
				'fList' =>  'username,first_name,last_name,title,date_of_birth,comments',
				'icon' => TRUE
			),
			'country' => array(
				'MENU' => 'm_country',
				'fList' =>  'username,static_info_country,zone,language',
				'icon' => TRUE
			),
			'other' => array(
				'MENU' => 'm_other',
				'fList' =>  'username,www,company,status,image,lastlogin,by_invitation,terms_acknowledged,is_online,module_sys_dmail_html',
				'icon' => TRUE
			)
		);

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_groups'] = array (
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'title,description',
				'icon' => TRUE
			),
			'ext' => array(
				'MENU' => 'm_ext',
				'fList' =>  'title,subgroup,lockToDomain,TSconfig',
				'icon' => TRUE
			)
		);

		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['fe_groups_language_overlay'] = array (
			'default' => array(
				'MENU' => 'm_default',
				'fList' =>  'title,fe_group,sys_language_uid',
				'icon' => TRUE
			)
		);
	}
}

if (t3lib_extMgm::isLoaded('static_info_tables')) {
	if (!defined ('PATH_BE_static_info_tables')) {
		define('PATH_BE_static_info_tables', t3lib_extMgm::extPath('static_info_tables'));
	}
}

if (t3lib_extMgm::isLoaded('tt_products') && TYPO3_MODE=='FE') {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['extendingTCA'][] = SR_FEUSER_REGISTER_EXTkey;
}

?>