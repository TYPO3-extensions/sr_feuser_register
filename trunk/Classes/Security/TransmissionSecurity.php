<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2012-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;

/**
 * Transmission security functions
 */
class TransmissionSecurity
{
	/**
	 * @var string Extension name
	 */
	protected $extensionName = 'SrFeuserRegister';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extKey = 'sr_feuser_register';

	/**
	 * The transmission security level: normal or rsa
	 *
	 * @var string
	 */	
	protected $transmissionSecurityLevel = 'normal';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->setTransmissionSecurityLevel();
	}

	/**
	 * Sets the transmission security level
	 *
	 * @return void
	 */
	protected function setTransmissionSecurityLevel()
	{
		$this->transmissionSecurityLevel = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
	}

	/**
	 * Gets the transmission security level
	 *
	 * @return string the storage security level
	 */
	public function getTransmissionSecurityLevel()
	{
		return $this->transmissionSecurityLevel;
	}

	/**
	 * Decrypts fields that were encrypted for transmission
	 *
	 * @param array $row: incoming data array that may contain encrypted fields
	 * @return boolean true, if decryption was successful
	 */
	public function decryptIncomingFields(array &$row)
	{
		$success = true;
		$fields = array('password', 'password_again');
		$incomingFieldSet = false;
		foreach ($fields as $field) {
			if (isset($row[$field])) {
				$incomingFieldSet = true;
				break;
			}
		}
		if ($incomingFieldSet) {
			switch ($this->getTransmissionSecurityLevel()) {
				case 'rsa':
					// Get services from rsaauth
					// Can't simply use the authentication service because we have two fields to decrypt
					/** @var $backend \TYPO3\CMS\Rsaauth\Backend\AbstractBackend */
					$backend = BackendFactory::getBackend();
					/** @var $storage \TYPO3\CMS\Rsaauth\Storage\AbstractStorage */
					$storage = StorageFactory::getStorage();
					if (is_object($backend) && is_object($storage)) {
						$key = $storage->get();
						if ($key != NULL) {
							foreach ($fields as $field) {
								if (isset($row[$field]) && $row[$field] != '') {
									if (substr($row[$field], 0, 4) == 'rsa:') {
										// Decode password
										$result = $backend->decrypt($key, substr($row[$field], 4));
										if ($result) {
											$row[$field] = $result;
										} else {
											// RSA auth service failed to process incoming password
											// May happen if the key is wrong
											// May happen if multiple instance of rsaauth on same page
											$success = false;
											$message = LocalizationUtility::translate('internal_rsaauth_process_incoming_password_failed', $this->extensionName);
											GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
										}
									}
								}
							}
							// Remove the key
							$storage->put(NULL);
						} else {
							// RSA auth service failed to retrieve private key
							// May happen if the key was already removed
							$success = false;
							$message = LocalizationUtility::translate('internal_rsaauth_retrieve_private_key_failed', $this->extensionName);
							GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
						}
					} else {
						// Required RSA auth backend not available
						// Should not happen
						$success = false;
						$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', $this->extensionName);
						GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
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
	 * @return void
	 */
	public function getMarkers(array &$markerArray)
	{
 		switch ($this->getTransmissionSecurityLevel()) {
			case 'rsa':
				$extraHiddenFieldsArray = array();
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'])) {
					$_params = array();
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'] as $funcRef) {
						list($onSubmit, $hiddenFields) = GeneralUtility::callUserFunction($funcRef, $_params, $this);
						$extraHiddenFieldsArray[] = $hiddenFields;
					}
				} else {
					// Extension rsaauth not installed
					// Should not happen
					$message = sprintf(LocalizationUtility::translate('internal_required_extension_missing', $this->extensionName), 'rsaauth');
					GeneralUtility::sysLog($message, $this->extKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
				}
				if (count($extraHiddenFieldsArray)) {
					$extraHiddenFields = implode(LF, $extraHiddenFieldsArray);
				}
				$GLOBALS['TSFE']->additionalHeaderData['sr_feuser_register_rsaauth'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . GeneralUtility::createVersionNumberedFilename(ExtensionManagementUtility::siteRelPath('sr_feuser_register')  . 'scripts/rsaauth.js') . '"></script>';
				$markerArray['###FORM_ONSUBMIT###'] = ' onsubmit="tx_srfeuserregister_encrypt(this); return true;"';
				$markerArray['###HIDDENFIELDS###'] .= LF . $extraHiddenFields;
				break;
			case 'normal':
			default:
				$markerArray['###FORM_ONSUBMIT###'] = '';
				$markerArray['###HIDDENFIELDS###'] .= LF;
				break;
		}
	}
}