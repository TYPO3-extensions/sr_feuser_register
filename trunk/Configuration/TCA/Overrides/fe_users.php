<?php
defined('TYPO3_MODE') or die();

/**
 * Setting up country, country subdivision, preferred language, first_name and last_name in fe_users table
 * Adjusting some maximum lengths to conform to specifications of payment gateways (ref.: Authorize.net)
 */
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

$GLOBALS['TCA']['fe_users']['columns']['image']['config']['uploadfolder'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
$GLOBALS['TCA']['fe_users']['columns']['image']['config']['max_size'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageMaxSize'];
$GLOBALS['TCA']['fe_users']['columns']['image']['config']['allowed'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageTypes'];

$addColumnArray = array(
	'cnum' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.cnum',
		'config' => array(
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'static_info_country' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.static_info_country',
		'config' => array(
			'type' => 'input',
			'size' => '5',
			'max' => '3',
			'eval' => '',
			'default' => ''
		)
	),
	'zone' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.zone',
		'config' => array(
			'type' => 'input',
			'size' => '20',
			'max' => '40',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'language' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.language',
		'config' => array(
			'type' => 'input',
			'size' => '4',
			'max' => '2',
			'eval' => '',
			'default' => ''
		)
	),
	'date_of_birth' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.date_of_birth',
		'config' => array(
			'type' => 'input',
			'size' => '10',
			'max' => '20',
			'eval' => 'date',
			'checkbox' => '0',
			'default' => ''
		)
	),
	'gender' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender',
		'config' => array(
			'type' => 'radio',
			'items' => array(
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.99', '99'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.0', '0'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.gender.I.1', '1')
			)
		)
	),
	'status' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status',
		'config' => array(
			'type' => 'select',
			'items' => array(
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.0', '0'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.1', '1'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.2', '2'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.3', '3'),
				array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.status.I.4', '4')
			),
			'size' => 1,
			'maxitems' => 1
		)
	),
	'comments' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.comments',
		'config' => array(
			'type' => 'text',
			'rows' => '5',
			'cols' => '48'
		)
	),
	'by_invitation' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.by_invitation',
		'config' => array(
			'type' => 'check',
			'default' => '0'
		)
	),
	'terms_acknowledged' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.terms_acknowledged',
		'config' => array(
			'type' => 'check',
			'default' => '0',
			'readOnly' => '1'
		)
	),
	'token' => array(
		'exclude' => 1,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.token',
		'config' => array(
			'type' => 'text',
			'rows' => '1',
			'cols' => '32'
		)
	),
	'tx_srfeuserregister_password' => array(
		'exclude' => 1,
		'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.tx_srfeuserregister_password',
		'config' => array(
			'type' => 'passthrough'
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
	)
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $addColumnArray);

$GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList'] = preg_replace('/(^|,)\s*country\s*(,|$)/', '$1zone,static_info_country,country,language$2', $GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList']);
$GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList'] = preg_replace('/(^|,)\s*title\s*(,|$)/', '$1gender,status,date_of_birth,house_no,title$2', $GLOBALS['TCA']['fe_users']['interface']['showRecordFieldList']);

$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*country\s*(,|$)/', '$1 zone, static_info_country, country, language$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);
$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*address\s*(,|$)/', '$1 cnum, status, date_of_birth, house_no, address$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);
$GLOBALS['TCA']['fe_users']['types']['0']['showitem'] = preg_replace('/(^|,)\s*www\s*(,|$)/', '$1 www, comments, by_invitation, terms_acknowledged$2', $GLOBALS['TCA']['fe_users']['types']['0']['showitem']);

$GLOBALS['TCA']['fe_users']['palettes']['2']['showitem'] = 'gender,--linebreak--,' . $GLOBALS['TCA']['fe_users']['palettes']['2']['showitem'];

$GLOBALS['TCA']['fe_users']['ctrl']['thumbnail'] = 'image';

// fe_users further modified when extension direct_mail is not loaded
if (
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['enableDirectMail'] && !\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('direct_mail')
) {
	$addColumnArray = array(
		'module_sys_dmail_newsletter' => array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_newsletter',
			'exclude' => '1',
			'config'=> array(
				'type'=>'check'
			)
		),
		'module_sys_dmail_category' => array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_category',
			'exclude' => '1',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_dmail_category',
				'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
				'itemsProcFunc' => 'tx_srfeuserregister_select_dmcategories->get_localized_categories',
				'itemsProcFunc_config' => array(
					'table' => 'sys_dmail_category',
					'indexField' => 'uid'
				),
				'size' => 5,
				'minitems' => 0,
				'maxitems' => 60,
				'renderMode' => 'checkbox',
				'MM' => 'sys_dmail_feuser_category_mm'
			)
		),
		'module_sys_dmail_html' => array(
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_users.module_sys_dmail_html',
			'exclude' => '1',
			'config'=>Array(
				'type'=>'check'
			)
		)
	);
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $addColumnArray);
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users','--div--;Direct mail,module_sys_dmail_newsletter;;;;1-1-1,module_sys_dmail_category,module_sys_dmail_html');
}
unset($addColumnArray);