<?php
return array(
	'ctrl' => array(
		'title' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_groups_language_overlay',
		'label' => 'title',
		'default_sortby' => 'ORDER BY fe_groups_uid',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden'
		),
		'typeicon_classes' => array(
			'default'=> 'status-user-group-frontend'
		)
	),
	'interface' => array(
		'showRecordFieldList' => 'hidden,fe_group,sys_language_uid,title'
	),
	'columns' => array(
		'hidden' => array(	
			'exclude' => 0,	
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'fe_group' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:fe_groups',
			'config' => array(
				'type' => 'select',	
				'foreign_table' => 'fe_groups'
			)
		),
		'sys_language_uid' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title'
			)
		),
		'title' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:fe_groups_language_overlay.title',
			'config' => array(
				'type' => 'input',	
				'size' => '30',
				'max' => '70',
				'eval' => 'trim,required',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, fe_group, sys_language_uid, title')
	)
);