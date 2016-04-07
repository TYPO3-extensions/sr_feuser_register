<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2015-2016 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Security\StorageSecurity;
use SJBR\SrFeuserRegister\Security\TransmissionSecurity;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Frontend user session data handling
 */
class SessionData
{
	/**
	 * Retrieves session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param boolean $readAll: whether to retrieve all session data or only data for this extension key
	 * @return array session data
	 */
	protected static function readSessionData($extensionKey, $readAll = false)
	{
		$sessionData = array();
		$allSessionData = $GLOBALS['TSFE']->fe_user->getKey('ses', 'feuser');
		if (isset($allSessionData) && is_array($allSessionData)) {
			if ($readAll) {
				$sessionData = $allSessionData;
			} else if (isset($allSessionData[$extensionKey])) {
				$sessionData = $allSessionData[$extensionKey];
			}
		}
		return $sessionData;
	}

	/**
	 * Writes data to FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param array	$data: the data to be written to FE user session data
	 * @param boolean $keepToken: whether to keep any token
	 * @param boolean $keepRedirectUrl: whether to keep any redirectUrl
	 * @return array session data
	 */
	protected static function writeSessionData($extensionKey, array $data, $keepToken = true, $keepRedirectUrl = true)
	{
		$clearSession = empty($data);
		if ($keepToken && !isset($data['token'])) {
			$token = self::readToken($extensionKey);
			if ($token != '') {
				$data['token'] = $token;
			}
		}
		if ($keepRedirectUrl && !isset($data['redirect_url'])) {
			$redirect_url = self::readRedirectUrl($extensionKey);
			if ($redirect_url != '') {
				$data['redirect_url'] = $redirect_url;
			}
		}
		// Read all session data
		$allSessionData = self::readSessionData($extensionKey, true);
		if (isset($allSessionData[$extensionKey]) && is_array($allSessionData[$extensionKey])) {
			$keys = array_keys($allSessionData[$extensionKey]);
			if ($clearSession) {
				foreach ($keys as $key) {
					unset($allSessionData[$extensionKey][$key]);
				}
			}
		} else {
			$allSessionData[$extensionKey] = array();
		}
		ArrayUtility::mergeRecursiveWithOverrule($allSessionData[$extensionKey], $data);
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'feuser', $allSessionData);
		// The feuser session data shall not get lost when coming back from external scripts
		$GLOBALS['TSFE']->fe_user->storeSessionData($extensionKey);
	}

	/**
	 * Deletes all session data except the token and possibly the redirectUrl
	 *
	 * @param string $extensionKey: the extension key
	 * @param boolean $keepRedirectUrl: whether to keep any redirectUrl
	 * @return void
	 */
	public static function clearSessionData($keepRedirectUrl = true)
	{
		$data = array();
		$keepToken  = true;
		self::writeSessionData($extensionKey, $data, $keepToken, $keepRedirectUrl);
	}

	/**
	 * Retrieves the token from FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @return string token
	 */
	public static function readToken($extensionKey)
	{
		$token = '';
		$sessionData = self::readSessionData($extensionKey);
		if (isset($sessionData['token'])) {
			$token = $sessionData['token'];
		}
		return $token;
	}

	/**
	 * Writes the token to FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param string token
	 * @return void
	 */
	public static function writeToken($extensionKey, $token)
	{
		$sessionData = self::readSessionData($extensionKey);
		if ($token == '') {
			$sessionData['token'] = '__UNSET';
		} else {
			$sessionData['token'] = $token;
		}
		self::writeSessionData($extensionKey, $sessionData, false);
	}

	/**
	 * Retrieves the redirectUrl from FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @return string redirectUrl
	 */
	public static function readRedirectUrl($extensionKey)
	{
		$redirectUrl = '';
		$sessionData = self::readSessionData($extensionKey);
		if (isset($sessionData['redirect_url'])) {
			$redirectUrl = $sessionData['redirect_url'];
		}
		return $redirectUrl;
	}

	/**
	 * Writes the redirectUrl to FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @return void
	 */
	public static function writeRedirectUrl($extensionKey)
	{
		$redirectUrl = GeneralUtility::_GET('redirect_url');
		if ($redirectUrl != '') {
			$data = array();
			$data['redirect_url'] = $redirectUrl;
			self::writeSessionData($extensionKey, $data);
		}
	}

	/**
	 * Retrieves values of secured fields from FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @return array secured FE user session data
	 */
	public static function readSecuredArray($extensionKey)
	{
		$securedArray = array();
		$sessionData = self::readSessionData($extensionKey);
		$fields = SecuredData::getSecuredFields();
		foreach ($fields as $securedField) {
			if (isset($sessionData[$securedField])) {
				$securedArray[$securedField] = $sessionData[$securedField];
			}
		}
		return $securedArray;
	}

	/**
	 * Retrieves the password from FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @return string redirectUrl
	 */
	public static function readPassword($extensionKey)
	{
		$sessionData = self::readSessionData($extensionKey);
		return array(
			'password' => isset($sessionData['password']) ? $sessionData['password'] : '',
			'password_again' => isset($sessionData['password_again']) ? $sessionData['password_again'] : ''
		);
	}

	/**
	 * Writes the password to session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $password: the password
	 * @return void
	 */
	public static function writePassword($extensionKey, $password, $passwordAgain = '')
	{
		$sessionData = self::readSessionData($extensionKey);
		if ($password === '') {
			$sessionData['password'] = '__UNSET';
			$sessionData['password_again'] = '__UNSET';
		} else {
			$sessionData['password'] = $password;
			if ($passwordAgain !== '') {
				$sessionData['password_again'] = $passwordAgain;
			}
		}
		self::writeSessionData($extensionKey, $sessionData);
	}

	/**
	 * Retrieve the password from session data and encrypt it for storage
	 *
	 * @param string $extensionKey: the extension key
	 * @return string the encrypted password
	 */
	public static function readPasswordForStorage($extensionKey)
	{
		$password = self::readPassword($extensionKey);
		$result = StorageSecurity::encryptPasswordForStorage($password['password']);
		return $result;
	}

	/**
	 * Writes the password to FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param array $row: data array that may contain password values
	 * @return void
	 */
	public static function securePassword($extensionKey, array $row)
	{
		// Decrypt incoming password
		$passwordRow = self::readPassword($extensionKey);
		$passwordDecrypted = TransmissionSecurity::decryptIncomingFields($passwordRow);
		if ($passwordDecrypted) {
			if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version()) >= 7000000) {
				self::writePassword($extensionKey, $passwordRow['password'], $passwordRow['password_again']);
			} else {
				self::writePassword($extensionKey, $passwordRow['password'], $passwordRow['password']);
			}
		} else if (TransmissionSecurity::getTransmissionSecurityLevel() !== 'rsa') {
			self::writePassword($extensionKey, $passwordRow['password'], $row['password_again']);
		}
	}

	/**
	 * Generates a value for the password and stores it the FE user session data
	 *
	 * @param string $extensionKey: the extension key
	 * @param array $dataArray: incoming array
	 * @return void
	 */
	public static function generatePassword($extensionKey, array &$dataArray)
	{
		$generatedPassword = substr(md5(uniqid(microtime(), 1)), 0, 32);
		$dataArray['password'] = $generatedPassword;
		$dataArray['password_again'] = $generatedPassword;
		self::writePassword($extensionKey, $generatedPassword, $generatedPassword);
	}
}