<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['sr_feuser_register_pi1'] = 'layout,select_key';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['sr_feuser_register_pi1'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('sr_feuser_register_pi1', 'FILE:EXT:sr_feuser_register/Configuration/FlexForms/flexform_ds_pi1.xml');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_db.xlf:tt_content.list_type', 'sr_feuser_register_pi1'), 'list_type', 'sr_feuser_register');

$GLOBALS['TCA']['tt_content']['columns']['fe_group']['config']['foreign_table_where'] = ' AND fe_groups.sys_language_uid IN (-1,0) ORDER BY fe_groups.title';