<?php
namespace SJBR\SrFeuserRegister\Configuration;

/*
 *  Copyright notice
 *
 *  (c) 2004-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;
use TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility;

/**
 * Validate the extension configuration
 */
class ConfigurationCheck
{
	/**
	 * Checks requirements for this plugin
	 *
	 * @param string $extensionKey the extension key
	 * @return string Error message, if error found, empty string otherwise
	 */
	static public function checkRequirements($extensionKey)
	{
		$content = '';
		$extensionName = GeneralUtility::underscoredToUpperCamelCase($extensionKey);
		// Check if all required extensions are available
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $requiredExtension) {
				if (!ExtensionManagementUtility::isLoaded($requiredExtension)) {
					$message = sprintf(LocalizationUtility::translate('internal_required_extension_missing', $extensionName), $requiredExtension);
					GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
				}
			}
		}
		return $content;
	}

	/**
	 * Checks security settings
	 *
	 * @param string $extensionKey the extension key
	 * @return string Error message, if error found, empty string otherwise
	 */
	static public function checkSecuritySettings($extensionKey)
	{
		$content = '';
		$extensionName = GeneralUtility::underscoredToUpperCamelCase($extensionKey);
		if ($extensionKey === 'sr_feuser_register') {
			// Check if front end login security level is correctly set
			$supportedTransmissionSecurityLevels = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['loginSecurityLevels'];
			if (!in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
				$message = LocalizationUtility::translate('internal_login_security_level', $extensionName);
				GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
				$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
			} else {
				// Check if salted passwords are enabled in front end
				if (ExtensionManagementUtility::isLoaded('saltedpasswords')) {
					if (!SaltedPasswordsUtility::isUsageEnabled('FE')) {
						$message = LocalizationUtility::translate('internal_salted_passwords_disabled', $extensionName);
						GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
						$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
					} else {
						// Check if we can get a salting instance
						$objSalt = SaltFactory::getSaltingInstance(NULL);
						if (!is_object($objSalt)) {
							// Could not get a salting instance from saltedpasswords
							$message = LocalizationUtility::translate('internal_salted_passwords_no_instance', $extensionName);
							GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
							$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
						}
					}
				}
				// Check if we can get a backend from rsaauth
				if (ExtensionManagementUtility::isLoaded('rsaauth')) {
					$backend = BackendFactory::getBackend();
					$storage = StorageFactory::getStorage();
					if (!is_object($backend) || !$backend->isAvailable() || !is_object($storage)) {
						// Required RSA auth backend not available
						$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', $extensionName);
						GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
						$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Checks whether the HTML templates contains any deprecated marker
	 *
	 * @param string $extensionKey: the extension key
	 * @param array $configuration: the configuration of the plugin
	 * @return string Error message, if error found, empty string otherwise
	 */
	static public function checkDeprecatedMarkers($extensionKey, $conf)
	{
		$content = '';
		$messages = array();
		$extensionName = GeneralUtility::underscoredToUpperCamelCase($extensionKey);
		$cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$templateCode = $cObj->fileResource($conf['templateFile']);
		// These changes apply only to sr_feuser_register
		if ($extensionKey === 'sr_feuser_register') {
			// Version 3: no clear-text passwords in templates
			// Remove any ###FIELD_password###, ###FIELD_password_again### markers
			// Remove markers ###TEMPLATE_INFOMAIL###, ###TEMPLATE_INFOMAIL_SENT### and ###EMAIL_TEMPLATE_INFOMAIL###
			$removeMarkers = array(
				'###FIELD_password###',
				'###FIELD_password_again###',
				'###TEMPLATE_INFOMAIL###',
				'###TEMPLATE_INFOMAIL_SENT###',
				'###EMAIL_TEMPLATE_INFOMAIL###',
			);
			$removeMarkerMessage = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang.xlf:internal_remove_deprecated_marker', $extensionName);
			foreach ($removeMarkers as $marker) {
				if (strpos($templateCode, $marker) !== FALSE) {
					$messages[] = sprintf($removeMarkerMessage, $marker, $fileName);
				}
			}
			// Version 3: No clear-text password in email
			// Replace ###LABEL_V_REGISTRATION_INVITED_MESSAGE1### with ###LABEL_V_REGISTRATION_INVITED_MESSAGE1A###
			// Replace ###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1### with ###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1A###
			$replaceMarkers = array(
				array(
					'marker' => '###LABEL_V_REGISTRATION_INVITED_MESSAGE1###',
					'replacement' => '###LABEL_V_REGISTRATION_INVITED_MESSAGE1A###'
				),
				array(
					'marker' => '###LABEL_V_REGISTRATION_INVITED_MESSAGE1_INFORMAL###',
					'replacement' => '###LABEL_V_REGISTRATION_INVITED_MESSAGE1A_INFORMAL###',
				),
				array(
					'marker' => '###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1###',
					'replacement' => '###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1A###',
				),
				array(
					'marker' => '###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1_INFORMAL###',
					'replacement' => '###LABEL_V_REGISTRATION_INFOMAIL_MESSAGE1A_INFORMAL###',
				),
			);
			$replaceMarkerMessage = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang.xlf:internal_replace_deprecated_marker', $extensionName);
			foreach ($replaceMarkers as $replaceMarker) {
				if (strpos($templateCode, $replaceMarker['marker']) !== false) {
					$messages[] = sprintf($replaceMarkerMessage, $replaceMarker['marker'], $replaceMarker['replacement'], $fileName);
				}
			}
		}
		foreach ($messages as $message) {
			GeneralUtility::sysLog($message, $extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
			$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
		}
		return $content;
	}
}