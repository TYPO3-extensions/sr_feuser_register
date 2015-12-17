<?php
namespace SJBR\SrFeuserRegister\Controller;

/*
 *  Copyright notice
 *
 *  (c) 2007-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Domain\Data;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\Utility\UrlUtility;
use SJBR\SrFeuserRegister\View\Email;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Action functions
 */

abstract class AbstractActionController
{

	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extensionKey;

	/**
	 *  Extension name
	 *
	 * @var string Extension name
	 */
	protected $extensionName;

	/**
	 * Prefix used for CSS classes and variables
	 *
	 * @var string
	 */
	protected $prefixId;

	/**
	 * The table in used
	 *
	 * @var string
	 */
	protected $theTable;

	/**
	 * The plugin configuration
	 *
	 * @var array
	 */
	protected $conf;

	/**
	 * The data object
	 *
	 * @var Data
	 */
	protected $ata;

	/**
	 * The request parameters object
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * The marker object
	 *
	 * @var Marker
	 */
	protected $marker;

	/**
	 * The email object
	 *
	 * @var Email;
	 */
	protected $email;

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param Data $ata: the data object
	 * @param Parameters $parameters: the request parameters object
	 * @param Marker $marker: the marker object
	 * @return void
	 */
	public function __construct(
		$extensionKey,
		$prefixId,
		$theTable,
		array $conf,
		Data $data,	
		Parameters $parameters,
		Marker $marker
	) {
		$this->extensionKey = $extensionKey;
		$this->extensionName = GeneralUtility::underscoredToUpperCamelCase($extensionKey);
		$this->prefixId = $prefixId;
		$this->theTable = $theTable;
	 	$this->conf = $conf;
	 	$this->data = $data;
	 	$this->parameters = $parameters;
	 	$this->marker = $marker;

		$this->marker->generateURLMarkers($this->data->getRecUid());
		$this->email = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\Email', $this->extKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
	}

	/**
	 * Get the extension key
	 *
	 * @return string the extension key
	 */
	public function getExtensionKey()
	{
		return $this->extensionKey;
	}

	/**
	 * Get the prefix id
	 *
	 * @return string the prefix id
	 */
	public function getPrefixId()
	{
		return $this->prefixId;
	}

	/**
	 * Get the table in use
	 *
	 * @return string the table in use
	 */
	public function getTable()
	{
		return $this->theTable;
	}

	/**
	 * Get the request parameters
	 *
	 * @return Parameters the request parameters
	 */
	public function getParameters()
	{
		return $this->parameters;
	}

	/**
	 * Get the use of the password field
	 *
	 * @return boolean whether password field is used
	 */
	protected function getUsePassword()
	{
		return $this->theTable === 'fe_users' && isset($this->conf['create.']['evalValues.']['password']);
	}

	/**
	 * Get the use of the password again field
	 *
	 * @return boolean whether password again field is used
	 */
	protected function getUsePasswordAgain()
	{
		return $this->getUsePassword() && GeneralUtility::inList($this->conf['create.']['evalValues.']['password'], 'twice');
	}

	/**
	 * Perform user login and redirect to configured url, if any
	 *
	 * @param array	$username: user name
	 * @param array	$password: password (decrypted) 
	 * @param boolean $redirect: whether to redirect after login or not; If true, then you must immediately call exit after this call
	 * @return boolean true, if login was successful, false otherwise
	 */
	protected function login($username, $password, $redirect = true)
	{
		$success = true;
		$message = '';
		// Log the user in
		$loginData = array(
			'uname' => $username,
			'uident' => $password,
			'uident_text' => $password,
			'status' => 'login',
		);
		// Check against configured pid (defaulting to current page)
		$GLOBALS['TSFE']->fe_user->checkPid = true;
		$GLOBALS['TSFE']->fe_user->checkPid_value = (int) $this->parameters->getPid();
		// Get authentication info array
		$authInfo = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
		// Get user info
		$user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($authInfo['db_user'], $loginData['uname']);
		if (is_array($user)) {
			// Get the appropriate authentication service
			$authServiceObj = GeneralUtility::makeInstanceService('auth', 'authUserFE');
			// Check authentication
			if (is_object($authServiceObj)) {
				$ok = $authServiceObj->compareUident($user, $loginData);
				if ($ok) {
					// Login successfull: create user session
					$GLOBALS['TSFE']->fe_user->createUserSession($user);
					$GLOBALS['TSFE']->initUserGroups();
					$GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
					$GLOBALS['TSFE']->loginUser = 1;
				} else {
					// Login failed...
					SessionData::clearSessionData($this->extensionKey, false);
					$message = LocalizationUtility::translate('internal_auto_login_failed', $this->extensionName);
					$success = false;
				}
			} else {
				// Required authentication service not available
				$message = LocalizationUtility::translate('internal_required_authentication_service_not_available', $this->extensionName);
				GeneralUtility::sysLog($message, $this->extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
				SessionData::clearSessionData($this->extensionKey, false);
				$success = false;
			}
		} else {
			// No enabled user of the given name
			$message = sprintf(LocalizationUtility::translate('internal_no_enabled_user', $this->extensionName), $loginData['uname']);
			SessionData::clearSessionData($this->extensionKey, false);
			$success = false;
		}
		// Delete regHash
		if ($this->parameters->getValidRegHash()) {
			$regHash = $this->parameters->getRegHash();
			HashUtility::deleteHash($regHash);
		}
		if (!$success) {
			SessionData::clearSessionData($this->extensionKey, false);
		   if ($message !== '') {
			   GeneralUtility::sysLog($message, $this->extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
		   }
        }
		if ($redirect) {
			// Redirect to configured page, if any
			$redirectUrl = SessionData::readRedirectUrl($this->extensionKey);
			if (!$redirectUrl && $success) {
				$redirectUrl = trim($this->conf['autoLoginRedirect_url']);
			}
			if (!$redirectUrl) {
				if ((int) $this->conf['loginPID']) {
					$redirectUrl = UrlUtility::get($this->prefixId, '', (int) $this->conf['loginPID'], array(), array(), false);
				} else {
					$redirectUrl = UrlUtility::getSiteUrl();
				}
			}
			header('Location: ' . GeneralUtility::locationHeaderUrl($redirectUrl));
		}
		return $success;
	}
}