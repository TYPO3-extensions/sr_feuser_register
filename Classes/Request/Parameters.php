<?php
namespace SJBR\SrFeuserRegister\Request;

/*
 *  Copyright notice
 *
 *  (c) 2007-2012 Franz Holzinger <franz@ttproducts.de>
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

use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Request parameters
 */
class Parameters
{
	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extensionKey;

	/**
	 * Prefix used for CSS classes and variables
	 *
	 * @var string
	 */
	protected $prefixId;

	/**
	 * The table being used
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
	 * The plugin object
	 *
	 * @var AbstractPlugin
	 */
	protected $pibaseObj;

	/**
	 * The authentication code
	 *
	 * @var string
	 */
	static protected $authCode = '';

	/**
	 * The control data from get/post parameters (with no prefix or as $this->prefix[parameter]) including regHash
	 *
	 * @var array
	 */
	protected $feUserData = array();

	/**
	 * The regHash if available among GET parameters (after validation)
	 *
	 * @var string
	 */
	protected $regHash = '';

	/**
	 * The setfixed variables (with prefix fD)
	 *
	 * @var array
	 */
	protected $setFixedVars = array();

	/**
	 * The input command 
	 *
	 * @var string
	 */
	protected $cmd = '';

	/**
	 * The title of the page of the records
	 *
	 * @var string
	 */
	protected $pidTitle;

	/**
	 * The pids of the various types
	 *
	 * @var array
	 */
	protected $pid = array();

	/**
	 * Whether the token was found valid
	 *
	 * @var bool
	 */
	protected $isTokenValid = false;
	
	/**
	 * The command key
	 *
	 * @var string
	 */
	protected $cmdKey = '';

	public $thePid = 0;
	public $piVars;
	public $bSubmit = false;
	public $bDoNotSave = false;

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param array $piVars: the pivars
	 * @param AbstractPlugin $pibaseObj
	 * @return void
	 */
	public function __construct(
		$extensionKey,
		$prefixId,
		$theTable,
		array &$conf,
		array $piVars,
		AbstractPlugin $pibaseObj
	) {
	 	$this->extensionKey = $extensionKey;
	 	$this->prefixId = $prefixId;
	 	$this->theTable = $theTable;
	 	$this->conf =& $conf;
	 	$this->piVars = $piVars;
	 	$this->pibaseObj = $pibaseObj;
	 	$this->initialize();
	}

	/**
	 * Establishes context based on configuration settings, piVars and session data
	 *
	 * @return void
	 */
	protected function initialize()
	{
		// Determine whether a password is in use
		$this->setPassword();
		
		// Populate the feUserData array from Get/Post variables, including from any available regHash
		$this->setFeUserData();

		// Set the authCode
		$this->setAuthCode($this->getFeUserData('aC'));

		// Set the command
		$this->setCmd();
	
		// Validate the token
		$this->validateToken();

		if ($this->isTokenValid()) {
			SessionData::writeRedirectUrl($this->extensionKey);
			// Generate a new token for the next created forms
			$token = Authentication::generateToken();
			SessionData::writeToken($this->extensionKey, $token);
			$this->setTokenValid(true);
			// Initialize various data
			$this->setPids();
			$this->setPidTitle();
		} else {
			// Erase all FE user data when the token is not valid
			$this->resetFeUserData();
			// Erase any stored password
			SessionData::writePassword($this->extensionKey, '');
		}
	}

	/**
	 * Set the password
	 *
	 * @return void
	 */
	protected function setPassword()
	{
		if ($this->theTable === 'fe_users' && isset($this->conf['create.']['evalValues.']['password'])) {
			// Establish compatibility with the extension Felogin
			$value = GeneralUtility::_GP('pass');
			if (isset($value)) {
				SessionData::writePassword($this->extensionKey, $value, '');
			}
		}
	}

	/**
	 * Initialize the pids of various types
	 *
	 * @return void
	 */
	protected function setPids()
	{
		$pidTypeArray = array('login', 'register', 'edit', 'infomail', 'confirm', 'confirmInvitation');
		foreach ($pidTypeArray as $type) {
			$this->setPid($type, $this->conf[$type . 'PID']);
		}
	}

	public function getPids()
	{
		return $this->pid;
	}

	/**
	 * Set the pid of a given type
	 *
	 * @param string $type: the type of pid
	 * @param string $pid: the pid
	 * @return void
	 */
	protected function setPid($type, $pid)
	{
		if (!MathUtility::canBeInterpretedAsInteger($pid) || !$pid) {
			switch ($type) {
				case 'infomail':
				case 'confirm':
					$pid = $this->getPid('register');
					break;
				case 'confirmInvitation':
					$pid = $this->getPid('confirm');
					break;
				default:
					$pid = MathUtility::canBeInterpretedAsInteger($this->conf['pid']) ? (int) $this->conf['pid'] : $GLOBALS['TSFE']->id;
					break;
			}
		}
		$this->pid[$type] = $pid;
	}

	/**
	 * Get the configured pid of a given type
	 *
	 * @param string $type: the type of pid requested
	 * @return string the configured pid of the requested type
	 */
	public function getPid($type = '')
	{
		$pid = 0;
		if ($type) {
			if (isset($this->pid[$type])) {
				$pid = $this->pid[$type];
			}
		}
		if (!$pid) {
			if ($this->cObj->data['pages']) {
				$pids = explode(',', $this->cObj->data['pages']);
				$pid = $pids['0'];
			}
			if (!$pid) {
				$pid = MathUtility::canBeInterpretedAsInteger($this->conf['pid']) ? (int) $this->conf['pid'] : $GLOBALS['TSFE']->id;
			}
		}
		return $pid;
	}

	/**
	 * Set the title of the page of the records
	 *
	 * @return void
	 */
	protected function setPidTitle()
	{
		$pidRecord = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$pidRecord->init(0);
		$pidRecord->sys_language_uid = (int) $GLOBALS['TSFE']->config['config']['sys_language_uid'];
		$row = $pidRecord->getPage($this->getPid());
		$this->pidTitle = $this->conf['pidTitleOverride'] ?: $row['title'];
	}

	/**
	 * Get the title of the page of the records
	 *
	 * @return string the title of the page of the records
	 */
	public function getPidTitle()
	{
		return $this->pidTitle;
	}

	/**
	 * Set the feUserData array including from any available regHash
	 *
	 * @return void
	 */
	protected function setFeUserData()
	{
		$feUserData = GeneralUtility::_GP($this->prefixId);
		if (isset($feUserData) && is_array($feUserData)) {
			$this->feUserData = $feUserData;
		}
		// Get if short url feature is enabled and hash variable if provided
		if ($this->conf['useShortUrls'] && $this->feUserData['regHash']) {
			HashUtility::cleanHashCache($this->conf);
			// Check and process for short URL if the regHash GET parameter exists
			$getHashVars = HashUtility::getParametersFromHash($this->feUserData['regHash']);
			if (!empty($getHashVars) && is_array($getHashVars)) {
				$this->setRegHash($this->feUserData['regHash']);
				$keepFieldArray = array('sFK', 'cmd', 'submit', 'fetch', 'regHash', 'preview');
				$keepFeuserData = array();
				// Copy the original values which must not be overridden by the regHash stored values
				foreach ($keepFieldArray as $keepField) {
					if (isset($feUserData[$keepField])) {
						$keepFeuserData[$keepField] = $this->feUserData[$keepField];
					}
				}
				// Restore former GET values from the url
				foreach ($getHashVars as $k => $v) {
					GeneralUtility::_GETset($v, $k);
				}
				// Overlay the $this->feUserData with the hashed values if rU is the same in both
				$hashedFeUserData = $getHashVars[$this->prefixId];
				if (isset($hashedFeUserData) && is_array($hashedFeUserData)) {
					if ($hashedFeUserData['rU'] > 0 && $hashedFeUserData['rU'] === $this->feUserData['rU']) {
						$this->feUserData = array_merge($this->feUserData, $hashedFeUserData);
					} else {
						$this->feUserData = $hashedFeUserData;
					}
				}
				// Overlay with the preserved values
				$this->feUserData = array_merge($this->feUserData, $keepFeuserData);
				// Set the fD array if provided by the hash
				if (isset($getHashVars['fD']) && is_array($getHashVars['fD'])) {
					$this->setFixedVars = $getHashVars['fD'];
				}
			}
		}
		// Establishing compatibility with Direct Mail and Rsaauth
		$piVarArray = array('rU', 'aC', 'sFK', 'submit');
		foreach ($piVarArray as $pivar) {
			$value = htmlspecialchars(GeneralUtility::_GP($pivar));
			if ($value != '') {
				$this->feUserData[$pivar] = $value;
			}
		}
		// Cleanup input values
		SecuredData::secureInput($this->feUserData);
	}

	/**
	 * Erase the fe user data array
	 *
	 * @return void
	 */
	protected function resetFeUserData()
	{
		$this->feUserData = array();
	}

	/**
	 * Set a value for a key of feUserData
	 *
	 * @param string $key: the key for which the value should be set
	 * @param mixed $value: the value to be assigned
	 * @return void
	 */
	public function setFeUserDataValue($key, $value)
	{
		$this->feUserData[$key] = $value;
	}

	/**
	 * Get the feUserData array or an index of the array
	 *
	 * @param string $key: the key for which the value should be returned
	 * @return mixed the value of the specified key or the full array
	 */
	public function getFeUserData($key = '')
	{
		return empty($key) ? $this->feUserData : $this->feUserData[$key];
	}

	/**
	 * Set the regHash (only when found valid)
	 *
	 * @param string $regHash
	 * @return void
	 */
	protected function setRegHash($regHash)
	{
		$this->regHash = $regHash;
	}

	/**
	 * Get the regHash (only when found valid)
	 *
	 * @return string $regHash
	 */
	public function getRegHash()
	{
		return $this->regHash;
	}

	/**
	 * Get the validity of the regHash
	 *
	 * @return bool true, if the regHash was found valid
	 */
	public function getValidRegHash()
	{
		return !empty($this->regHash);
	}

	/**
	 * Get the authentication code
	 *
	 * @return string the code
	 */
	public function getAuthCode()
	{
		return $this->authCode;
	}

	/**
	 * Set the authentication code
	 *
	 * @param string $code: the code
	 * @return void
	 */
	protected function setAuthCode($code)
	{
		$this->authCode = $code;
	}

	/**
	 * Set the command
	 *
	 * @return void
	 */
	protected function setCmd()
	{
		$this->cmd = $this->conf['defaultCODE'] ?: '';
		// flexform overrides TS config
		$this->pibaseObj->pi_initPIflexForm();
		$cmd = $this->pibaseObj->pi_getFFvalue($this->pibaseObj->cObj->data['pi_flexform'], 'display_mode', 'sDEF');
		if (!empty($cmd)) {
			$this->cmd = $cmd;
		}
		// Query variable &cmd used by Direct Mail overrides flexform
		$cmd = htmlspecialchars(GeneralUtility::_GP('cmd'));
		if (!empty($cmd)) {
			$this->cmd = $cmd;
		}
		// Query variable &prefixId[cmd] (possibly from regHash) overrides query variable &cmd, if not empty
		$cmd = $this->getFeUserData('cmd');
		if (!empty($cmd)) {
			$this->cmd = $cmd;
		}
		$this->cmd = strtolower($this->cmd);
	}

	/**
	 * Get the cmd
	 *
	 * @return string the command
	 */
	public function getCmd()
	{
		return $this->cmd;
	}

	/**
	 * Validate the token
	 *
	 * @return void
	 */
	protected function validateToken() {
		$feUserData = $this->getFeUserData();
		$startCmdIsSecure = (count($feUserData) === 1 && in_array($feUserData['cmd'], array('create', 'edit', 'password')));
		// Get the data for the uid provided in query parameters
		$theUid = 0;
		if (MathUtility::canBeInterpretedAsInteger($feUserData['rU'])) {
			$theUid = (int) $feUserData['rU'];
			$origArray = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $theUid);
		}
		// Get the token
		$token = '';
		if (isset($origArray) && is_array($origArray) && $this->getCmd() === 'setfixed' && !empty($origArray['token'])) {
			// Use the token from the FE user data
			$token = $origArray['token'];
		} else if ($this->getCmd() !== 'setfixed') {
			// Get latest token from session data
			$token = SessionData::readToken($this->extensionKey);
		}
		// Validate the token
		if (empty($feUserData) || $startCmdIsSecure || (!empty($token) && $feUserData['token'] === $token)) {
			$this->setTokenValid(true);
		} else if ($theUid > 0) {
			// When processing a setfixed link from other extensions,
			// there might no token and no short url regHash, but there might be an authCode
			if (!empty($this->regHash) || !$this->conf['useShortUrls'] || $this->getAuthCode()) {
				if (empty($this->setFixedVars)) {
					$this->setFixedVars = GeneralUtility::_GP('fD', 1);
				}
				if (isset($this->setFixedVars) && is_array($this->setFixedVars) && isset($origArray) && is_array($origArray)) {
					// Calculate the setfixed hash from incoming data
					$fieldList = rawurldecode($this->setFixedVars['_FIELDLIST']);
					$setFixedArray = array_merge($origArray, $this->setFixedVars);
					$codeLength = strlen($this->getAuthCode());
					$sFK = $this->getFeUserData('sFK');
					// Let's try with a code length of 8 in case this link is coming from direct mail
					if ($codeLength === 8 && in_array($sFK, array('DELETE', 'EDIT', 'UNSUBSCRIBE'))) {
						$authCode = Authentication::setfixedHash($setFixedArray, $this->conf, $fieldList, $codeLength);
					} else {
						$authCode = Authentication::setfixedHash($setFixedArray, $this->conf, $fieldList);
					}
					if (!strcmp($this->getAuthCode(), $authCode)) {
						// We use the valid authCode in place of token
						$this->setFeUserDataValue('token', $authCode);
						$this->setTokenValid(true);
					}
				} else {
					// aC parameter from URL does not match the hash calculated from the given user record data
					if ($token === '' && (!isset($this->setFixedVars) || !is_array($this->setFixedVars))) {
						$message = 'The submitted authcode does not match the one from the given user record, perhaps due to missing fD[_FIELDLIST] parameter.';
						GeneralUtility::sysLog($message, $this->extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					}
				}
			}
		}		
	}

	/**
	 * Get whether the token was found valid
	 *
	 * @return boolean whether the token was found valid
	 */
	public function isTokenValid()
	{
		return $this->isTokenValid;
	}

	/**
	 * Set whether the token was found valid
	 *
	 * @return boolean $valid: whether the token was found valid
	 * @return void
	 */
	protected function setTokenValid($valid)
	{
		$this->isTokenValid = $valid;
	}

	public function getCmdKey()
	{
		return $this->cmdKey;
	}

	public function setCmdKey($cmdKey)
	{
		$this->cmdKey = $cmdKey;
	}

	public function setSubmit($bSubmit)
	{
		$this->bSubmit = $bSubmit;
	}

	public function getSubmit()
	{
		return $this->bSubmit;
	}

	public function setDoNotSave($bParam)
	{
		$this->bDoNotSave = $bParam;
	}

	public function getDoNotSave()
	{
		return $this->bDoNotSave;
	}

	public function getBackURL()
	{
		$rc = rawurldecode($this->getFeUserData('backURL'));
		return $rc;
	}

	/**
	 * Checks whether preview display is on.
	 *
	 * @return bool true, if preview display is on
	 */
	public function isPreview()
	{
		$cmdKey = $this->getCmdKey();
		return $this->conf[$cmdKey . '.']['preview'] && $this->getFeUserData('preview');
	}

	/**
	 * Get whether setfixed is enabled
	 *
	 * @return bool true, if setfixed is enabled
	 */
	public function getSetfixedEnabled()
	{
		return (
			$this->conf['enableEmailConfirmation']
			|| ($this->theTable === 'fe_users' && $this->conf['enableAdminReview'])
			|| $this->conf['setfixed']
			|| ($this->conf['infomail'] && ($this->getCmd() === 'setfixed' || $this->getCmd() === 'infomail'))
		);
	}
}