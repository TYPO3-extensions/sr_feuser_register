<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (class_exists('t3lib_utility_VersionNumber')) {
	$typo3Version = t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version);
} else if (class_exists('TYPO3\\CMS\\Core\\Utility\\VersionNumberUtility')) {
	$typo3Version = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
}

if (TYPO3_MODE == 'BE' && !$loadTcaAdditions) {

	t3lib_extMgm::addStaticFile(SR_FEUSER_REGISTER_EXT, 'static/css_styled/', 'FE User Registration CSS-styled');

	if ($typo3Version < 6001000) {
		t3lib_div::loadTCA('tt_content');
	}
	$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][SR_FEUSER_REGISTER_EXT.'_pi1']='layout,select_key';
	$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][SR_FEUSER_REGISTER_EXT.'_pi1']='pi_flexform';
	t3lib_extMgm::addPiFlexFormValue(SR_FEUSER_REGISTER_EXT.'_pi1', 'FILE:EXT:'.SR_FEUSER_REGISTER_EXT.'/pi1/flexform_ds_pi1.xml');
	t3lib_extMgm::addPlugin(Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:tt_content.list_type', SR_FEUSER_REGISTER_EXT.'_pi1'),'list_type');
}


/**
 * Setting up country, country subdivision, preferred language, first_name and last_name in fe_users table
 * Adjusting some maximum lengths to conform to specifications of payment gateways (ref.: Authorize.net)
 */
if ($typo3Version < 6001000) {
	t3lib_div::loadTCA('fe_users');
}

$GLOBALS['TCA']['fe_users']['columns']['username']['config']['eval'] = 'nospace,uniqueInPid,required';
$GLOBALS['TCA']['fe_users']['columns']['name']['config']['max'] = '100';
$GLOBALS['TCA']['fe_users']['columns']['company']['config']['max'] = '50';
$GLOBALS['TCA']['fe_users']['columns']['city']['config']['max'] = '40';
$GLOBALS['TCA']['fe_users']['columns']['country']['config']['max'] = '60';
$GLOBALS['TCA']['fe_users']['columns']['zip']['config']['size'] = '15';
$GLOBALS['TCA']['fe_users']['columns']['zip']['config']['max'] = '20';
$GLOBALS['TCA']['fe_users']['columns']['email']['config']['max'] = '255';
$GLOBALS['TCA']['fe_users']['columns']['telephone']['config']['max'] = '25';
$GLOBALS['TCA']['fe_users']['columns']['fax']['config']['max'] = '25';


$GLOBALS['TCA']['fe_users']['columns']['image']['config']['uploadfolder'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['uploadfolder'];
$GLOBALS['TCA']['fe_users']['columns']['image']['config']['max_size'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['imageMaxSize'];
$GLOBALS['TCA']['fe_users']['columns']['image']['config']['allowed'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['imageTypes'];

$addColumnArray = Array(
	'cnum' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.cnum',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'static_info_country' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.static_info_country',
		'config' => Array (
			'type' => 'input',
			'size' => '5',
			'max' => '3',
			'eval' => '',
			'default' => ''
		)
	),
	'zone' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.zone',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '40',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'language' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.language',
		'config' => Array (
			'type' => 'input',
			'size' => '4',
			'max' => '2',
			'eval' => '',
			'default' => ''
		)
	),
	'date_of_birth' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.date_of_birth',
		'config' => Array (
			'type' => 'input',
			'size' => '10',
			'max' => '20',
			'eval' => 'date',
			'checkbox' => '0',
			'default' => ''
		)
	),
	'gender' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender',
		'config' => Array (
			'type' => 'radio',
			'items' => Array (
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.99', '99'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.0', '0'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.1', '1')
			),
		)
	),
	'status' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status',
		'config' => Array (
			'type' => 'select',
			'items' => Array (
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.0', '0'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.1', '1'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.2', '2'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.3', '3'),
				Array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.4', '4'),
			),
			'size' => 1,
			'maxitems' => 1,
		)
	),
	'comments' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.comments',
		'config' => Array (
			'type' => 'text',
			'rows' => '5',
			'cols' => '48'
		)
	),
	'by_invitation' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.by_invitation',
		'config' => Array (
			'type' => 'check',
			'default' => '0'
		)
	),
	'terms_acknowledged' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.terms_acknowledged',
		'config' => Array (
			'type' => 'check',
			'default' => '0',
			'readOnly' => '1',
		)
	),
	'token' => Array (
		'exclude' => 1,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.token',
		'config' => Array (
			'type' => 'text',
			'rows' => '1',
			'cols' => '32'
		)
	),
	'tx_srfeuserregister_password' => array (
		'exclude' => 1,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.tx_srfeuserregister_password',
		'config' => array (
			'type' => 'passthrough',
		)
	),
	'house_no' => array(
		'exclude' => 1,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.house_no',
		'config' => array(
			'type' => 'input',
			'eval' => 'trim',
			'size' => '20',
			'max' => '20'
		)
	),
);
t3lib_extMgm::addTCAcolumns('fe_users', $addColumnArray);

$GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList'] = preg_replace('/(^|,)\s*country\s*(,|$)/', '$1zone,static_info_country,country,language$2', $GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList']);
$GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList'] = preg_replace('/(^|,)\s*title\s*(,|$)/', '$1gender,status,date_of_birth,house_no,title$2', $GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList']);

$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*country\s*(,|$)/', '$1 zone, static_info_country, country, language$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);
$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*address\s*(,|$)/', '$1 cnum, status, date_of_birth, house_no, address$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);
$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*www\s*(,|$)/', '$1 www, comments, by_invitation, terms_acknowledged$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);

$GLOBALS['TCA']['fe_users']['palettes']['2']['showitem'] = 'gender,--linebreak--,' . $GLOBALS['TCA']['fe_users']['palettes']['2']['showitem'];


$GLOBALS['TCA']['fe_users']['ctrl']['thumbnail'] = 'image';


	// fe_users modified
if (
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['enableDirectMail'] &&
	!t3lib_extMgm::isLoaded('direct_mail')
) {
	$tempCols = Array(
		'module_sys_dmail_newsletter' => Array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_newsletter',
			'exclude' => '1',
			'config'=>Array(
				'type'=>'check'
				)
			),
		'module_sys_dmail_category' => Array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_category',
			'exclude' => '1',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_dmail_category',
				'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
				'itemsProcFunc' => 'tx_srfeuserregister_select_dmcategories->get_localized_categories',
				'itemsProcFunc_config' => array (
					'table' => 'sys_dmail_category',
					'indexField' => 'uid',
				),
				'size' => 5,
				'minitems' => 0,
				'maxitems' => 60,
				'renderMode' => 'checkbox',
				'MM' => 'sys_dmail_feuser_category_mm',
			)
		),
		'module_sys_dmail_html' => Array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_html',
			'exclude' => '1',
			'config'=>Array(
				'type'=>'check'
			)
		)
	);
	t3lib_extMgm::addTCAcolumns('fe_users', $tempCols);
	t3lib_extMgm::addToAllTCATypes('fe_users','--div--;Direct mail,module_sys_dmail_newsletter;;;;1-1-1,module_sys_dmail_category,module_sys_dmail_html');
}

$GLOBALS['TCA']['fe_groups_language_overlay'] = Array (
	'ctrl' => Array (
 	'title' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_groups_language_overlay',
		'label' => 'title',
		'default_sortby' => 'ORDER BY fe_groups_uid',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => Array (
			'disabled' => 'hidden'
		),
 		'dynamicConfigFile' => t3lib_extMgm::extPath(SR_FEUSER_REGISTER_EXT).'tca.php',
		'iconfile' => 'gfx/i/fe_groups.gif',
		)
);
t3lib_extMgm::allowTableOnStandardPages('fe_groups_language_overlay');
t3lib_extMgm::addToInsertRecords('fe_groups_language_overlay');
unset($typo3Version);

?>