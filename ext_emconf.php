<?php

/*
 * Extension Manager configuration file for ext "sr_feuser_register".
 */

$EM_CONF[$_EXTKEY] = [
	'title' => 'Front End User Registration',
	'description' => 'A self-registration variant of Kasper Skårhøj\'s Front End User Admin extension.',
	'category' => 'plugin',
	'state' => 'stable',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_srfeuserregister',
	'modify_tables' => 'fe_users',
	'clearCacheOnLoad' => 1,
	'author' => 'Stanislas Rolland',
	'author_email' => 'typo3@sjbr.ca',
	'author_company' => 'SJBR',
	'version' => '7.0.2',
	'constraints' => [
		'depends' => [
			'typo3' => '9.5.0-10.4.99',
			'felogin' => '9.5.0-10.4.99',
			'static_info_tables' => '6.9.0-6.9.99'
		],
		'conflicts' => [
		],
		'suggests' => [
			'sr_freecap' => '2.5.3-2.6.99'
		]
	]
];