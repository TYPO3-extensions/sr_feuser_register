<?php
namespace SJBR\SrFeuserRegister\Reports;

/*
 *  Copyright notice
 *
 *  (c) 2012-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
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
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;
use TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility;

/**
 * Hook into the backend module "Reports" checking the configuration required for sr_feuser_register
 */
class StatusProvider implements StatusProviderInterface
{
	/**
	 * Compiles a collection of system status checks as a status report.
	 *
	 * @return array List of statuses
	 */
	public function getStatus()
	{
		$reports = array(
			'requiredExtensionsAreInstalled' => $this->checkIfRequiredExtensionsAreInstalled(),
			'noConflictingExtensionIsInstalled' => $this->checkIfNoConflictingExtensionIsInstalled(),
			'frontEndLoginSecurityLevelIsCorrectlySet' => $this->checkIfFrontEndLoginSecurityLevelIsCorrectlySet(),
			'saltedPasswordsAreEnabledInFrontEnd' => $this->checkIfSaltedPasswordsAreEnabledInFrontEnd()
		);
		return $reports;
	}

	/**
	 * Check whether any required extension is not installed
	 *
	 * @return	Status
	 */
	protected function checkIfRequiredExtensionsAreInstalled()
	{
		$languageService = $this->getLanguageService();
		$title = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Required_extensions_not_installed');
		$missingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $extensionKey) {
				if (!ExtensionManagementUtility::isLoaded($extensionKey)) {
					$missingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($missingExtensions)) {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:keys') . ' ' . implode(', ', $missingExtensions);
			$message = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:install');
			$status = Status::ERROR;
		} else {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:none');
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $title, $value, $message, $status);
	}

	/**
	 * Check whether any conflicting extension has been installed
	 *
	 * @return	Status
	 */
	protected function checkIfNoConflictingExtensionIsInstalled()
	{
		$languageService = $this->getLanguageService();
		$title = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Conflicting_extensions_installed');
		$conflictingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints']['conflicts'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['constraints']['conflicts'] as $extensionKey => $version) {
				if (ExtensionManagementUtility::isLoaded($extensionKey)) {
					$conflictingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($conflictingExtensions)) {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:keys') . ' ' . implode(', ', $conflictingExtensions);
			$message = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:uninstall');
			$status = Status::ERROR;
		} else {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:none');
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $title, $value, $message, $status);
	}

	/**
	 * Check whether frontend login security level is correctly set
	 *
	 * @return	Status
	 */
	protected function checkIfFrontEndLoginSecurityLevelIsCorrectlySet()
	{
		$languageService = $this->getLanguageService();
		$title = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Front_end_login_security_level');
		$supportedTransmissionSecurityLevels = array('normal', 'rsa');
		if (!in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
			$value = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
			$message = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:must_be_normal_or_rsa');
			$status = Status::ERROR;
		} else {
			$value = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $title, $value, $message, $status);
	}

	/**
	 * Check whether salted passwords are enabled in front end
	 *
	 * @return	Status
	 */
	protected function checkIfSaltedPasswordsAreEnabledInFrontEnd()
	{
		$languageService = $this->getLanguageService();
		$title = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Salted_passwords_in_front_end');
		if (!ExtensionManagementUtility::isLoaded('saltedpasswords') || !SaltedPasswordsUtility::isUsageEnabled('FE')) {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:disabled');
			$message = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:salted_passwords_must_be_enabled');
			$status = Status::ERROR;
		} else {
			$value = $languageService->sL('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:enabled');
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $title, $value, $message, $status);
	}

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}