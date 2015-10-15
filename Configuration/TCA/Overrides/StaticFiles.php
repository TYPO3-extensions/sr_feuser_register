<?php
defined('TYPO3_MODE') or die();

// Configure extension static template
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('sr_feuser_register', 'Configuration/TypoScript/PluginSetup', 'FE User Registration Setup');