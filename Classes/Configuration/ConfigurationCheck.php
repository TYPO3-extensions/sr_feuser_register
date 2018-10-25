<?php
namespace SJBR\SrFeuserRegister\Configuration;

/*
 *  Copyright notice
 *
 *  (c) 2004-2018 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;

/**
 * Validate the extension configuration
 */
class ConfigurationCheck implements LoggerAwareInterface
{
	use LoggerAwareTrait;

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
					$this->logger->error($extensionName . ': ' . $message);
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
				$this->logger->error($extensionName . ': ' . $message);
				$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
			} else {
				// Check if we can get a backend from rsaauth
				if (ExtensionManagementUtility::isLoaded('rsaauth')) {
					$backend = BackendFactory::getBackend();
					$storage = StorageFactory::getStorage();
					if (!is_object($backend) || !$backend->isAvailable() || !is_object($storage)) {
						// Required RSA auth backend not available
						$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', $extensionName);
						$this->logger->error($extensionName . ': ' . $message);
						$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $extensionName), $message);
					}
				}
			}
		}
		return $content;
	}
}