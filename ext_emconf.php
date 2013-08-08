<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "sr_feuser_register".
 *
 * Auto generated 08-08-2013 12:01
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Front End User Registration',
	'description' => 'A self-registration variant of Kasper SkÃ¥rhÃ¸j\'s Front End User Admin extension.',
	'category' => 'plugin',
	'shy' => 0,
	'version' => '3.0.2',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_srfeuserregister',
	'modify_tables' => 'fe_users',
	'clearcacheonload' => 1,
	'lockType' => '',
	'author' => 'Stanislas Rolland / Franz Holzinger',
	'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
	'CGLcompliance' => NULL,
	'CGLcompliance_note' => NULL,
	'constraints' => 
	array (
		'depends' => 
		array (
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.4.0-4.7.99',
			'cms' => '',
			'felogin' => '',
			'rsaauth' => '',
			'saltedpasswords' => '',
			'static_info_tables' => '2.3.0-',
			'div2007' => '0.10.1-',
		),
		'conflicts' => 
		array (
			'germandates' => '0.0.0-1.0.1',
			'rlmp_language_detection' => '0.0.0-1.2.99',
			'newloginbox' => '',
			'kb_md5fepw' => '',
			'srfeuserregister_t3secsaltedpw' => '',
			'patch1822' => '',
		),
		'suggests' => 
		array (
		),
	),
);

?>