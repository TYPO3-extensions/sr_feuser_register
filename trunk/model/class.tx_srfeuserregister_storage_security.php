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
 * $Id: class.tx_srfeuserregister_storage_security.php$
 *
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */
class tx_srfeuserregister_storage_security {
		// Extension key
	protected $extKey = SR_FEUSER_REGISTER_EXTkey;
		// The storage security level: normal or salted
	protected $storageSecurityLevel = 'normal';

	/**
	* Constructor
	*
	* @return	void
	*/
	public function __construct () {
		$this->setStorageSecurityLevel();
	}

	/**
	* Sets the storage security level
	*
	* @return	void
	*/
	protected function setStorageSecurityLevel () {
		$this->storageSecurityLevel = 'normal';
		if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
			$this->storageSecurityLevel = 'salted';
		}
	}

	/**
	* Gets the storage security level
	*
	* @return	string	the storage security level
	*/
	protected function getStorageSecurityLevel () {
		return $this->storageSecurityLevel;
	}

	/**
	* Encrypts the password for secure storage
	*
	* @param	string	$password: password to encrypt
	* @return	string	encrypted password
	*/
	public function encryptPasswordForStorage ($password) {
		$encryptedPassword = $password;
		if ($password !== '') {
			switch ($this->getStorageSecurityLevel()) {
				case 'salted':
					$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance(NULL);
					if (is_object($objSalt)) {
						$encryptedPassword = $objSalt->getHashedPassword($password);
					} else {
						// Could not get a salting instance from saltedpasswords
						// Should not happen: checked in tx_srfeuserregister_pi1_base::checkRequirements
					}
					break;
				case 'normal':
				default:
						// No encryption!
					break;
			}
		}
		return $encryptedPassword;
	}

	/**
	* Initializes the password for auto-login on confirmation
	*
	* @param	array	$dataArray
	* @return	void
	*/
	public function initializeAutoLoginPassword (array &$dataArray) {
		$dataArray['tx_srfeuserregister_password'] = '';
		unset($dataArray['auto_login_key']);
	}

	/**
	* Determines if auto login should be attempted
	*
	* @param array $feuData: incoming fe_users parameters
	* @param array $dataArray: fe_users row
	* @return boolean TRUE, if auto-login should be attempted
	*/
	public function getAutoLoginIsRequested (array $feuData, array &$dataArray) {
		$autoLoginIsRequested = FALSE;
		if (isset($feuData['key']) && $feuData['key'] !== '') {
			$dataArray['auto_login_key'] = $feuData['key'];
			$autoLoginIsRequested = TRUE;
		}
		return $autoLoginIsRequested;
	}

	/**
	* Encrypts the password for auto-login on confirmation
	*
	* @param	array	$dataArray: array containing the password to be encrypted
	* @return	void
	*/
	public function encryptPasswordForAutoLogin (array &$dataArray) {
		$password = $dataArray['password'];
		$privateKey = '';
		$cryptedPassword = '';
		if ($password !== '') {
				// Create the keypair
			$keyPair = openssl_pkey_new();
				// Get private key
			openssl_pkey_export($keyPair, $privateKey);
				// Get public key
			$keyDetails = openssl_pkey_get_details($keyPair);
			$publicKey = $keyDetails['key'];
			if (@openssl_public_encrypt($password, $cryptedPassword, $publicKey)) {
				$dataArray['tx_srfeuserregister_password'] = base64_encode($cryptedPassword);
				$dataArray['auto_login_key'] = $privateKey;
			}
		}
	}

	/**
	* Decrypts the password for auto-login on confirmation or invitation acceptation
	*
	* @param array $dataArray: table row containing the password to be decrypted
	* @param array $row: incoming data containing the auto-login private key
	* @return void
	*/
	public function decryptPasswordForAutoLogin (array &$dataArray, array $row) {
		if (isset($row['auto_login_key'])) {
			$privateKey = $row['auto_login_key'];
			if ($privateKey !== '') {
				$password = $dataArray['tx_srfeuserregister_password'];
				if ($password !== '') {
					$backend = tx_rsaauth_backendfactory::getBackend();
					if (is_object($backend) && $backend->isAvailable()) {
						$decryptedPassword = $backend->decrypt($privateKey, $password);
						if ($decryptedPassword) {
							$dataArray['password'] = $decryptedPassword;
						} else {
								// Failed to decrypt auto login password
							$message = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:internal_decrypt_auto_login_failed');
							t3lib_div::sysLog($message, $this->extKey, t3lib_div::SYSLOG_SEVERITY_ERROR);
						}
					} else {
						// Required RSA auth backend not available
						// Should not happen: checked in tx_srfeuserregister_pi1_base::checkRequirements
					}
				}
			}
		}
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_storage_security.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_storage_security.php']);
}
?>