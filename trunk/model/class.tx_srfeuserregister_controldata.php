<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Franz Holzinger (franz@ttproducts.de)
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
 * control data store functions
 *
 * $Id$
 *
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

define ('MODE_NORMAL', '0');
define ('MODE_PREVIEW', '1');


class tx_srfeuserregister_controldata {
	public $thePid = 0;
	public $thePidTitle;
	public $theTable;
	public $site_url;
	public $prefixId;
	public $piVars;
	public $extKey;
	public $cmd='';
	public $cmdKey;
	public $pid = array();
	public $setfixedEnabled = 0;
	public $bSubmit = FALSE;
	public $bDoNotSave = FALSE;
	public $failure = FALSE; // is set if data did not have the required fields set.
	public $sys_language_content;
	public $feUserData = array();
		// Names of secured fields
	protected $securedFieldArray = array('password', 'password_again');
	public $bValidRegHash;
	public $regHash;
		// Whether the token was found valid
	protected $isTokenValid = FALSE;
		// The transmission security level: normal or rsa
	protected $transmissionSecurityLevel = 'normal';
		// The storage security level: normal or salted
	protected $storageSecurityLevel = 'normal';


	public function init (&$conf, $prefixId, $extKey, $piVars, $theTable) {

		$this->conf = &$conf;
		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		if ($GLOBALS['TSFE']->absRefPrefix) {
			if(strpos($GLOBALS['TSFE']->absRefPrefix, 'http://') === 0 || strpos($GLOBALS['TSFE']->absRefPrefix, 'https://') === 0) {
				$this->site_url = $GLOBALS['TSFE']->absRefPrefix;
			} else {
				$this->site_url = $this->site_url . ltrim($GLOBALS['TSFE']->absRefPrefix, '/');
			}
		}
		$this->prefixId = $prefixId;
		$this->extKey = $extKey;
		$this->piVars = $piVars;
		$this->setTable($theTable);
		$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

		$this->setTransmissionSecurityLevel();
		$this->setStorageSecurityLevel();

		$bSysLanguageUidIsInt = (
			class_exists('t3lib_utility_Math') ?
				t3lib_utility_Math::canBeInterpretedAsInteger($GLOBALS['TSFE']->config['config']['sys_language_uid']) :
				t3lib_div::testInt($GLOBALS['TSFE']->config['config']['sys_language_uid'])
		);
		$this->sys_language_content = ($bSysLanguageUidIsInt ? $GLOBALS['TSFE']->config['config']['sys_language_uid'] : 0);

			// set the title language overlay
		$pidRecord = t3lib_div::makeInstance('t3lib_pageSelect');
		$pidRecord->init(0);
		$pidRecord->sys_language_uid = $this->sys_language_content;
		$row = $pidRecord->getPage($this->getPid());
		$this->thePidTitle = trim($this->conf['pidTitleOverride']) ? trim($this->conf['pidTitleOverride']) : $row['title'];

		$pidTypeArray = array('login', 'register', 'edit', 'infomail', 'confirm', 'confirmInvitation');
		// set the pid's

		foreach ($pidTypeArray as $k => $type) {
			$this->setPid($type, $this->conf[$type.'PID']);
		}

		if (
			$this->conf['enableEmailConfirmation'] ||
			$this->conf['enableAdminReview'] ||
			$this->conf['setfixed']
		) {
			$this->setSetfixedEnabled(1);
		}
			// Get hash variable if provided and if short url feature is enabled
		$feUserData = t3lib_div::_GP($this->getPrefixId());
		$bSecureStartCmd = (count($feUserData) == 1 && in_array($feUserData['cmd'], array('create', 'edit')));
		$bValidRegHash = FALSE;

		// <Steve Webster added short url feature>
		if ($this->conf['useShortUrls']) {
			$this->cleanShortUrlCache();
			if (isset($feUserData) && is_array($feUserData)) {
				$regHash = $feUserData['regHash'];
			}

			if (!$regHash) {
				$getData = t3lib_div::_GET($this->getPrefixId());

				if (isset($getData) && is_array($getData)) {
					$regHash = $getData['regHash'];
				}
			}

				// Check and process for short URL if the regHash GET parameter exists
			if ($regHash) {
				$getVars = $this->getShortUrl($regHash);

				if (isset($getVars) && is_array($getVars) && count($getVars)) {
					$bValidRegHash = TRUE;
					$origDataFieldArray = array('sFK', 'cmd', 'submit', 'fetch', 'regHash', 'preview');
					$origFeuserData = array();
 					// copy the original values which must not be overridden by the regHash stored values
					foreach ($origDataFieldArray as $origDataField) {
						if (isset($feUserData[$origDataField])) {
							$origFeuserData[$origDataField] = $feUserData[$origDataField];
						}
					}
					$restoredFeUserData = $getVars[$this->getPrefixId()];

					foreach ($getVars as $k => $v ) {
						// restore former GET values for the url
						t3lib_div::_GETset($v,$k);
					}

					if ($restoredFeUserData['rU'] > 0 && $restoredFeUserData['rU'] == $feUserData['rU']) {
						$feUserData = array_merge($feUserData, $restoredFeUserData);
					} else {
						$feUserData = $restoredFeUserData;
					}

					if (isset($feUserData) && is_array($feUserData)) {
						$feUserData = array_merge($feUserData, $origFeuserData);
					} else {
						$feUserData = $origFeuserData;
					}
					$this->setRegHash($regHash);
				}
			}
		}

		if (isset($feUserData) && is_array($feUserData)) {
			$this->setFeUserData($feUserData);
		}

			// Establishing compatibility with Direct Mail extension
		$piVarArray = array('rU', 'aC', 'cmd', 'sFK');

		foreach($piVarArray as $pivar) {
			$value = htmlspecialchars(t3lib_div::_GP($pivar));
			if ($value != '') {
				$this->setFeUserData($value, $pivar);
			}
		}
		$aC = $this->getFeUserData('aC');
		$authObj->setAuthCode($aC);

		if (isset($feUserData) && is_array($feUserData) && isset($feUserData['cmd'])) {
			$cmd = htmlspecialchars($feUserData['cmd']);
			$this->setCmd($cmd);
		}
		$feUserData = $this->getFeUserData();
		$this->secureInput($feUserData);

		$bRuIsInt = (
			class_exists('t3lib_utility_Math') ?
				t3lib_utility_Math::canBeInterpretedAsInteger($feUserData['rU']) :
				t3lib_div::testInt($feUserData['rU'])
		);

		if ($bRuIsInt) {
			$theUid = intval($feUserData['rU']);                                          // find the uid
			$origArray = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $theUid);               // Get data
		}

		$token = '';
		if (
			isset($origArray) &&
			is_array($origArray) &&
			$cmd == 'setfixed' &&
			$origArray['token'] != ''
		) {
			$token = $origArray['token'];	// use the token from the FE user data
					// Token has been added to pivars in a mail link URL.
		} else {
			$token = $this->readToken(); // fetch latest internal token
		}

		if (
			is_array($feUserData) && (
				!count($feUserData) ||
				$bSecureStartCmd ||
				$token != '' && $feUserData['token'] == $token
			)
		) {
			$this->setTokenValid(TRUE);
		} else if (
			$bRuIsInt &&
			($bValidRegHash || !$this->conf['useShortUrls'])
		) {
			if (
				isset($getVars) &&
				is_array($getVars) &&
				isset($getVars['fD']) &&
				is_array($getVars['fD'])
			) {
				$fdArray = $getVars['fD'];
			} else if (!isset($getVars)) {
				$fdArray = t3lib_div::_GP('fD', 1);
			}

			if (
				isset($fdArray) &&
				is_array($fdArray) &&
				isset($origArray) &&
				is_array($origArray)
			) {
				$fieldList = rawurldecode($fdArray['_FIELDLIST']);
				$setFixedArray = array_merge($origArray, $fdArray);
				$authCode = $authObj->setfixedHash($setFixedArray, $fieldList);

				if ($authCode == $feUserData['aC']) {
					$this->setTokenValid(TRUE);
				}
			}
		}

		if ($this->isTokenValid()) {
			$this->setValidRegHash($bValidRegHash);
			$this->setFeUserData($feUserData);
			$this->writeRedirectUrl();

			// generate a new token for the next created forms
			$token = $authObj->generateToken();
			$this->writeToken($token);
		} else {
			$this->setFeUserData(array());	// delete all FE user data when the token is not valid
			$this->writePassword('');	// delete the stored password
		}
	}


	public function setRegHash ($regHash) {
		$this->regHash = $regHash;
	}


	public function getRegHash () {
		return $this->regHash;
	}


	public function setValidRegHash ($bValidRegHash) {
		$this->bValidRegHash = $bValidRegHash;
	}


	public function getValidRegHash () {
		return $this->bValidRegHash;
	}

	/* reduces to the field list which are allowed to be shown */
	public function getOpenFields ($fields) {
		$securedFieldArray = $this->getSecuredFieldArray();
		$newFieldArray = array();
		$fieldArray = t3lib_div::trimExplode(',', $fields);
		array_unique($fieldArray);

		foreach ($securedFieldArray as $securedField) {
			$k = array_search($securedField, $fieldArray);
			if ($k !== FALSE)	{
				unset($fieldArray[$k]);
			}
		}
		$fields = implode(',', $fieldArray);
		return $fields;
	}

	/*************************************
	* PASSWORD TRANSMISSION SECURITY
	*************************************/
	/**
	* Sets the transmission security level
	*
	* @return	void
	*/
	protected function setTransmissionSecurityLevel () {
		$supportedTransmissionSecurityLevels = array('normal', 'rsa');
		if (in_array($GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'], $supportedTransmissionSecurityLevels)) {
			if (t3lib_extMgm::isLoaded('rsaauth')) {
					// rsaauth in TYPO3 4.5 misses autoload
				if (!class_exists('tx_rsaauth_backendfactory')) {
					require_once(t3lib_extMgm::extPath('rsaauth') . 'sv1/backends/class.tx_rsaauth_backendfactory.php');
					require_once(t3lib_extMgm::extPath('rsaauth') . 'sv1/storage/class.tx_rsaauth_storagefactory.php');
				}
				if (tx_rsaauth_backendfactory::getBackend() !== NULL) {
					$this->transmissionSecurityLevel = $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
				}
			}
		}
	}
	/**
	* Gets the transmission security level
	*
	* @return	string	the transmission security level
	*/
	public function getTransmissionSecurityLevel () {
		return $this->transmissionSecurityLevel;
	}
	/**
	* Decrypts the password that was encrypted for transmission
	*
	* @param array $row: data array that may contain passwords
	* @return boolean TRUE if decryption was successful
	*/
	protected function decryptPassword (array &$row) {
		$success = TRUE;
		$fields = array('password', 'password_again');
		switch ($this->getTransmissionSecurityLevel()) {
			case 'rsa':
					// Get services from rsaauth
					// Can't simply use the authentication service because we have two fields to decrypt
				if (t3lib_extMgm::isLoaded('rsaauth')) {
					$backend = tx_rsaauth_backendfactory::getBackend();
					$storage = tx_rsaauth_storagefactory::getStorage();
					/* @var $storage tx_rsaauth_abstract_storage */
					if (is_object($backend) && is_object($storage)) {
						$key = $storage->get();
						if ($key != NULL) {
							foreach ($fields as $field) {
								if (isset($row[$field]) && $row[$field] !== '') {
									if (substr($row[$field], 0, 4) === 'rsa:') {
											// Decode password
										$result = $backend->decrypt($key, substr($row[$field], 4));
										if ($result) {
											$row[$field] = $result;
										} else {
											$success = FALSE;
											t3lib_div::devLog('RSA auth service failed to process incoming password', 'sr_feuser_register', 3);
										}
									}
								}
							}
								// Remove the key
							$storage->put(NULL);
						} else {
							$success = FALSE;
							t3lib_div::devLog('RSA auth service failed to retrieve private key', 'sr_feuser_register', 3);
						}
					} else {
						$success = FALSE;
						t3lib_div::devLog('Required RSA auth backend not available', 'sr_feuser_register', 3);
					}
				} else {
					$success = FALSE;
				}
				break;
			case 'normal':
			default:
					// Nothing to decrypt
				break;	
		}
		return $success;
	}

	/*************************************
	* PASSWORD STORAGE SECURITY
	*************************************/
	/**
	* Sets the storage security level
	*
	* @return	void
	*/
	protected function setStorageSecurityLevel () {
		$supportedStorageSecurityLevels = array('normal', 'salted');
		if (t3lib_extMgm::isLoaded('saltedpasswords')) {
			if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
				$this->storageSecurityLevel = 'salted';
			}
		}
	}
	/**
	* Gets the storage security level
	*
	* @return	string	the storage security level
	*/
	public function getStorageSecurityLevel () {
		return $this->storageSecurityLevel;
	}
	/**
	* Encrypts the password for secure storage
	*
	* @param	string	$password: password to encrypt
	* @return	string	encrypted password
	*/
	protected function encryptPasswordForStorage ($password) {
		$encryptedPassword = $password;
		if ($password !== '') {
			switch ($this->getStorageSecurityLevel()) {
				case 'salted':
					if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
						$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance(NULL);
						if (is_object($objSalt)) {
							$encryptedPassword = $objSalt->getHashedPassword($password);
						} else {
							t3lib_div::devLog('Could not get a salting instance from saltedpasswords', 'sr_feuser_register', 3);
						}
					} else {
						t3lib_div::devLog('Salted passwords not enabled in frontend', 'sr_feuser_register', 3);
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
				// Make sure openssl is available
			if (t3lib_extMgm::isLoaded('rsaauth')) {
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
			} else {
				t3lib_div::devLog('Required rsaauth extension not available', 'sr_feuser_register', 3);
			}
		}
	}

	/**
	* Decrypts the password for auto-login on confirmation
	*
	* @param	array	$dataArray: array containing the password to be decrypted
	* @param	string	$privateKey: the private key to decrypt the password
	* @return	void
	*/
	public function decryptPasswordForAutoLogin (&$dataArray, $privateKey) {
		$password = $dataArray['tx_srfeuserregister_password'];
		if ($password !== '') {
			if (t3lib_extMgm::isLoaded('rsaauth')) {
				$backend = tx_rsaauth_backendfactory::getBackend();
				if (is_object($backend) && $backend->isAvailable()) {
					$decryptedPassword = $backend->decrypt($privateKey, $password);
					if ($decryptedPassword) {
						$dataArray['password'] = $decryptedPassword;
					} else {
						t3lib_div::devLog('Failed to decrypt auto login password', 'sr_feuser_register', 3);
					}
				} else {
					t3lib_div::devLog('Required RSA auth backend not available', 'sr_feuser_register', 3);
				}
			} else {
				t3lib_div::devLog('Required rsaauth extension not available', 'sr_feuser_register', 3);
			}
		}
	}
	/*************************************
	* SECURED ARRAY HANDLING
	*************************************/
	/**
	* Retrieves values of secured fields from FE user session data
	*
	* @return	array	secured FE user session data 
	*/
	public function readSecuredArray () {
		$securedArray = array();
		$sessionData = $this->readSessionData();
		$securedFieldArray = $this->getSecuredFieldArray();
		foreach ($securedFieldArray as $securedField) {
			if (isset($sessionData[$securedField])) {
				$securedArray[$securedField] = $sessionData[$securedField];
			}
		}
		return $securedArray;
	}
	/**
	* Gets the array of names of secured fields
	*
	* @return	array	names of secured fields
	*/
	public function getSecuredFieldArray () {
		return $this->securedFieldArray;
	}
	/**
	* Sets the array of names of secured fields
	*
	* @param	array	array of names of secured fields
	* @return	void
	*/
	public function setSecuredFieldArray (array $securedFieldArray) {
		$this->securedFieldArray = array_merge($securedFieldArray, array('password', 'password_again'));
		
	}

	/*************************************
	* PASSWORD HANDLING
	*************************************/
	/**
	* Retreieves the password from session data and encrypt it for storage
	*
	* @return	string	the encrypted password
	*/
	public function readPasswordForStorage () {
		$password = $this->readPassword();
		return $this->encryptPasswordForStorage($password);
	}
	/**
	* Retrieves the password from session data
	*
	* @return	string	the password
	*/
	protected function readPassword () {
		$password = '';
		$securedArray = $this->readSecuredArray();
		if ($securedArray['password']) {
			$password = $securedArray['password'];
		}
		return $password;
	}
	/**
	* Writes the password to FE user session data
	*
	* @param	array	$row: data array that may contain password values
	*
	* @return void
	*/
	public function securePassword (array &$row) {
		$data = array();
			// Decrypt incoming password
		$passwordDecrypted = $this->decryptPassword($row);
			// Collect secured fields
		$securedFieldArray = $this->getSecuredFieldArray();
		foreach ($securedFieldArray as $securedField) {
			if (isset($row[$securedField]) && !empty($row[$securedField])) {
				$data[$securedField] = $row[$securedField];
			}
		}
			// Update FE user session data if required
		if (!empty($data)) {
			$this->writeSessionData($data);
		}
	}
	/**
	* Generates a value for the password and stores it the FE user session data
	*
	* @param	array	$dataArray: incoming array
	* @return	void
	*/
	public function generatePassword (array &$dataArray) {
		$generatedPassword = substr(md5(uniqid(microtime(), 1)), 0, 32);
		$dataArray['password'] = $generatedPassword;
		$dataArray['password_again'] = $generatedPassword;
		$this->securePassword($dataArray);
	}
	/**
	* Writes the password to session data
	*
	* @param	string	$password: the password
	* @return	void
	*/
	protected function writePassword ($password) {
		$sessionData = $this->readSessionData();
		if ($password === '') {
			unset($sessionData['password']);
			unset($sessionData['password_again']);
		} else {
			$sessionData['password'] = $password;
		}
		$this->writeSessionData($sessionData);
	}

	/*************************************
	* TOKEN HANDLING
	*************************************/
	/**
	* Whether the token was found valid
	*
	* @return	boolean	whether the token was found valid
	*/
	public function isTokenValid () {
		return $this->isTokenValid;
	}
	/**
	* Sets whether the token was found valid
	*
	* @return	boolean	$valid: whether the token was found valid
	* @return	void
	*/
	protected function setTokenValid ($valid) {
		$this->isTokenValid = $valid;
	}
	/**
	* Retrieves the token from FE user session data
	*
	* @return	string	token
	*/
	public function readToken () {
		$token = '';
		$sessionData = $this->readSessionData();
		if (isset($sessionData['token'])) {
			$token = $sessionData['token'];
		}
		return $token;
	}
	/**
	* Writes the token to FE user session data
	*
	* @param	string	token
	* @return void
	*/
	public function writeToken ($token) {
		$sessionData = $this->readSessionData();
		if ($token === '') {
			unset($sessionData['token']);
		} else {
			$sessionData['token'] = $token;
		}
		$this->writeSessionData($sessionData, FALSE);
	}
	/**
	* Retrieves the redirectUrl from FE user session data
	*
	* @return	string	redirectUrl
	*/
	public function readRedirectUrl () {
		$redirectUrl = '';
		$sessionData = $this->readSessionData();
		if (isset($sessionData['redirect_url'])) {
			$redirectUrl = $sessionData['redirect_url'];
		}
		return $redirectUrl;
	}
	/**
	* Writes the redirectUrl to FE user session data
	*
	* @return void
	*/
	protected function writeRedirectUrl () {
		$redirectUrl = t3lib_div::_GET('redirect_url');
		if ($redirectUrl !== '') {
			$data = array();
			$data['redirect_url'] = $redirectUrl;
			$this->writeSessionData($data);
		}
	}

	/*************************************
	* FE USER SESSION DATA HANDLING
	*************************************/
	/**
	* Retrieves session data
	*
	* @param	boolean	$readAll: whether to retrieve all session data or only data for this extension key
	* @return	array	session data
	*/
	public function readSessionData ($readAll = FALSE) {
		$sessionData = array();
		$extKey = $this->getExtKey();
		$allSessionData = $GLOBALS['TSFE']->fe_user->getKey('ses', 'feuser');
		if (isset($allSessionData) && is_array($allSessionData)) {
			if ($readAll) {
				$sessionData = $allSessionData;
			} else if (isset($allSessionData[$extKey])) {
				$sessionData = $allSessionData[$extKey];
			}
		}
		return $sessionData;
	}
	/**
	* Writes data to FE user session data
	*
	* @param	array	$data: the data to be written to FE user session data
	* @param	boolean	$keepToken: whether to keep any token
	* @param	boolean	$keepRedirectUrl: whether to keep any redirectUrl
	* @return	array	session data
	*/
	public function writeSessionData (array $data, $keepToken = TRUE, $keepRedirectUrl = TRUE) {
		$clearSession = empty($data);
		if ($keepToken && !isset($data['token'])) {
			$token = $this->readToken();
			if ($token !== '') {
				$data['token'] = $token;
			}
		}
		if ($keepRedirectUrl && !isset($data['redirect_url'])) {
			$redirect_url = $this->readRedirectUrl();
			if ($redirect_url !== '') {
				$data['redirect_url'] = $redirect_url;
			}
		}
		$extKey = $this->getExtKey();
			// Read all session data
		$allSessionData = $this->readSessionData(TRUE);
		if (is_array($allSessionData[$extKey])) {
			if ($clearSession) {
				$keys = array_keys($allSessionData[$extKey]);
				foreach ($keys as $key) {
					unset($allSessionData[$extKey][$key]);
				}
			}
			$allSessionData[$extKey] = t3lib_div::array_merge_recursive_overrule($allSessionData[$extKey], $data);
		} else {
			$allSessionData[$extKey] = $data;
		}
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'feuser', $allSessionData);
			// The feuser session data shall not get lost when coming back from external scripts
		$GLOBALS['TSFE']->fe_user->storeSessionData();
	}
	/**
	* Deletes all session data except the token and possibly the redirectUrl
	*
	* @param	boolean	$keepRedirectUrl: whether to keep any redirectUrl
	* @return	void
	*/
	public function clearSessionData ($keepRedirectUrl = TRUE) {
		$data = array();
		$this->writeSessionData($data, TRUE, $keepRedirectUrl);
	}

	/**
	* Changes potential malicious script code of the input to harmless HTML
	*
	* @return void
	*/
	public function getSecuredValue (
		$field,
		$value,
		$bHtmlSpecial
	) {
		$securedFieldArray = $this->getSecuredFieldArray();

		if ($field != '' && in_array($field, $securedFieldArray)) {
			// nothing for password and password_again
		} else {
			$value = htmlspecialchars_decode($value);
			if ($bHtmlSpecial) {
				$value = htmlspecialchars($value);
			}
		}

		$rc = $value;
		return $rc;
	}


	/**
	* Changes potential malicious script code of the input to harmless HTML
	*
	* @return void
	*/
	public function secureInput (&$dataArray, $bHtmlSpecial = TRUE) {

		if (isset($dataArray) && is_array($dataArray)) {
			foreach ($dataArray as $k => $value) {
				if (is_array($value)) {
					foreach ($value as $k2 => $value2) {
						if (is_array($value2)) {
							foreach ($value2 as $k3 => $value3) {
								$dataArray[$k][$k2][$k3] = $this->getSecuredValue($k3, $value3, $bHtmlSpecial);
							}
						} else {
							$dataArray[$k][$k2] = $this->getSecuredValue($k2, $value2, $bHtmlSpecial);
						}
					}
				} else {
					$dataArray[$k] = $this->getSecuredValue($k, $value, $bHtmlSpecial);
				}
			}
		}
	}

	public function bFieldsAreFilled ($row) {
		if (is_array($row)) {
			$rc = isset($row['username']);
		} else {
			$rc = FALSE;
		}
		return $rc;
	}


	public function useCaptcha ($theCode) {
		$rc = FALSE;

		if (
			(t3lib_extMgm::isLoaded('sr_freecap') &&
			t3lib_div::inList($this->conf[$theCode . '.']['fields'], 'captcha_response') &&
			is_array($this->conf[$theCode . '.']) &&
			is_array($this->conf[$theCode . '.']['evalValues.']) &&
			$this->conf[$theCode . '.']['evalValues.']['captcha_response'] == 'freecap')
		) {
			$rc = TRUE;
		}
		return $rc;
	}


	// example: plugin.tx_srfeuserregister_pi1.conf.sys_dmail_category.ALL.sys_language_uid = 0
	public function getSysLanguageUid ($theCode, $theTable) {

		if (
			isset($this->conf['conf.']) &&
			is_array($this->conf['conf.']) &&
			isset($this->conf['conf.'][$theTable . '.']) &&
			is_array($this->conf['conf.'][$theTable . '.']) &&
			isset($this->conf['conf.'][$theTable . '.'][$theCode . '.']) &&
			is_array($this->conf['conf.'][$theTable . '.'][$theCode . '.']) &&
			(
				class_exists('t3lib_utility_Math') ?
					t3lib_utility_Math::canBeInterpretedAsInteger($this->conf['conf.'][$theTable . '.'][$theCode . '.']['sys_language_uid']) :
					t3lib_div::testInt($this->conf['conf.'][$theTable . '.'][$theCode . '.']['sys_language_uid'])
			)
		)	{
			$rc = $this->conf['conf.'][$theTable . '.'][$theCode . '.']['sys_language_uid'];
		} else {
			$rc = $this->sys_language_content;
		}
		return $rc;
	}


	public function getPidTitle () {
		return $this->thePidTitle;
	}


	public function getSiteUrl () {
		return $this->site_url;
	}


	public function getPrefixId () {
		return $this->prefixId;
	}


	public function getExtKey () {
		return $this->extKey;
	}


	public function getPiVars () {
		return $this->piVars;
	}


	public function setPiVars ($piVars) {
		$this->piVars = $piVars;
	}


	public function getCmd () {
		return $this->cmd;
	}


	public function setCmd ($cmd) {
		$this->cmd = $cmd;
	}


	public function getCmdKey () {
		return $this->cmdKey;
	}


	public function setCmdKey ($cmdKey) {
		$this->cmdKey = $cmdKey;
	}


	public function getFeUserData ($k = '') {

		if ($k) {
			$rc = $this->feUserData[$k];
		} else {
			$rc = $this->feUserData;
		}
		return $rc;
	}


	public function setFeUserData ($v, $k='') {

		if ($k != '') {
			$this->feUserData[$k] = $v;
		} else {
			$this->feUserData = $v;
		}
	}


	public function getFailure () {
		return $this->failure;
	}


	public function setFailure ($failure) {
		$this->failure = $failure;
	}


	public function setSubmit ($bSubmit) {
		$this->bSubmit = $bSubmit;
	}


	public function getSubmit () {
		return $this->bSubmit;
	}


	public function setDoNotSave ($bParam) {
		$this->bDoNotSave = $bParam;
	}


	public function getDoNotSave () {
		return $this->bDoNotSave;
	}


	public function getPid ($type = '') {
		if ($type) {
			if (isset($this->pid[$type])) {
				$result = $this->pid[$type];
			}
		}
		if (!$result) {
			$bPidIsInt =
				(
					class_exists('t3lib_utility_Math') ?
						t3lib_utility_Math::canBeInterpretedAsInteger($this->conf['pid']) :
						t3lib_div::testInt($this->conf['pid'])
				);
			$result = ($bPidIsInt ? intval($this->conf['pid']) : $GLOBALS['TSFE']->id);
		}

		return $result;
	}


	public function setPid ($type, $pid)	{

		if (!intval($pid)) {
			switch ($type) {
				case 'infomail':
				case 'confirm':
					$pid = $this->getPid('register');
					break;
				case 'confirmInvitation':
					$pid = $this->getPid('confirm');
					break;
				default:
					$pid = $GLOBALS['TSFE']->id;
					break;
			}
		}
		$this->pid[$type] = $pid;
	}


	public function getMode () {
		return $this->mode;
	}


	public function setMode ($mode) {
		$this->mode = $mode;
	}

	public function getTable () {
		return $this->theTable;
	}

	public function setTable ($theTable) {
		$this->theTable = $theTable;
	}


	public function getRequiredArray () {
		return $this->requiredArray;
	}


	public function setRequiredArray ($requiredArray) {
		$this->requiredArray = $requiredArray;
	}


	public function getSetfixedEnabled () {
		return $this->setfixedEnabled;
	}


	public function setSetfixedEnabled ($setfixedEnabled) {
		$this->setfixedEnabled = $setfixedEnabled;
	}


	public function getBackURL () {
		$rc = rawurldecode($this->getFeUserData('backURL'));
		return $rc;
	}


	/**
	* Checks if preview display is on.
	*
	* @return boolean  true if preview display is on
	*/
	public function isPreview () {
		$rc = '';
		$cmdKey = $this->getCmdKey();

		$rc = ($this->conf[$cmdKey . '.']['preview'] && $this->getFeUserData('preview'));
		return $rc;
	}	// isPreview

	/*************************************
	* SHORT URL HANDLING
	*************************************/
	/**
	*  Get the stored variables using the hash value to access the database
	*/
	public function getShortUrl ($regHash) {

			// get the serialised array from the DB based on the passed hash value
		$varArray = array();
		$res =
			$GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'params',
				'cache_md5params',
				'md5hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(
					$regHash,
					'cache_md5params'
				)
			);

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$varArray = unserialize($row['params']);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

			// convert the array to one that will be properly incorporated into the GET global array.
		$retArray = array();
		foreach($varArray as $key => $val) {
			$val = str_replace('%2C', ',', $val);
			$search = array('[%5D]', '[%5B]');
			$replace = array('\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			if (!preg_match('/' . preg_quote(']') . '$/', $newkey)){
				$newkey .= "']";
			}
			eval("\$retArray" . $newkey . "='$val';");
		}
		return $retArray;
	}	// getShortUrl


	/**
	*  Get the stored variables using the hash value to access the database
	*/
	public function deleteShortUrl ($regHash) {

		if ($regHash != '')	{
			// get the serialised array from the DB based on the passed hash value
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_md5params', 'md5hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($regHash, 'cache_md5params'));
		}
	}


	/**
	*  Clears obsolete hashes used for short url's
	*/
	public function cleanShortUrlCache () {

		$shortUrlLife = intval($this->conf['shortUrlLife']) ? strval(intval($this->conf['shortUrlLife'])) : '30';
		$max_life = time() - (86400 * intval($shortUrlLife));
		$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');
	}	// cleanShortUrlCache
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php']);
}
?>