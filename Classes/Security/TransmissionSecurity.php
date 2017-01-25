<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2012-2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;
use TYPO3\CMS\Rsaauth\Hook\FrontendLoginHook;
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;

/**
 * Transmission security functions
 */
class TransmissionSecurity
{
	/**
	 *  Extension name
	 *
	 * @var string
	 */
	static protected $extensionName = 'SrFeuserRegister';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	static protected $extensionKey = 'sr_feuser_register';

	/**
	 * Gets the transmission security level
	 *
	 * @return string the transmission security level
	 */
	static public function getTransmissionSecurityLevel()
	{
		return $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
	}

	/**
	 * Decrypts fields that were encrypted for transmission
	 *
	 * @param array $row: incoming data array that may contain encrypted fields
	 * @return boolean true, if decryption was successful
	 */
	static public function decryptIncomingFields(array &$row)
	{
		$success = true;
		if (!empty($row)) {
			switch (self::getTransmissionSecurityLevel()) {
				case 'rsa':
					// Get services from rsaauth
					// Can't simply use the authentication service because we have two fields to decrypt
					/** @var $backend \TYPO3\CMS\Rsaauth\Backend\AbstractBackend */
					$backend = BackendFactory::getBackend();
					/** @var $storage \TYPO3\CMS\Rsaauth\Storage\AbstractStorage */
					$storage = StorageFactory::getStorage();
					if (is_object($backend) && is_object($storage)) {
						$key = $storage->get();
						if ($key !== null) {
							foreach ($row as $field => $value) {
								if (isset($value) && $value !== '') {
									if (substr($value, 0, 4) === 'rsa:') {
										// Decode password
										$result = $backend->decrypt($key, substr($value, 4));
										if ($result !== null) {
											$row[$field] = $result;
										} else {
											// RSA auth service failed to process incoming password
											// May happen if the key is wrong
											$success = false;
											$message = LocalizationUtility::translate('internal_rsaauth_process_incoming_password_failed', self::$extensionName);
											GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
										}
									}
								}
							}
							// Remove the key
							$storage->put(null);
						} else {
							// RSA auth service failed to retrieve private key
							// May happen if the key was already removed
							$success = false;
							$message = LocalizationUtility::translate('internal_rsaauth_retrieve_private_key_failed', self::$extensionName);
							GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_WARNING);
						}
					} else {
						// Required RSA auth backend not available
						// Should not happen
						$success = false;
						$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', self::$extensionName);
						GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					}
					break;
				case 'normal':
				default:
					// Nothing to decrypt
					break;
			}
		}
		return $success;
	}

	/**
	 * Gets value for ###FORM_ONSUBMIT### and ###HIDDENFIELDS### markers
	 *
	 * @param array $markerArray: marker array
	 * @param boolean $usePasswordAgain: whether the password again field is configured
	 * @return void
	 */
	static public function getMarkers(array &$markerArray, $usePasswordAgain)
	{
		$markerArray['###FORM_ONSUBMIT###'] = '';
 		switch (self::getTransmissionSecurityLevel()) {
			case 'rsa':
				$onSubmit = '';
				$extraHiddenFields = '';
				$extraHiddenFieldsArray = array();
				list($onSubmit, $hiddenFields) = self::getPasswordEncryptionCode();
				if ($usePasswordAgain && !empty($onSubmit)) {
					$onSubmit = 'if (this.pass.value != this[\'FE[fe_users][password_again]\'].value) {this.pass.value = \'X\'; this[\'FE[fe_users][password_again]\'].value = \'\'; return true;} else { this[\'FE[fe_users][password_again]\'].value = \'\'; ' . $onSubmit . '}';
				}
				$markerArray['###FORM_ONSUBMIT###'] = !empty($onSubmit) ? ' onsubmit="' . $onSubmit . '"' : '';
				if (count($extraHiddenFieldsArray)) {
					$extraHiddenFields = implode(LF, $extraHiddenFieldsArray) .  LF;
				}
				$markerArray['###HIDDENFIELDS###'] .= LF . $extraHiddenFields;
				break;
			case 'normal':
			default:
				$markerArray['###HIDDENFIELDS###'] .= LF;
				break;
		}
	}

	/**
	 * Provide additional code for FE password encryption
	 *
	 * @return array 0 => onSubmit function, 1 => extra fields and required files
	 * @see \TYPO3\CMS\Rsaauth\Hook\FrontendLoginHook
	 */
	protected static function getPasswordEncryptionCode()
	{
		$result = array(0 => '', 1 => '');
		if (trim($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel']) === 'rsa') {
			$frontendLoginHook = GeneralUtility::makeInstance(FrontendLoginHook::class);
			$result = $frontendLoginHook->loginFormHook();
		}
		return $result;
	}
}