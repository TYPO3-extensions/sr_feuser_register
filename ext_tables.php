<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::addStaticFile(SR_FEUSER_REGISTER_EXTkey, 'static/css_styled/', 'FE User Registration CSS-styled');
t3lib_extMgm::addStaticFile(SR_FEUSER_REGISTER_EXTkey, 'static/old_style/', 'FE User Registration Old Style');

t3lib_div::loadTCA('tt_content');

if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useFlexforms']==1) {
	$TCA['tt_content']['types']['list']['subtypes_excludelist'][SR_FEUSER_REGISTER_EXTkey.'_pi1']='layout,select_key';
	$TCA['tt_content']['types']['list']['subtypes_addlist'][SR_FEUSER_REGISTER_EXTkey.'_pi1']='pi_flexform';
	t3lib_extMgm::addPiFlexFormValue(SR_FEUSER_REGISTER_EXTkey.'_pi1', 'FILE:EXT:'.SR_FEUSER_REGISTER_EXTkey.'/pi1/flexform_ds_pi1.xml');
} else {
	$TCA['tt_content']['types']['list']['subtypes_excludelist'][SR_FEUSER_REGISTER_EXTkey.'_pi1'] = 'layout';
}

t3lib_extMgm::addPlugin(Array('LLL:EXT:'.SR_FEUSER_REGISTER_EXTkey.'/locallang_db.xml:tt_content.list_type', SR_FEUSER_REGISTER_EXTkey.'_pi1'),'list_type');

/**
 * Setting up country, country subdivision, preferred language, first_name and last_name in fe_users table
 * Adjusting some maximum lengths to conform to specifications of payment gateways (ref.: Authorize.net)
 */
t3lib_div::loadTCA('fe_users');
$TCA['fe_users']['columns']['username']['config']['eval'] = 'nospace,uniqueInPid,required';

if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['useMd5Password'] && strstr($TCA['fe_users']['columns']['password']['config']['eval'], 'md5')) {
	$TCA['fe_users']['columns']['password']['config']['eval'] = 'nospace,required,md5,password';
} else {
	$TCA['fe_users']['columns']['password']['config']['eval'] = 'nospace,required';
}

$TCA['fe_users']['columns']['name']['config']['max'] = '100';
$TCA['fe_users']['columns']['company']['config']['max'] = '50';
$TCA['fe_users']['columns']['city']['config']['max'] = '40';
$TCA['fe_users']['columns']['country']['config']['max'] = '60';
$TCA['fe_users']['columns']['zip']['config']['size'] = '15';
$TCA['fe_users']['columns']['zip']['config']['max'] = '20';
$TCA['fe_users']['columns']['email']['config']['max'] = '255';
$TCA['fe_users']['columns']['telephone']['config']['max'] = '25';
$TCA['fe_users']['columns']['fax']['config']['max'] = '25';


$TCA['fe_users']['columns']['image']['config']['uploadfolder'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['uploadfolder'];
$TCA['fe_users']['columns']['image']['config']['max_size'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageMaxSize'];
$TCA['fe_users']['columns']['image']['config']['allowed'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXTkey]['imageTypes'];

t3lib_extMgm::addTCAcolumns('fe_users', Array(
	'static_info_country' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.static_info_country',
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
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.zone',
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
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.language',
		'config' => Array (
			'type' => 'input',
			'size' => '4',
			'max' => '2',
			'eval' => '',
			'default' => ''
		)
	),
	'first_name' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.first_name',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'last_name' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.last_name',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'date_of_birth' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.date_of_birth',
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
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.gender',
		'config' => Array (
			'type' => 'radio',
			'items' => Array (
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.gender.I.0', '0'),
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.gender.I.1', '1'),
			),
		)
	),
	'status' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status',
		'config' => Array (
			'type' => 'select',
			'items' => Array (
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status.I.0', '0'),
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status.I.1', '1'),
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status.I.2', '2'),
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status.I.3', '3'),
				Array('LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.status.I.4', '4'),
			),
			'size' => 1,
			'maxitems' => 1,
		)
	),
	'comments' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.comments',
		'config' => Array (
			'type' => 'text',
			'rows' => '5',
			'cols' => '48'
		)
	),
	'by_invitation' => Array (
		'exclude' => 0,	
		'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_users.by_invitation',
		'config' => Array (
			'type' => 'check',
			'default' => '0'
		)
	),
));

$TCA['fe_users']['interface']['showRecordFieldList'] = str_replace(',country', ',zone,static_info_country,country,language', $TCA['fe_users']['interface']['showRecordFieldList']);
$TCA['fe_users']['interface']['showRecordFieldList'] = str_replace(',title', ',gender,first_name,last_name,status,date_of_birth,title', $TCA['fe_users']['interface']['showRecordFieldList']);

$TCA['fe_users']['feInterface']['fe_admin_fieldList'] = str_replace(',country', ',zone,static_info_country,country,language,comments', $TCA['fe_users']['feInterface']['fe_admin_fieldList']);
$TCA['fe_users']['feInterface']['fe_admin_fieldList'] = str_replace(',title', ',gender,first_name,last_name,status,title', $TCA['fe_users']['feInterface']['fe_admin_fieldList']);
$TCA['fe_users']['feInterface']['fe_admin_fieldList'] .= ',image,disable,date_of_birth,by_invitation';

$TCA['fe_users']['types']['0']['showitem'] = str_replace(', country', ', zone, static_info_country, country,language', $TCA['fe_users']['types']['0']['showitem']);

$TCA['fe_users']['types']['0']['showitem'] = str_replace(', address', ', status, address', $TCA['fe_users']['types']['0']['showitem']);

$TCA['fe_users']['types']['0']['showitem'] = str_replace(', www', ', www, comments, by_invitation', $TCA['fe_users']['types']['0']['showitem']);

$TCA['fe_users']['palettes']['2']['showitem'] = str_replace(',title', ',gender,first_name,last_name,title', $TCA['fe_users']['palettes']['2']['showitem']);

	// fe_users modified
if (!t3lib_extMgm::isLoaded('direct_mail')) {
	$tempCols = Array(
		'module_sys_dmail_html' => Array(
			'label'=>'LLL:EXT:'.$_EXTKEY.'/locallang_db.xml:fe_users.module_sys_dmail_html',
			'exclude' => '1',
			'config'=>Array(
				'type'=>'check'
				)
			)
	);
	t3lib_extMgm::addTCAcolumns('fe_users',$tempCols);
	$TCA['fe_users']['feInterface']['fe_admin_fieldList'].=',module_sys_dmail_html';
	t3lib_extMgm::addToAllTCATypes('fe_users','--div--;Direct mail,module_sys_dmail_html;;;;1-1-1');
}

$TCA['fe_groups_language_overlay'] = Array (
	'ctrl' => Array (
 	'title' => 'LLL:EXT:' . SR_FEUSER_REGISTER_EXTkey . '/locallang_db.xml:fe_groups_language_overlay',
		'label' => 'title',
		'default_sortby' => 'ORDER BY fe_groups_uid',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => Array (
			'disabled' => 'hidden'
		),
 		'dynamicConfigFile' => t3lib_extMgm::extPath(SR_FEUSER_REGISTER_EXTkey).'tca.php',
		'iconfile' => 'gfx/i/fe_groups.gif',
		)
);
t3lib_extMgm::allowTableOnStandardPages('fe_groups_language_overlay');
t3lib_extMgm::addToInsertRecords('fe_groups_language_overlay');

?>