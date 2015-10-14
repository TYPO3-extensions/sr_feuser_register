<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012-2015 Stanislas Rolland <typo3@sjbr.ca>
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Hook into the backend module "Reports" checking the configuration required for sr_feuser_register
 */
class tx_srfeuserregister_statusReport implements tx_reports_StatusProvider {
	/**
	 * Compiles a collection of system status checks as a status report.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array(
			'requiredExtensionsAreInstalled' => $this->checkIfRequiredExtensionsAreInstalled(),
			'noConflictingExtensionIsInstalled' => $this->checkIfNoConflictingExtensionIsInstalled(),
			'frontEndLoginSecurityLevelIsCorrectlySet' => $this->checkIfFrontEndLoginSecurityLevelIsCorrectlySet(),
			'saltedPasswordsAreEnabledInFrontEnd' => $this->checkIfSaltedPasswordsAreEnabledInFrontEnd(),
		);
		return $reports;
	}

	/**
	 * Check whether any required extension is not installed
	 *
	 * @return	tx_reports_reports_status_Status
	 */
	protected function checkIfRequiredExtensionsAreInstalled() {
		$title = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Required_extensions_not_installed');
		$missingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $extensionKey) {
				if (!t3lib_extMgm::isLoaded($extensionKey)) {
					$missingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($missingExtensions)) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::keys') . ' ' . implode(', ', $missingExtensions);
			$message = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::install');
			$status = tx_reports_reports_status_Status::ERROR;
		} else {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::none');
			$message = '';
			$status = tx_reports_reports_status_Status::OK;
		}
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$title,
			$value,
			$message,
			$status
		);
	}

	/**
	 * Check whether any conflicting extension has been installed
	 *
	 * @return	tx_reports_reports_status_Status
	 */
	protected function checkIfNoConflictingExtensionIsInstalled() {
		$title = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::Conflicting_extensions_installed');
		$conflictingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['constraints']['conflicts'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][SR_FEUSER_REGISTER_EXT]['constraints']['conflicts'] as $extensionKey => $version) {
				if (t3lib_extMgm::isLoaded($extensionKey)) {
					$conflictingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($conflictingExtensions)) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::keys') . ' ' . implode(', ', $conflictingExtensions);
			$message = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::uninstall');
			$status = tx_reports_reports_status_Status::ERROR;
		} else {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::none');
			$message = '';
			$status = tx_reports_reports_status_Status::OK;
		}
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$title,
			$value,
			$message,
			$status
		);
	}

	/**
	 * Check whether frontend login security level is correctly set
	 *
	 * @return	tx_reports_reports_status_Status
	 */
	protected function checkIfFrontEndLoginSecurityLevelIsCorrectlySet() {
		$title = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::Front_end_login_security_level');
		$supportedTransmissionSecurityLevels = array('normal', 'rsa');
		if (!in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
			$value = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
			$message = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::must_be_normal_or_rsa');
			$status = tx_reports_reports_status_Status::ERROR;
		} else {
			$value = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];;
			$message = '';
			$status = tx_reports_reports_status_Status::OK;
		}
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$title,
			$value,
			$message,
			$status
		);
	}

	/**
	 * Check whether salted passwords are enabled in front end
	 *
	 * @return	tx_reports_reports_status_Status
	 */
	protected function checkIfSaltedPasswordsAreEnabledInFrontEnd() {
		$title = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::Salted_passwords_in_front_end');
		if (!t3lib_extMgm::isLoaded('saltedpasswords') || !tx_saltedpasswords_div::isUsageEnabled('FE')) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::disabled');
			$message = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::salted_passwords_must_be_enabled');
			$status = tx_reports_reports_status_Status::ERROR;
		} else {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf::enabled');
			$message = '';
			$status = tx_reports_reports_status_Status::OK;
		}
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$title,
			$value,
			$message,
			$status
		);
	}
}
if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXT . '/hooks/statusreport/class.tx_srfeuserregister_statusReport.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXT . '/hooks/statusreport/class.tx_srfeuserregister_statusReport.php']);
}
?>