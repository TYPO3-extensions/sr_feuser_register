<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['fe_groups_language_overlay'] = Array (
	'ctrl' => $TCA['fe_groups_language_overlay']['ctrl'],
	'interface' => Array (
		'showRecordFieldList' => 'hidden,fe_group,sys_language_uid,title'
	),
	'columns' => Array (
		'hidden' => Array (		
			'exclude' => 0,	
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'fe_group' => Array (
			'exclude' => 0,
			'label' => 'LLL:EXT:cms/locallang_tca.xml:fe_groups',
			'config' => Array (
				'type' => 'select',	
				'foreign_table' => 'fe_groups'
			)
		),
		'sys_language_uid' => Array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title'
			)
		),
		'title' => Array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:sr_feuser_register/locallang_db.xml:fe_groups_language_overlay.title',
			'config' => Array (
				'type' => 'input',	
				'size' => '20',
				'max' => '20',
				'eval' => 'trim,required',
			)
		),
	),
	'types' => Array (
		'0' => Array( 'showitem' => 'hidden;;;;1-1-1, fe_group, sys_language_uid, title')
	)
);

?>