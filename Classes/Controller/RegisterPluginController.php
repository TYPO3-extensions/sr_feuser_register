<?php
namespace SJBR\SrFeuserRegister\Controller;

/*
 *  Copyright notice
 *
 *  (c) 1999-2003 Kasper Skårhøj <kasperYYYY@typo3.com>
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
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;
use TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility;

/**
 * Front End creating/editing/deleting records authenticated by fe_user login.
 * A variant restricted to front end user self-registration and profile maintenance, with a number of enhancements (see the manual).
 */
class RegisterPluginController extends AbstractPlugin
{

	public $cObj;

	/**
	 * @var string Extension name
	 */
	protected $extensionName = 'SrFeuserRegister';

	/**
	 * Used for CSS classes, variables
	 *
	 * @var string
	 */
	public $prefixId = 'tx_srfeuserregister_pi1';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	public $extKey = 'sr_feuser_register';

	public function main($content, $conf)
	{
		$this->pi_setPiVarDefaults();
		$this->conf = &$conf;
		// Check installation requirements
		$content = $this->checkRequirements();
		// Check presence of deprecated markers
		$content .= $this->checkDeprecatedMarkers($conf);
		// If no error content, proceed
		if (!$content) {
			$mainObj = GeneralUtility::makeInstance('tx_srfeuserregister_control_main');
			$mainObj->cObj = $this->cObj;
			$mainObj->extensionName = $this->extensionName;
			$content = $mainObj->main($content, $conf, $this, 'fe_users');
		}
		return $content;
	}

	/**
	 * Checks requirements for this plugin
	 *
	 * @return string Error message, if error found, empty string otherwise
	 */
	protected function checkRequirements()
	{
		$content = '';
		// Check if all required extensions are available
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $requiredExtension) {
				if (!ExtensionManagementUtility::isLoaded($requiredExtension)) {
					$message = sprintf(LocalizationUtility::translate('internal_required_extension_missing', $this->extensionName), $requiredExtension);
					GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				}
			}
		}
		// Check if front end login security level is correctly set
		$supportedTransmissionSecurityLevels = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['loginSecurityLevels'];

		if (!in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
			$message = LocalizationUtility::translate('internal_login_security_level', $this->extensionName);
			GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
			$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
		} else {
			// Check if salted passwords are enabled in front end
			if (ExtensionManagementUtility::isLoaded('saltedpasswords')) {
				if (!SaltedPasswordsUtility::isUsageEnabled('FE')) {
					$message = LocalizationUtility::translate('internal_salted_passwords_disabled', $this->extensionName);
					GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				} else {
					// Check if we can get a salting instance
					$objSalt = SaltFactory::getSaltingInstance(NULL);
					if (!is_object($objSalt)) {
						// Could not get a salting instance from saltedpasswords
						$message = LocalizationUtility::translate('internal_salted_passwords_no_instance', $this->extensionName);
						GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
						$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
					}
				}
			}
			// Check if we can get a backend from rsaauth
			if (ExtensionManagementUtility::isLoaded('rsaauth')) {
				$backend = BackendFactory::getBackend();
				$storage = StorageFactory::getStorage();
				if (!is_object($backend) || !$backend->isAvailable() || !is_object($storage)) {
					// Required RSA auth backend not available
					$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', $this->extensionName);
					GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				}
			}
		}
		return $content;
	}

	/**
	 * Checks whether the HTML templates contains any deprecated marker
	 *
	 * @return string Error message, if error found, empty string otherwise
	 */
	protected function checkDeprecatedMarkers()
	{
		$content = '';
		$templateCode = $this->cObj->fileResource($this->conf['templateFile']);
		$marker = GeneralUtility::makeInstance('tx_srfeuserregister_marker');
		$messages = $marker->checkDeprecatedMarkers($templateCode, $this->extKey, $this->conf['templateFile']);
		foreach ($messages as $message) {
			GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
			$content .= sprintf(LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
		}
		return $content;
	}
}