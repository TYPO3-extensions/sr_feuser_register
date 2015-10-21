<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skårhøj (kasperYYYY@typo3.com)
*  (c) 2004-2013 Stanislas Rolland <typo3(arobas)sjbr.ca>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * Part of the sr_feuser_register (Front End User Registration) extension.
 * A variant restricted to front end user self-registration and profile maintenance, with a number of enhancements (see the manual).
 *
 * $Id$
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 * @maintainer	Franz Holzinger <franz@ttproducts.de>
 *
 *
 */
class tx_srfeuserregister_pi1_base extends tslib_pibase {
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

	public function main ($content, $conf) {
		$this->pi_setPiVarDefaults();
		$this->conf = &$conf;
			// Check installation requirements
		$content = $this->checkRequirements();
			// Check presence of deprecated markers
		$content .= $this->checkDeprecatedMarkers($conf);
			// If no error content, proceed
		if (!$content) {
			$mainObj = t3lib_div::getUserObj('&tx_srfeuserregister_control_main');
			$mainObj->cObj = $this->cObj;
			$mainObj->extensionName = $this->extensionName;
			$content = $mainObj->main($content, $conf, $this, 'fe_users');
		}
		return $content;
	}

	/* Checks requirements for this plugin
	 *
	 * @return string Error message, if error found, empty string otherwise
	 */
	protected function checkRequirements() {
		$content = '';
			// Check if all required extensions are available
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $requiredExtension) {
				if (!t3lib_extMgm::isLoaded($requiredExtension)) {
					$message = sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_required_extension_missing', $this->extensionName), $requiredExtension);
					t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				}
			}
		}
			// Check if front end login security level is correctly set
		$supportedTransmissionSecurityLevels = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['loginSecurityLevels'];

		if (!in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
			$message = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_login_security_level', $this->extensionName);
			t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
			$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
		} else {
				// Check if salted passwords are enabled in front end
			if (t3lib_extMgm::isLoaded('saltedpasswords')) {
				if (!tx_saltedpasswords_div::isUsageEnabled('FE')) {
					$message = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_salted_passwords_disabled', $this->extensionName);
					t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				} else {
						// Check if we can get a salting instance
					$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance(NULL);
					if (!is_object($objSalt)) {
						// Could not get a salting instance from saltedpasswords
						$message = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_salted_passwords_no_instance', $this->extensionName);
						t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
						$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
					}
				}
			}
				// Check if we can get a backend from rsaauth
			if (t3lib_extMgm::isLoaded('rsaauth')) {
				$backend = tx_rsaauth_backendfactory::getBackend();
				$storage = tx_rsaauth_storagefactory::getStorage();
				if (!is_object($backend) || !$backend->isAvailable() || !is_object($storage)) {
					// Required RSA auth backend not available
					$message = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_rsaauth_backend_not_available', $this->extensionName);
					t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
					$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
				}
			}
		}
		return $content;
	}

	/* Checks whether the HTML templates contains any deprecated marker
	 *
	 * @return string Error message, if error found, empty string otherwise
	 */
	protected function checkDeprecatedMarkers() {
		$content = '';
		$templateCode = $this->cObj->fileResource($this->conf['templateFile']);
		$marker = t3lib_div::getUserObj('&tx_srfeuserregister_marker');
		$messages = $marker->checkDeprecatedMarkers($templateCode, $this->extKey, $this->conf['templateFile']);
		foreach ($messages as $message) {
			t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
			$content .= sprintf(\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_check_requirements_frontend', $this->extensionName), $message);
		}
		return $content;
	}
}