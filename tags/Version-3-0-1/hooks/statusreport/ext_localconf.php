<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

if (TYPO3_MODE == 'BE') {
		// Take note of conflicting extensions
	$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['constraints'] = $EM_CONF[$_EXTKEY]['constraints'];
		// Register Status Report Hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Front End User Registration'][] = 'tx_srfeuserregister_statusReport';
}
?>