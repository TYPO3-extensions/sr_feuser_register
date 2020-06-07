<?php
namespace SJBR\SrFeuserRegister\Configuration\Reports;

/*
 *  Copyright notice
 *
 *  (c) 2012-2020 Stanislas Rolland <typo32020(arobas)sjbr.ca>
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
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Hook into the backend module "Reports" checking the configuration required for sr_feuser_register
 */
class StatusProvider implements StatusProviderInterface
{
	/**
	 * @var string Extension name
	 */
	protected $extensionName = 'SrFeuserRegister';

	/**
	 * Compiles a collection of system status checks as a status report.
	 *
	 * @return array List of statuses
	 */
	public function getStatus()
	{
		$reports = [
			'requiredExtensionsAreInstalled' => $this->checkIfRequiredExtensionsAreInstalled(),
			'noConflictingExtensionIsInstalled' => $this->checkIfNoConflictingExtensionIsInstalled()
		];
		return $reports;
	}

	/**
	 * Check whether any required extension is not installed
	 *
	 * @return	Status
	 */
	protected function checkIfRequiredExtensionsAreInstalled()
	{
		$title = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Required_extensions_not_installed', $this->extensionName);
		$missingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sr_feuser_register']['constraints']['depends'])) {
			$requiredExtensions = array_diff(array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sr_feuser_register']['constraints']['depends']), array('php', 'typo3'));
			foreach ($requiredExtensions as $extensionKey) {
				if (!ExtensionManagementUtility::isLoaded($extensionKey)) {
					$missingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($missingExtensions)) {
			$value = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:keys', $this->extensionName) . ' ' . implode(', ', $missingExtensions);
			$message = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:install', $this->extensionName);
			$status = Status::ERROR;
		} else {
			$value = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:none', $this->extensionName);
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance(Status::class, $title, $value, $message, $status);
	}

	/**
	 * Check whether any conflicting extension has been installed
	 *
	 * @return	Status
	 */
	protected function checkIfNoConflictingExtensionIsInstalled()
	{
		$title = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:Conflicting_extensions_installed', $this->extensionName);
		$conflictingExtensions = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sr_feuser_register']['constraints']['conflicts'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sr_feuser_register']['constraints']['conflicts'] as $extensionKey => $version) {
				if (ExtensionManagementUtility::isLoaded($extensionKey)) {
					$conflictingExtensions[] = $extensionKey;
				}
			}
		}
		if (count($conflictingExtensions)) {
			$value = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:keys', $this->extensionName) . ' ' . implode(', ', $conflictingExtensions);
			$message = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:uninstall', $this->extensionName);
			$status = Status::ERROR;
		} else {
			$value = LocalizationUtility::translate('LLL:EXT:sr_feuser_register/Resources/Private/Language/locallang_statusreport.xlf:none', $this->extensionName);
			$message = '';
			$status = Status::OK;
		}
		return GeneralUtility::makeInstance(Status::class, $title, $value, $message, $status);
	}
}