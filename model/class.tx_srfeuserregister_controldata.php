<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Franz Holzinger (franz@ttproducts.de)
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
	public $useMd5Password = FALSE;
	public $setfixedEnabled = 0;
	public $bSubmit = FALSE;
	public $bDoNotSave = FALSE;
	public $failure = FALSE; // is set if data did not have the required fields set.
	public $sys_language_content;
	public $feUserData = array();
	public $jsMd5Added; // If the JavaScript for MD5 encoding has already been added
	public $securedFieldArray = array('password', 'password_again');
	public $bTokenValid = FALSE;
	public $dummyPassword = 'Joh 3,16';
	public $bValidRegHash;
	public $regHash;


	public function init (&$conf, $prefixId, $extKey, $piVars, $theTable) {
		global $TSFE;

		$this->conf = &$conf;
		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		if ($TSFE->absRefPrefix) {
			if(strpos($TSFE->absRefPrefix, 'http://') === 0 || strpos($TSFE->absRefPrefix, 'https://') === 0) {
				$this->site_url = $TSFE->absRefPrefix;
			} else {
				$this->site_url = $this->site_url . ltrim($TSFE->absRefPrefix, '/');
			}
		}
		$this->prefixId = $prefixId;
		$this->extKey = $extKey;
		$this->piVars = $piVars;
		$this->setTable($theTable);
		$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

		$bSysLanguageUidIsInt = (
			class_exists('t3lib_utility_Math') ?
				t3lib_utility_Math::canBeInterpretedAsInteger($TSFE->config['config']['sys_language_uid']) :
				t3lib_div::testInt($TSFE->config['config']['sys_language_uid'])
		);
		$this->sys_language_content = ($bSysLanguageUidIsInt ? $TSFE->config['config']['sys_language_uid'] : 0);

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

			// Initialise password encryption
		if (
			$theTable == 'fe_users' &&
			$this->conf['useMd5Password']
		) {
			$this->setUseMd5Password(TRUE);
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
			$origArray = $TSFE->sys_page->getRawRecord($theTable, $theUid);               // Get data
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
			$this->writeSpecialData();

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


	public function getSecuredFieldArray () {
		return $this->securedFieldArray;
	}


	public function getDummyPassword () {
		return $this->dummyPassword;
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


	public function readSecuredArray () {
		$rcArray = array();

		foreach ($this->securedFieldArray as $field) {
			switch ($field) {
				case 'password':
				case 'password_again':
					$v = $this->getDummyPassword();
					$rcArray[$field] = $v;
					break;
				default:
					break;
			}
		}
		return $rcArray;
	}


	public function readUnsecuredArray ($bPassword = TRUE) {
		$rcArray = array();
		$seData = $this->readSessionData();

		foreach ($this->securedFieldArray as $field) {
			if (
				!$bPassword &&
				($field == 'password' || $field == 'password_again')
			) {
				continue;
			}
			$v = $seData[$field];
			if (isset($v)) {
				$rcArray[$field] = $v;
			}
		}

		return $rcArray;
	}


	public function readPassword () {
		global $TSFE;

		$securedArray = $this->readUnsecuredArray();

		if (isset($securedArray) && is_array($securedArray)) {
			$rc = $securedArray['password'];
		}

		return $rc;
	}


	public function writePassword ($password) {
		global $TSFE;

		$seData = $this->readSessionData();
		$seData['password'] = $password;
		$this->writeSessionData($seData);
	}


	public function setTokenValid ($valid) {
		$this->bTokenValid = $valid;
	}


	public function isTokenValid () {
		return $this->bTokenValid;
	}


	public function readToken () {
		global $TSFE;

		$seData = $this->readSessionData();
		$rc = $seData['token'];

		return $rc;
	}


	public function writeToken ($token) {
		global $TSFE;

		$seData = $this->readSessionData();
		$seData['token'] = $token;
		$this->writeSessionData($seData, FALSE);
	}


	public function readSessionData ($bReadAll = FALSE)	{
		global $TSFE;

		$extKey = $this->getExtKey();
		$session = $TSFE->fe_user->getKey('ses', 'feuser');

		if ($bReadAll) {
			$rc = $session;
		} else if (isset($session) && is_array($session)) {
			$rc = $session[$extKey];
		}
		return $rc;
	}


	public function writeSessionData (
		$data,
		$bKeepToken = TRUE,
		$bRedirectUrl = TRUE
	) {
		global $TSFE;

		$extKey = $this->getExtKey();
		$session = $this->readSessionData(TRUE);
		if ($bKeepToken && !isset($data['token'])) {
			$data['token'] = $this->readToken();
		}
		if ($bRedirectUrl && !isset($data['redirect_url'])) {
			$redirect_url = $this->readRedirectUrl();
			if ($redirect_url != '') {
				$data['redirect_url'] = $redirect_url;
			}
		}
		$session[$extKey] = $data;
		$TSFE->fe_user->setKey('ses','feuser',$session);
		$TSFE->fe_user->storeSessionData(); // The feuser session data shall not get lost when coming back from external scripts
	}


	// delete all session data except of the token
	public function clearSessionData ($bRedirectUrl = TRUE) {
		$seData = array();

		$this->writeSessionData($seData, TRUE, $bRedirectUrl);
	}


	public function writeSpecialData () {
		$redirect_url = t3lib_div::_GET('redirect_url');

		if ($redirect_url != '') {
			$data = array();
			$data['redirect_url'] = $redirect_url;
			$this->writeSessionData($data);
		}
	}


	public function readRedirectUrl () {
		$data = $this->readSessionData();
		if (isset($data) && is_array($data)) {
			$rc = $data['redirect_url'];
		}
		return $rc;
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


	/**
	* Stores the password and changes it to the default value
	*
	* @return void
	*/
	public function securePassword (&$row) {

// 		if (isset($row['password_again']) && $row['password_again'] == '') {
// 			$row['password'] = '';
// 		}

		$bWriteDummy = FALSE;
		$bMd5 = $this->getUseMd5Password();
		$dummyPassword = $this->dummyPassword;
		if ($bMd5) {
			$dummyPassword = md5($dummyPassword);
		}

		$securedFieldArray = $this->getSecuredFieldArray();
		$newSessionData = array();
		$bNewSessionData = FALSE;

		foreach ($securedFieldArray as $securedField) {

			$value = $row[$securedField];

			if ($value != '' && $value != $dummyPassword) {

				$newSessionData[$securedField] = $value;
				$bWriteDummy = TRUE;
				$bNewSessionData = TRUE;
			}
		}

		if ($bNewSessionData) {
			$this->writeSessionData($newSessionData);
		} else if ($row['password'] != '' || $row['password_again'] != '') {
			$bWriteDummy = TRUE;
		}

		if ($bWriteDummy) {
			if ($row['password'] != '') {
				$row['password'] = $dummyPassword;
			}
			if ($row['password_again'] != '') {
				$row['password_again'] = $dummyPassword;
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
		global $TSFE;

		if ($type) {
			if (isset($this->pid[$type])) {
				$rc = $this->pid[$type];
			}
		}

		if (!$rc) {
			$rc = (t3lib_div::testInt($this->conf['pid']) ? intval($this->conf['pid']) : $TSFE->id);
		}

		return $rc;
	}


	public function setPid ($type, $pid)	{
		global $TSFE;

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
					$pid = $TSFE->id;
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


	public function setUseMd5Password ($useMd5Password) {
		$this->useMd5Password = $useMd5Password;
	}


	public function getUseMd5Password() {
		return $this->useMd5Password;
	}


	public function getJSmd5Added () {
		return ($this->jsMd5Added);
	}


	public function setJSmd5Added ($var) {
		$this->jsMd5Added = TRUE;
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


	/**
	*  Get the stored variables using the hash value to access the database
	*/
	public function getShortUrl ($regHash) {
		global $TYPO3_DB;

			// get the serialised array from the DB based on the passed hash value
		$varArray = array();
		$res =
			$TYPO3_DB->exec_SELECTquery(
				'params',
				'cache_md5params',
				'md5hash=' . $TYPO3_DB->fullQuoteStr(
					$regHash,
					'cache_md5params'
				)
			);

		while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$varArray = unserialize($row['params']);
		}
		$TYPO3_DB->sql_free_result($res);

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
		global $TYPO3_DB;

		if ($regHash != '')	{
			// get the serialised array from the DB based on the passed hash value
			$TYPO3_DB->exec_DELETEquery('cache_md5params', 'md5hash=' . $TYPO3_DB->fullQuoteStr($regHash, 'cache_md5params'));
		}
	}


	/**
	*  Clears obsolete hashes used for short url's
	*/
	public function cleanShortUrlCache () {
		global $TYPO3_DB;

		$shortUrlLife = intval($this->conf['shortUrlLife']) ? strval(intval($this->conf['shortUrlLife'])) : '30';
		$max_life = time() - (86400 * intval($shortUrlLife));
		$res = $TYPO3_DB->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');
	}	// cleanShortUrlCache
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php']);
}
?>