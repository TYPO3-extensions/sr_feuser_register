<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Stanislas Rolland <typo3(arobas)sjbr.ca>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Part of the sr_feuser_register (Front End User Registration) extension.
 *
 * Storage security functions
 *
 * $Id: class.tx_srfeuserregister_transmission_security.php$
 *
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */
class tx_srfeuserregister_transmission_security {
		// Extension key
	protected $extKey = SR_FEUSER_REGISTER_EXTkey;
		// The storage security level: normal or rsa
	protected $transmissionSecurityLevel = 'normal';

	/**
	* Constructor
	*
	* @return	void
	*/
	public function __construct () {
		$this->setTransmissionSecurityLevel();
	}

	/**
	* Sets the transmission security level
	*
	* @return	void
	*/
	protected function setTransmissionSecurityLevel () {
		$this->transmissionSecurityLevel = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
	}

	/**
	* Gets the transmission security level
	*
	* @return	string	the storage security level
	*/
	public function getTransmissionSecurityLevel () {
		return $this->transmissionSecurityLevel;
	}

	/**
	* Decrypts fields that were encrypted for transmission
	*
	* @param array $row: incoming data array that may contain encrypted fields
	* @return boolean TRUE if decryption was successful
	*/
	public function decryptIncomingFields (array &$row) {
		$success = TRUE;
		$fields = array('password', 'password_again');
		$incomingFieldSet = FALSE;
		foreach ($fields as $field) {
			if (isset($row[$field])) {
				$incomingFieldSet = TRUE;
				break;
			}
		}
		if ($incomingFieldSet) {
			switch ($this->getTransmissionSecurityLevel()) {
				case 'rsa':
						// Get services from rsaauth
						// Can't simply use the authentication service because we have two fields to decrypt
					$backend = tx_rsaauth_backendfactory::getBackend();
					$storage = tx_rsaauth_storagefactory::getStorage();
					/* @var $storage tx_rsaauth_abstract_storage */
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
											$success = FALSE;
											$message = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:internal_rsaauth_process_incoming_password_failed');
											t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
										}
									}
								}
							}
								// Remove the key
							$storage->put(NULL);
						} else {
								// RSA auth service failed to retrieve private key
								// May happen if the key was already removed
							$success = FALSE;
							$message = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:internal_rsaauth_retrieve_private_key_failed');
							t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
						}
					} else {
							// Required RSA auth backend not available
							// Should not happen: checked in tx_srfeuserregister_pi1_base::checkRequirements
						$success = FALSE;
						$message = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:internal_rsaauth_backend_not_available');
						t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
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
	public function getMarkers (array &$markerArray) {
 		switch ($this->getTransmissionSecurityLevel()) {
			case 'rsa':
				$extraHiddenFieldsArray = array();
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'])) {
					$_params = array();
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'] as $funcRef) {
						list($onSubmit, $hiddenFields) = t3lib_div::callUserFunction($funcRef, $_params, $this);
						$extraHiddenFieldsArray[] = $hiddenFields;
					}
				} else {
						// Extension rsaauth not installed
						// Should not happen: checked in tx_srfeuserregister_pi1_base::checkRequirements
					$message = sprintf($GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:internal_required_extension_missing'), 'rsaauth');
					t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
				}
				if (count($extraHiddenFieldsArray)) {
					$extraHiddenFields = implode(LF, $extraHiddenFieldsArray);
				}
				$GLOBALS['TSFE']->additionalHeaderData['sr_feuser_register_rsaauth'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . t3lib_div::createVersionNumberedFilename(t3lib_extMgm::siteRelPath('sr_feuser_register')  . 'scripts/rsaauth.js') . '"></script>';
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

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_transmission_security.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_transmission_security.php']);
}
?>