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
	'version' => '6.0.1',
	'constraints' => [
		'depends' => [
			'typo3' => '9.5.0-9.5.99',
			'rdct' => '1.0.0-1.0.99',
			'felogin' => '9.5.0-9.5.99',
			'rsaauth' => '9.5.0-9.5.99',
			'static_info_tables' => '6.7.1-6.7.99'
		],
		'conflicts' => [
			'germandates' => '0.0.0-99.99.99',
			'rlmp_language_detection' => '0.0.0-99.99.99',
			'newloginbox' => '0.0.0-99.99.99',
			'kb_md5fepw' => '0.0.0-99.99.99',
			'patch1822' => '0.0.0-99.99.99',
			'cc_random_image' => '0.0.0-99.99.99'
		],
		'suggests' => [
			'sr_freecap' => '2.5.1-2.5.99'
		]
	]
];