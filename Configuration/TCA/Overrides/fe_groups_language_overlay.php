<?php
defined('TYPO3_MODE') or die();
if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getNumericTypo3Version()) < 7000000) {
	$GLOBALS['TCA']['fe_groups_language_overlay']['columns']['fe_group']['title'] = 'LLL:EXT:cms/locallang_tca.xlf:fe_groups';
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToInsertRecords('fe_groups_language_overlay');