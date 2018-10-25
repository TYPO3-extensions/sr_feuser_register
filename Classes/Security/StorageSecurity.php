<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2012-2018 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use Psr\Log\LoggerInterface;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;

/**
 * Storage security functions
 */
class StorageSecurity
{
	/**
	 * @var string Extension name
	 */
	static protected $extensionName = 'SrFeuserRegister';

	/**
	 * Encrypts the password for secure storage
	 *
	 * @param string $password: password to encrypt
	 * @return string/boolean encrypted password, boolean false in case of an error
	 */
	static public function encryptPasswordForStorage($password)
	{
		$encryptedPassword = $password;
		if ($password != '') {
			$objSalt = PasswordHashFactory::getDefaultHashInstance('FE');
			if (is_object($objSalt)) {
				$encryptedPassword = $objSalt->getHashedPassword($password);
			} else {
				$encryptedPassword = false;
				// Could not get a salting instance
				// Should not happen
			}
		}
		return $encryptedPassword;
	}

	/**
	 * Initializes the password for auto-login on confirmation
	 *
	 * @param array $dataArray
	 * @return void
	 */
	static public function initializeAutoLoginPassword(array &$dataArray)
	{
		$dataArray['tx_srfeuserregister_password'] = '';
		unset($dataArray['auto_login_key']);
	}

	/**
	 * Determines if auto login should be attempted
	 *
	 * @param array $feuData: incoming fe_users parameters
	 * @param array $dataArray: fe_users row
	 * @return boolean true, if auto-login should be attempted
	 */
	static public function getAutoLoginIsRequested(array $feuData, array &$dataArray)
	{
		$autoLoginIsRequested = false;
		if (isset($feuData['key']) && $feuData['key'] !== '') {
			$dataArray['auto_login_key'] = $feuData['key'];
			$autoLoginIsRequested = true;
		}
		return $autoLoginIsRequested;
	}

	/**
	 * Encrypts the password for auto-login on confirmation
	 *
	 * @param array $dataArray: array containing the password to be encrypted
	 * @return void
	 */
	static public function encryptPasswordForAutoLogin(array &$dataArray)
	{
		$password = $dataArray['password'];
		$privateKey = '';
		$cryptedPassword = '';
		if ($password != '') {
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
	static public function decryptPasswordForAutoLogin(array &$dataArray, array $row)
	{
		if (isset($row['auto_login_key'])) {
			$privateKey = $row['auto_login_key'];
			if ($privateKey !== '') {
				$password = $dataArray['tx_srfeuserregister_password'];
				if ($password != '') {
					$backend = BackendFactory::getBackend();
					if (is_object($backend) && $backend->isAvailable()) {
						$decryptedPassword = $backend->decrypt($privateKey, $password);
						if ($decryptedPassword) {
							$dataArray['password'] = $decryptedPassword;
						} else {
							// Failed to decrypt auto login password
							$message = LocalizationUtility::translate('internal_decrypt_auto_login_failed', self::$extensionName);
							static::getLogger()->error(self::$extensionName . ': ' . $message);
						}
					} else {
						// Required RSA auth backend not available
						// Should not happen
					}
				}
			}
		}
	}

    /**
     * @return LoggerInterface
     */
    protected static function getLogger()
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }
}