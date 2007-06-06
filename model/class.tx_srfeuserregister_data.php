<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
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
 * Part of the sr_feuser_register (Frontend User Registration) extension.
 *
 * data store functions
 *
 * $Id$
 *
 * @author Kasper Skaarhoj <kasper2007@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author Franz Holzinger <kontakt@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

	// For use with images:
require_once (PATH_t3lib.'class.t3lib_basicfilefunc.php');


class tx_srfeuserregister_data {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $lang;
	var $tca;
	var $auth;
	var $freeCap;

	var $dataArray = array();
	var $currentArr = array();
	var $feUserData = array();
	var $cmdKey;
	var $cmd;
	var $cObj;
	var $failureMsg = array();
	var $saved = false; // is set if data is saved
	var $theTable;
	var $addTableArray = array();
	var $failure = 0; // is set if data did not have the required fields set.
	var $fileFunc = ''; // Set to a basic_filefunc object for file uploads

	var $extKey;
	var $error;
	var $sys_language_content;
	var $prefixId;
	var $adminFieldList;
	var $fieldList; // List of fields from fe_admin_fieldList
	var $recUid;
	var $missing = array(); // array of required missing fields
	var $inError = array(); // array of fields with eval errors other than absence
	var $templateCode;


	function init(&$pibase, &$conf, &$config, &$lang, &$tca, &$auth, &$control, &$freeCap, $theTable, &$adminFieldList)	{
		global $TSFE, $TCA;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->lang = &$lang;
		$this->tca = &$tca;
		$this->auth = &$auth;
		$this->control = &$control;
		$this->freeCap = &$freeCap;
		$this->cObj = &$pibase->cObj;
		$this->extKey = $extKey;
		$this->sys_language_content = $pibase->sys_language_content;
		$this->fileFunc = $pibase->fileFunc;
		$this->prefixId = $pibase->prefixId;

			// Get parameters
		$this->feUserData = t3lib_div::_GP($this->prefixId);
		$fe = t3lib_div::_GP('FE');
		$cmdKey = $this->control->getCmdKey();
		$this->setTable($theTable);
		$this->setAdminFieldList($adminFieldList);

			// Initialise fileFunc object
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

		// <Steve Webster added short url feature>
			// Get hash variable if provided and if short url feature is enabled
		if ($this->conf['useShortUrls']) {
			$this->cleanShortUrlCache();
				// Check and process for short URL if the regHash GET parameter exists
			if ($this->getFeUserData('regHash')) {
				$getVars = $this->getStoredURL($this->getFeUserData('regHash'));
				foreach ($getVars as $k => $v ) {
					t3lib_div::_GETset($v,$k);
				}
				$this->feUserData = t3lib_div::_GP($this->prefixId);
			}
		}
		// </Steve Webster added short url feature>

			// Establishing compatibility with Direct Mail extension
		$piVarArray = array('rU', 'aC', 'cmd', 'sFK');
		foreach ($piVarArray as $k => $pivar)	{
			if (t3lib_div::_GP($pivar))	{
				$this->setFeUserData($pivar, t3lib_div::_GP($pivar));
			}
		}

		$this->setDataArray($fe[$theTable]);
		$module_sys_dmail_category = $this->getDataArray('module_sys_dmail_category');
		if (is_array($module_sys_dmail_category))	{	// no array elements are allowed for $this->cObj->fillInMarkerArray
			$this->setDataArray(implode(',',$module_sys_dmail_category), 'module_sys_dmail_category');
		}

			// Setting cmd and various switches
// 		if ($this->theTable == 'fe_users' && $this->feUserData['cmd'] == 'login' ) {
// 			unset($this->feUserData['cmd']);
// 		}


			// Setting the list of fields allowed for editing and creation.
		$fieldlist = implode(',', t3lib_div::trimExplode(',', $TCA[$theTable]['feInterface']['fe_admin_fieldList'], 1));
		$this->setFieldList($fieldlist);
		$adminFieldList = implode(',', array_intersect( explode(',', $fieldlist), t3lib_div::trimExplode(',', $this->getAdminFieldList(), 1)));

		if (trim($this->conf['addAdminFieldList'])) {
			$adminFieldList .= ',' . trim($this->conf['addAdminFieldList']);
		}
		$this->setAdminFieldList($adminFieldList);

			// Fetching the template file
		$this->setTemplateCode($this->cObj->fileResource($this->conf['templateFile']));
	}

	function &getTemplateCode()	{
		return $this->templateCode;
	}

	function setTemplateCode(&$templateCode)	{
		$this->templateCode = $templateCode;
	}

	function getFieldList()	{
		return $this->fieldList;
	}

	function setFieldList(&$fieldList)	{
		$this->fieldList = $fieldList;
	}

	function getAdminFieldList()	{
		return $this->adminFieldList;
	}

	function setAdminFieldList($adminFieldList)	{
		$this->adminFieldList = $adminFieldList;
	}

	function getFailure()	{
		return $this->failure;
	}

	function setFailure($failure)	{
		$this->failure = $failure;
	}

	function getFeUserData ($k)	{
		$rc = $this->feUserData[$k];
		return $rc;
	}

	function setFeUserData ($k, $value)	{
		$this->feUserData[$k] = $value;
	}

	function getTable ()	{
		return $this->theTable;
	}

	function setTable ($theTable)	{
		$this->theTable = $theTable;
	}

	function setRecUid($uid)	{
		$this->recUid = $uid;
	}

	function getRecUid()	{
		return $this->recUid;
	}

	function getAddTableArray ()	{
		return $this->addTableArray;
	}

	function addTableArray ($table)	{
		if (!in_array($table, $this->addTableArray))	{
			$this->addTableArray[] = $table;
		}
	}

	function setDataArray ($dataArray, $k=0)	{

		if ($k)	{
			$this->currentArr[$k] = $dataArray;
		} else {
			$this->dataArray = $dataArray;
		}
	}

	function getDataArray ($k=0)	{
		if ($k)	{
			$rc = $this->dataArray[$k];
		} else {
			$rc = $this->dataArray;
		}
		return $rc;
	}

	function setCurrentArr ($currentArr, $k=0)	{
		if ($k)	{
			$this->currentArr[$k] = $currentArr;
		} else {
			$this->currentArr = $currentArr;
		}
	}

	function getCurrentArr ($k=0)	{
		if ($k)	{
			$rc = $this->currentArr[$k];
		} else {
			$rc = $this->currentArr;
		}
		return $rc;
	}


	function resetDataArray()	{
		$this->dataArray = array();
	}

	/**
	* Overrides field values as specified by TS setup
	*
	* @return void  all overriding done directly on array $this->dataArray
	*/
	function overrideValues() {
		$cmdKey = $this->control->getCmdKey();
		// Addition of overriding values
		if (is_array($this->conf[$cmdKey.'.']['overrideValues.'])) {
			foreach ($this->conf[$cmdKey.'.']['overrideValues.'] as $theField => $theValue) {
				if ($theField == 'usergroup' && $this->getTable() == 'fe_users' && $this->conf[$cmdKey.'.']['allowUserGroupSelection']) {
					$this->dataArray[$theField] = implode(',', array_merge(array_diff(t3lib_div::trimExplode(',', $this->dataArray[$theField], 1), t3lib_div::trimExplode(',', $theValue, 1)), t3lib_div::trimExplode(',', $theValue, 1)));
				} else {
					$stdWrap = $this->conf[$cmdKey.'.']['overrideValues.'][$theField.'.'];
					if ($stdWrap)	{
						$this->dataArray[$theField] = $this->cObj->stdWrap($theValue, $stdWrap);
					} else {
						$this->dataArray[$theField] = $theValue;
					}
				}
			}
		}
		if ($cmdKey == 'edit') {
		}
	}	// overrideValues


	/**
	* Sets default field values as specified by TS setup
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return void  all initialization done directly on array $this->dataArray
	*/
	function defaultValues(&$markContentArray) {

		$cmdKey = $this->control->getCmdKey();

		// Addition of default values
		if (is_array($this->conf[$cmdKey.'.']['defaultValues.'])) {
			reset($this->conf[$cmdKey.'.']['defaultValues.']);
			foreach($this->conf[$cmdKey.'.']['defaultValues.'] as $theField => $theValue) {
				$this->dataArray[$theField] = $theValue;
			}
		}
		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			foreach($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$markContentArray['###EVAL_ERROR_FIELD_'.$theField.'###'] = '<!--no error-->';
			}
		}

	}	// defaultValues


	/**
	* Applies validation rules specified in TS setup
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return void  on return, $this->failure is the list of fields which were not ok
	*/
	function evalValues(&$markContentArray) {
		$cmdKey = $this->control->getCmdKey();
		$requiredArray = $this->control->getRequiredArray();

		// Check required, set failure if not ok.
		$tempArr = array();
		foreach ($requiredArray as $k => $theField)	{
			if (!trim($this->dataArray[$theField]) && trim($this->dataArray[$theField]) != '0') {
				$tempArr[] = $theField;
				$this->missing[$theField] = true;
			}
		}
		$pid = intval($this->getDataArray('pid'));

		// Evaluate: This evaluates for more advanced things than "required" does. But it returns the same error code, so you must let the required-message tell, if further evaluation has failed!
		$recExist = 0;
		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			$cmd = $this->control->getCmd();
			switch($cmd) {
				case 'edit':
					if ($pid) {
							// This may be tricked if the input has the pid-field set but the edit-field list does NOT allow the pid to be edited. Then the pid may be false.
						$recordTestPid = $pid;
					} else {
						$tempRecArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->getTable(), $this->getDataArray('uid'));
						$recordTestPid = intval($tempRecArr['pid']);
					}
					$recExist = ($recordTestPid != 0);
					break;
				default:
					$recordTestPid = $this->control->thePid ? $this->control->thePid :
					t3lib_div::intval_positive($pid);
					break;
			}

			foreach($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {

				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach ($listOfCommands as $k => $cmd)	{
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'uniqueGlobal':
						$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->getTable(), $theField, $this->dataArray[$theField], '', '', '', '1');
						if (trim($this->dataArray[$theField]) && $DBrows) {
							if (!$recExist || $DBrows[0]['uid'] != $this->dataArray['uid']) {
								// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'The value existed already. Enter a new value.');
							}
						}
						break;
						case 'uniqueLocal':
						$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->getTable(), $theField, $this->dataArray[$theField], 'AND pid IN ('.$recordTestPid.')', '', '', '1');
						if (trim($this->dataArray[$theField]) && $DBrows) {
							if (!$recExist || $DBrows[0]['uid'] != $this->dataArray['uid']) {
								// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'The value existed already. Enter a new value.');
							}
						}
						break;
						case 'twice':
						if (strcmp($this->dataArray[$theField], $this->dataArray[$theField.'_again'])) {
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'You must enter the same value twice.');
						}
						break;
						case 'email':
						if (trim($this->dataArray[$theField]) && !$this->cObj->checkEmail($this->dataArray[$theField])) {
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'You must enter a valid email address.');
						}
						break;
						case 'required':
						if (!trim($this->dataArray[$theField])) {
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'You must enter a value!');
						}
						break;
						case 'atLeast':
						$chars = intval($cmdParts[1]);
						if (strlen($this->dataArray[$theField]) < $chars) {
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = sprintf($this->getFailureMsg($theField, $theCmd, 'You must enter at least %s characters!'), $chars);
						}
						break;
						case 'atMost':
						$chars = intval($cmdParts[1]);
						if (strlen($this->dataArray[$theField]) > $chars) {
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = sprintf($this->getFailureMsg($theField, $theCmd, 'You must enter at most %s characters!'), $chars);
						}
						break;
						case 'inBranch':
						$pars = explode(';', $cmdParts[1]);
						if (intval($pars[0])) {
							$pid_list = $this->cObj->getTreeList(
								intval($pars[0]),
								intval($pars[1]) ? intval($pars[1]) : 999,
								intval($pars[2])
							);
							if (!$pid_list || !t3lib_div::inList($pid_list, $this->dataArray[$theField])) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = sprintf($this->getFailureMsg($theField, $theCmd, 'The value was not a valid value from this list: %s'), $pid_list);
							}
						}
						break;
						case 'unsetEmpty':
						if (!$this->dataArray[$theField]) {
							$hash = array_flip($tempArr);
							unset($hash[$theField]);
							$tempArr = array_keys($hash);
							unset($this->inError[$theField]);
							unset($this->failureMsg[$theField]);
							unset($this->dataArray[$theField]); // This should prevent the field from entering the database.
						}
						break;
						case 'upload':
						if ($this->dataArray[$theField] && is_array($this->tca->TCA['columns'][$theField]['config']) ) {
							if ($this->tca->TCA['columns'][$theField]['config']['type'] == 'group' && $this->tca->TCA['columns'][$theField]['config']['internal_type'] == 'file') {
								$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
								$allowedExtArray = t3lib_div::trimExplode(',', $this->tca->TCA['columns'][$theField]['config']['allowed'], 1);
								$maxSize = $this->tca->TCA['columns'][$theField]['config']['max_size'];
								$fileNameList = explode(',', $this->dataArray[$theField]);
								$newFileNameList = array();
								reset($fileNameList);
								while (list(, $filename) = each($fileNameList)) {
									$fI = pathinfo($filename);
									if (!count($allowedExtArray) || in_array(strtolower($fI['extension']), $allowedExtArray)) {
										if (@is_file(PATH_site.$uploadPath.'/'.$filename)) {
											if (!$maxSize || (filesize(PATH_site.$uploadPath.'/'.$filename) < ($maxSize * 1024))) {
												$newFileNameList[] = $filename;
											} else {
												$this->failureMsg[$theField][] = sprintf($this->getFailureMsg($theField, 'max_size', 'The file is larger than %s KB.'), $maxSize);
												$tempArr[] = $theField;
												$this->inError[$theField] = true;
												if(@is_file(PATH_site.$uploadPath.'/'.$filename)) @unlink(PATH_site.$uploadPath.'/'.$filename);
											}
										}
									} else {
										$this->failureMsg[$theField][] = sprintf($this->getFailureMsg($theField, 'allowed', 'The file extension %s is not allowed.'), $fI['extension']);
										$tempArr[] = $theField;
										$this->inError[$theField] = true;
										if (@is_file(PATH_site.$uploadPath.'/'.$filename)) { @unlink(PATH_site.$uploadPath.'/'.$filename); }
									}
								}
								$this->dataArray[$theField] = implode(',', $newFileNameList);
							}
						}
						break;
						case 'wwwURL':
						if ($this->dataArray[$theField]) {
							$wwwURLOptions = array (
							'AssumeProtocol' => 'http' ,
								'AllowBracks' => TRUE ,
								'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
								'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
								'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
								);
							$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($this->dataArray[$theField], $wwwURLOptions);
							if ($wwwURLResult['Result'] != 'EW_OK' ) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'Please enter a valid Internet site address.');
							}
						}
						break;
						case 'date':
						if ($this->dataArray[$theField] && !$this->evalDate($this->dataArray[$theField]) ){
							$tempArr[] = $theField;
							$this->inError[$theField] = true;
							$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'Please enter a valid date.');
						}
						break;
						// RALPH BRUGGER added captcha  feature>
						case 'captcha':
						if (is_object($this->freeCap))	{
							// Store the sr_freecap word_hash
							// sr_freecap will invalidate the word_hash after calling checkWord
							session_start();
							$sr_freecap_word_hash = $_SESSION[$this->freeCap->extKey.'_word_hash'];
							if (!$this->freeCap->checkWord( $this->dataArray['captcha_response'])) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailureMsg($theField, $theCmd, 'invalid!');
							} else {
								// Restore sr_freecap word_hash
								$_SESSION[$this->freeCap->extKey.'_word_hash'] = $sr_freecap_word_hash;
							}
						}
						break;
					}
				}
				$markContentArray['###EVAL_ERROR_FIELD_'.$theField.'###'] = is_array($this->failureMsg[$theField]) ? implode($this->failureMsg[$theField], '<br />'): '<!--no error-->';
			}
		}

		$this->failure = implode($tempArr, ',');
	}	// evalValues


	/**
	* Transforms fields into certain things...

	*
	* @return void  all parsing done directly on input array $this->dataArray
	*/
	function parseValues() {
		// <Ries van Twisk added support for multiple checkboxes>
		foreach ($this->dataArray AS $key => $value) {
			// If it's an array and the type is check, then we combine the selected items to a binary value
			if ($this->tca->TCA['columns'][$key]['config']['type'] == 'check') {
				if (is_array($this->tca->TCA['columns'][$key]['config']['items'])) {
					if(is_array($value)) {
						$this->dataArray[$key] = 0;
						foreach ($value AS $dec) {  // Combine values to one hexidecimal number
							$this->dataArray[$key] |= (1 << $dec);
						}
					}
				} else {
					$this->dataArray[$key] = $value ? 1 : 0;
				}
			}
		}
		// </Ries van Twisk added support for multiple checkboxes>
		if (is_array($this->conf['parseValues.'])) {
			reset($this->conf['parseValues.']);
			while (list($theField, $theValue) = each($this->conf['parseValues.'])) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				while (list(, $cmd) = each($listOfCommands)) {
					$cmdParts = split('\[|\]', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'int':
						$this->dataArray[$theField] = intval($this->dataArray[$theField]);
						break;
						case 'lower':
						case 'upper':
						$this->dataArray[$theField] = $this->cObj->caseshift($this->dataArray[$theField], $theCmd);
						break;
						case 'nospace':
						$this->dataArray[$theField] = str_replace(' ', '', $this->dataArray[$theField]);
						break;
						case 'alpha':
						$this->dataArray[$theField] = ereg_replace('[^a-zA-Z]', '', $this->dataArray[$theField]);
						break;
						case 'num':
						$this->dataArray[$theField] = ereg_replace('[^0-9]', '', $this->dataArray[$theField]);
						break;
						case 'alphanum':
						$this->dataArray[$theField] = ereg_replace('[^a-zA-Z0-9]', '', $this->dataArray[$theField]);
						break;
						case 'alphanum_x':
						$this->dataArray[$theField] = ereg_replace('[^a-zA-Z0-9_-]', '', $this->dataArray[$theField]);
						break;
						case 'trim':
						$this->dataArray[$theField] = trim($this->dataArray[$theField]);
						break;
						case 'random':
						$this->dataArray[$theField] = substr(md5(uniqid(microtime(), 1)), 0, intval($cmdParts[1]));
						break;
						case 'files':
						if (is_string($this->dataArray[$theField]) && $this->dataArray[$theField]) {
							$this->dataArray[$theField] = explode(',', $this->dataArray[$theField]);
						}
						$this->processFiles($theField);
						break;
						case 'setEmptyIfAbsent':
						if (!isset($this->dataArray[$theField])) {
							$this->dataArray[$theField] = '';
						}
						break;
						case 'multiple':
						if (is_array($this->dataArray[$theField])) {
							$this->dataArray[$theField] = implode(',', $this->dataArray[$theField]);
						}
						break;
						case 'checkArray':
						if (is_array($this->dataArray[$theField])) {
							reset($this->dataArray[$theField]);
							$val = 0;
							while (list($kk, $vv) = each($this->dataArray[$theField])) {
								$kk = t3lib_div::intInRange($kk, 0);
								if ($kk <= 30) {
									if ($vv) {
										$val|= pow(2, $kk);
									}
								}
							}
							$this->dataArray[$theField] = $val;
						}
						break;
						case 'uniqueHashInt':
						$otherFields = t3lib_div::trimExplode(';', $cmdParts[1], 1);
						$hashArray = array();
						while (list(, $fN) = each($otherFields)) {
							$vv = $this->dataArray[$fN];
							$vv = ereg_replace('[[:space:]]', '', $vv);
							$vv = ereg_replace('[^[:alnum:]]', '', $vv);
							$vv = strtolower($vv);
							$hashArray[] = $vv;
						}
						$this->dataArray[$theField] = hexdec(substr(md5(serialize($hashArray)), 0, 8));
						break;
						case 'wwwURL':
						if ($this->dataArray[$theField]) {
							$wwwURLOptions = array (
							'AssumeProtocol' => 'http' ,
								'AllowBracks' => TRUE ,
								'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
								'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
								'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
								);
							$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($this->dataArray[$theField], $wwwURLOptions);
							if ($wwwURLResult['Result'] = 'EW_OK' ) {
								$this->dataArray[$theField] = $wwwURLResult['Value'];
							}
						}
						break;
						case 'date':
						if($this->dataArray[$theField] && $this->evalDate($this->dataArray[$theField]) && strlen($this->dataArray[$theField]) == 8) { 
								$this->dataArray[$theField] = substr($this->dataArray[$theField],0,4).'-'.substr($this->dataArray[$theField],4,2).'-'.substr($this->dataArray[$theField],6,2);
						}
						break;
					}
				}
			}
		}
	}	// parseValues


	/**
	* Processes uploaded files
	*
	* @param string  $theField: the name of the field
	* @return void
	*/
	function processFiles($theField) {
		$theTable = $this->getTable();
		if (is_array($this->tca->TCA['columns'][$theField])) {
			$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
		}
		$fileNameList = array();
		if (is_array($this->dataArray[$theField]) && count($this->dataArray[$theField])) {
			foreach($this->dataArray[$theField] as $i => $file) {
				if (is_array($file)) {
					if ($uploadPath && $file['submit_delete']) {
						if(@is_file(PATH_site.$uploadPath.'/'.$file['name'])) @unlink(PATH_site.$uploadPath.'/'.$file['name']);
					} else {
						$fileNameList[] = $file['name'];
					}
				} else {
					$fileNameList[] = $file;
				}
			}
		}

		if ($uploadPath && is_array($_FILES['FE']['name'][$theTable][$theField]) && $this->evalFileError($_FILES['FE']['error'])) {
			reset($_FILES['FE']['name'][$theTable][$theField]);
			while (list($i, $filename) = each($_FILES['FE']['name'][$theTable][$theField])) {
				if ($filename) {
					$fI = pathinfo($filename);
					if (t3lib_div::verifyFilenameAgainstDenyPattern($fI['name'])) {
						$tmpFilename = (($GLOBALS['TSFE']->loginUser)?($GLOBALS['TSFE']->fe_user->user['username'].'_'):'').basename($filename, '.'.$fI['extension']).'_'.t3lib_div::shortmd5(uniqid($filename)).'.'.$fI['extension'];
						$theDestFile = $this->fileFunc->getUniqueName($this->fileFunc->cleanFileName($tmpFilename), PATH_site.$uploadPath.'/');
						t3lib_div::upload_copy_move($_FILES['FE']['tmp_name'][$theTable][$theField][$i], $theDestFile);
						$fI2 = pathinfo($theDestFile);
						$fileNameList[] = $fI2['basename'];
					}
				}
			}
		}
		$this->dataArray[$theField] = (count($fileNameList))?implode(',', $fileNameList):'';
	}	// processFiles


	/**
	* Saves the data into the database
	*
	* @return void  sets $this->saved
	*/
	function save() {
		global $TYPO3_DB, $TSFE, $TYPO3_CONF_VARS;

		$cmd = $this->control->getCmd();
		$cmdKey = $this->control->getCmdKey();
		$theTable = $this->getTable();
		switch($cmd) {
			case 'edit':
			$theUid = $this->dataArray['uid'];
			$origArr = $TSFE->sys_page->getRawRecord($theTable, $theUid);
				// Fetch the original record to check permissions
			if ($this->conf['edit'] && ($TSFE->loginUser || $this->auth->aCAuth($origArr))) {
					// Must be logged in in order to edit  (OR be validated by email)
				$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf['edit.']['fields'], 1)));
				$newFieldList = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList()))));
					// Do not reset the name if we have no new value
				if (!in_array('name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)) && !in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)) && !in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1))) {
					$newFieldList  = implode(',', array_diff(explode(',', $newFieldList), array('name')));
				}
					// Do not reset the username if we have no new value
				if (!in_array('username', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)) ) {
					$newFieldList  = implode(',', array_diff(explode(',', $newFieldList), array('username')));
				}
				if ($this->auth->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($theTable, $origArr, $TSFE->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
					$outGoingData = $this->parseOutgoingData($this->dataArray);
					$res = $this->cObj->DBgetUpdate($theTable, $theUid, $outGoingData, $newFieldList, true);
					$this->updateMMRelations($this->dataArray);
					$this->saved = true;

						// Post-edit processing: call user functions and hooks
					$rawRecord = $TSFE->sys_page->getRawRecord($theTable, $theUid);
					$this->setCurrentArr ($this->parseIncomingData($rawRecord));
					$this->pibase->userProcess_alt($this->conf['edit.']['userFunc_afterSave'], $this->conf['edit.']['userFunc_afterSave.'], array('rec' => $this->getCurrentArr(), 'origRec' => $origArr));

						// <Ries van Twisk added registrationProcess hooks>
						// Call all afterSaveEdit hooks after the record has been edited and saved
					if (is_array ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
						foreach  ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
							$hookObj= &t3lib_div::getUserObj($classRef);
							if (method_exists($hookObj, 'registrationProcess_afterSaveEdit')) {
								$hookObj->registrationProcess_afterSaveEdit($this->getCurrentArr(), $this);
							}
						}
					}
						// </Ries van Twisk added registrationProcess hooks>
				} else { 
					$this->error = '###TEMPLATE_NO_PERMISSIONS###';
				}
			}
			break;
			default:
			if (is_array($this->conf[$cmdKey.'.'])) {
				$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)));
				$newFieldList  = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList()))));
				$parsedArray = array();
				$parsedArray = $this->parseOutgoingData($this->dataArray);
				if ($cmdKey == 'invite' && $this->control->useMd5Password) {
					$parsedArray['password'] = md5($this->dataArray['password']);
				}
				$res = $this->cObj->DBgetInsert($theTable, $this->control->thePid, $parsedArray, $newFieldList, TRUE);
				$newId = $TYPO3_DB->sql_insert_id();

					// Enable users to own them self.
				if ($this->getTable() == "fe_users" && $this->conf['fe_userOwnSelf']) {
					$extraList = '';
					$dataArray = array();
					if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id']) {
						$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id'];
						$dataArray[$field] = $newId;
						$extraList .= ','.$field;
					}
					if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id']) {
						$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id'];
						list($dataArray[$field]) = explode(',', $this->dataArray['usergroup']);
						$dataArray[$field] = intval($dataArray[$field]);
						$extraList .= ','.$field;
					}
					if (count($dataArray)) {
						$res = $this->cObj->DBgetUpdate($theTable, $newId, $dataArray, $extraList, TRUE);
					}
				}
				$this->dataArray['uid'] = $newId;
				$this->updateMMRelations($this->dataArray);
				$this->saved = true;

					// Post-create processing: call user functions and hooks
				$this->setCurrentArr ($this->parseIncomingData($TSFE->sys_page->getRawRecord($theTable, $newId)));
				$this->pibase->userProcess_alt($this->conf['create.']['userFunc_afterSave'], $this->conf['create.']['userFunc_afterSave.'], array('rec' => $this->getCurrentArr()));

					// <Ries van Twisk added registrationProcess hooks>
					// Call all afterSaveCreate hooks after the record has been created and saved
				if (is_array ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
					foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
						$hookObj= &t3lib_div::getUserObj($classRef);
						if (method_exists($hookObj, 'registrationProcess_afterSaveCreate')) {
							$hookObj->registrationProcess_afterSaveCreate($this->getCurrentArr(), $this);
						}
					}
				}
					// </Ries van Twisk added registrationProcess hooks>
				if ($cmdKey == 'invite' && $this->control->useMd5Password) {
					$this->setCurrentArr($this->getDataArray('password'),'password');
				}
			}
			break;
		}
	}	// save


	/**
	* Processes a record deletion request
	*
	* @return void  sets $this->saved
	*/
	function deleteRecord() {
		global $TYPO3_CONF_VARS;

		$theTable = $this->getTable();
		if ($this->conf['delete']) {
			// If deleting is enabled
			$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $this->getRecUid());
			if ($GLOBALS['TSFE']->loginUser || $this->auth->aCAuth($origArr)) {
				// Must be logged in OR be authenticated by the aC code in order to delete
				// If the recUid selects a record.... (no check here)

				if (is_array($origArr)) {
					if ($this->auth->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
							// Delete the record and display form, if access granted.

							// <Ries van Twisk added registrationProcess hooks>
							// Call all beforeSaveDelete hooks BEFORE the record is deleted
						if (is_array ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
							foreach  ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
								$hookObj= &t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_beforeSaveDelete')) {
									$hookObj->registrationProcess_beforeSaveDelete($origArr, $this);
								}
							}
						}
							// </Ries van Twisk added registrationProcess hooks>

						if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
								// If the record is being fully deleted... then remove the images or files attached.
							$this->deleteFilesFromRecord($this->getRecUid());
						}
						$res = $this->cObj->DBgetDelete($theTable, $this->recUid, true);
						$this->deleteMMRelations($theTable, $this->getRecUid(), $origArr);
						$this->setCurrentArr($origArr);
						$this->saved = true;
					} else {
						$this->error = '###TEMPLATE_NO_PERMISSIONS###';
					}
				}
			}
		}
	}	// deleteRecord


	/**
		* Delete the files associated with a deleted record
		*
		* @param string  $uid: record id
		* @return void
		*/
	function deleteFilesFromRecord($uid) {
		$rec = $GLOBALS['TSFE']->sys_page->getRawRecord($this->getTable(), $uid);
		reset($this->tca->TCA['columns']);
		$updateFields = array();
		while (list($field, $conf) = each($this->tca->TCA['columns'])) {
			if ($conf['config']['type'] == "group" && $conf['config']['internal_type'] == 'file') {
				$updateFields[$field] = '';
				$res = $this->cObj->DBgetUpdate($this->getTable(), $uid, $updateFields, $field, true);
				unset($updateFields[$field]);
				$delFileArr = explode(',', $rec[$field]);
				reset($delFileArr);
				while (list(, $n) = each($delFileArr)) {
					if ($n) {
						$fpath = PATH_site.$conf['config']['uploadfolder'].'/'.$n;
						if(@is_file($fpath)) @unlink($fpath);
					}
				}
			}
		}
	}	// deleteFilesFromRecord


	/** evalDate($value)
		*
		*  Check if the value is a correct date in format yyyy-mm-dd
	*/
	function evalDate($value) {
		if( !$value) {  
			return false; 
		}
		$checkValue = trim($value);
		if( strlen($checkValue) == 8 ) {
			$checkValue = substr($checkValue,0,4).'-'.substr($checkValue,4,2).'-'.substr($checkValue,6,2) ;
		}
		list($year,$month,$day) = split('-', $checkValue, 3);
		if(is_numeric($year) && is_numeric($month) && is_numeric($day)) {
			return checkdate($month, $day, $year);
		} else {
			return false; 
		}
	}	// evalDate


	/**
		* Update MM relations
		*
		* @return void
		*/
	function updateMMRelations($origArr = array()) {
		global $TYPO3_DB;
			// update the MM relation
		$fieldsList = array_keys($origArr);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$valuesList = $origArr[$colName];
				if ($valuesList) {
					$res = $TYPO3_DB->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($origArr['uid']));
					$valuesArray = explode(',', $valuesList);
					reset($valuesArray);
					$insertFields = array();
					$insertFields['uid_local'] = intval($origArr['uid']);
					$insertFields['tablenames'] = '';
					$insertFields['sorting'] = 0;
					while (list(, $theValue) = each($valuesArray)) {
						$insertFields['uid_foreign'] = intval($theValue);
						$insertFields['sorting']++;
						$res = $TYPO3_DB->exec_INSERTquery($colSettings['config']['MM'], $insertFields);
					}
				}
			}
		}
	}	// updateMMRelations


	/**
		* Delete MM relations
		*
		* @return void
		*/
	function deleteMMRelations($table,$uid,$origArr = array()) {
		global $TYPO3_DB;
			// update the MM relation
		$fieldsList = array_keys($origArr);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$res = $TYPO3_DB->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($uid));
			}
		}
	}	// deleteMMRelations


	/**
		* Returns the relevant usergroup overlay record fields
		* Adapted from t3lib_page.php
		*
		* @param	mixed		If $usergroup is an integer, it's the uid of the usergroup overlay record and thus the usergroup overlay record is returned. If $usergroup is an array, it's a usergroup record and based on this usergroup record the language overlay record is found and gespeichert.OVERLAYED before the usergroup record is returned.
		* @param	integer		Language UID if you want to set an alternative value to $this->pibase->sys_language_content which is default. Should be >=0
		* @return	array		usergroup row which is overlayed with language_overlay record (or the overlay record alone)
		*/
	function getUsergroupOverlay($usergroup, $languageUid = -1) {
		global $TYPO3_DB;
		// Initialize:
		if ($languageUid < 0) {
			$languageUid = $this->pibase->sys_language_content;
		}

		// If language UID is different from zero, do overlay:
		if ($languageUid) {
			$fieldArr = array('title');
			if (is_array($usergroup)) {
				$fe_groups_uid = $usergroup['uid'];
				// Was the whole record
				$fieldArr = array_intersect($fieldArr, array_keys($usergroup));
				// Make sure that only fields which exist in the incoming record are overlaid!
			} else {
				$fe_groups_uid = $usergroup;
				// Was the uid
			}

			if (count($fieldArr)) {
				$whereClause = 'fe_group=' . intval($fe_groups_uid) . ' ' .
					'AND sys_language_uid='.intval($languageUid). ' ' .
					$this->cObj->enableFields('fe_groups_language_overlay');
				$res = $TYPO3_DB->exec_SELECTquery(implode(',', $fieldArr), 'fe_groups_language_overlay', $whereClause);
				if ($TYPO3_DB->sql_num_rows($res)) {
					$row = $TYPO3_DB->sql_fetch_assoc($res);
				}
			}
		}

			// Create output:
		if (is_array($usergroup)) {
			return is_array($row) ? array_merge($usergroup, $row) : $usergroup;
			// If the input was an array, simply overlay the newfound array and return...
		} else {
			return is_array($row) ? $row : array(); // always an array in return
		}
	}	// getUsergroupOverlay


	/**
	* Updates the input array from preview
	*
	* @param array  $inputArr: new values
	* @return array  updated array
	*/
	function modifyDataArrForFormUpdate($inputArr) {
		$cmdKey = $this->control->getCmdKey();

		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			foreach($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach($listOfCommands as $k => $cmd) {
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'twice':
						if (isset($inputArr[$theField])) {
							if (!isset($inputArr[$theField.'_again'])) {
								$inputArr[$theField.'_again'] = $inputArr[$theField];
							}
							$this->additionalUpdateFields .= ','.$theField.'_again';
						}
						break;
					}
				}
			}
		}
		if (is_array($this->conf['parseValues.'])) {
			foreach($this->conf['parseValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach($listOfCommands as $k => $cmd) {
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'multiple':
						if (isset($inputArr[$theField]) && !$this->control->isPreview()) {
							$inputArr[$theField] = explode(',', $inputArr[$theField]);
						}
						break;
						case 'checkArray':
						if ($inputArr[$theField] && !$this->control->isPreview()) {
							for($a = 0; $a <= 30; $a++) {
								if ($inputArr[$theField] & pow(2, $a)) {
									$alt_theField = $theField.']['.$a;
									$inputArr[$alt_theField] = 1;
									$this->additionalUpdateFields .= ','.$alt_theField;
								}
							}
						}
						break;
					}
				}
			}
		}
		$inputArr = $this->pibase->userProcess_alt($this->conf['userFunc_updateArray'], $this->conf['userFunc_updateArray.'], $inputArr );
		return $inputArr;
	}	// modifyDataArrForFormUpdate


	/**
	* Moves first and last name into name
	*
	* @return void  done directly on array $this->dataArray
	*/
	function setName() {
		$cmdKey = $this->control->getCmdKey();

		if (in_array('name', explode(',', $this->getFieldList())) && !in_array('name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1))
			&& in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)) && in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1))  ) {
			$this->dataArray['name'] = trim(trim($this->dataArray['first_name']).' '.trim($this->dataArray['last_name']));
		}
	}	// setName


	/**
		* Moves email into username if useEmailAsUsername is set
		*
		* @return void  done directly on array $this->dataArray
		*/
	function setUsername() {
		$cmdKey = $this->control->getCmdKey();

		if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $this->getTable() == "fe_users" && t3lib_div::inList($this->getFieldList(), 'username') && !$this->failureMsg['email']) {
			$this->dataArray['username'] = trim($this->dataArray['email']);
		}
	}	// setUsername


	/**
		* Assigns a value to the password if this is an invitation and password encryption with kb_md5fepw is enabled
		* or if we are creating and generatePassword is set.
		*
		* @return void  done directly on array $this->dataArray
		*/
	function setPassword() {
		$cmdKey = $this->control->getCmdKey();

		if (
			($cmdKey == 'invite' && ($this->control->useMd5Password || $this->conf[$cmdKey.'.']['generatePassword'])) ||

			($cmdKey == 'create' && $this->conf[$cmdKey.'.']['generatePassword'])
		)	{

			if ($this->control->useMd5Password) {
				$length = intval($GLOBALS['TSFE']->config['plugin.']['tx_newloginbox_pi1.']['defaultPasswordLength']);
				if (!$length)	{
					$length = 5;
				}
				$this->dataArray['password'] = tx_kbmd5fepw_funcs::generatePassword( $length );
			} else {
				$this->dataArray['password'] = substr(md5(uniqid(microtime(), 1)), 0, intval($this->conf[$cmdKey.'.']['generatePassword']));
			}
		}
	}	// setPassword


	/**
	* Gets the error message to be displayed
	*
	* @param string  $theField: the name of the field being validated
	* @param string  $theCmd: the name of the validation rule being evaluated
	* @param string  $label: a default error message provided by the invoking function
	* @return string  the error message to be displayed
	*/
	function getFailureMsg($theField, $theCmd, $label) {
		$failureLabel = $this->lang->pi_getLL('evalErrors_'.$theCmd.'_'.$theField);
		$failureLabel = $failureLabel ? $failureLabel : $this->lang->pi_getLL('evalErrors_'.$theCmd);
		$failureLabel = $failureLabel ? $failureLabel : (isset($this->conf['evalErrors.'][$theField.'.'][$theCmd]) ? $this->conf['evalErrors.'][$theField.'.'][$theCmd] : $label);
		return $failureLabel;
	}	// getFailureMsg


	/**
	* Transforms incoming timestamps into dates
	*
	* @return parsedArray
	*/
	function parseIncomingData($origArr = array()) {
		global $TYPO3_DB;
		
		$parsedArr = array();
		$parsedArr = $origArr;
		if (is_array($this->conf['parseFromDBValues.'])) {
			reset($this->conf['parseFromDBValues.']);
			while (list($theField, $theValue) = each($this->conf['parseFromDBValues.'])) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				while (list(, $cmd) = each($listOfCommands)) {
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'date':
						if($origArr[$theField]) {
							$parsedArr[$theField] = date( 'Y-m-d', $origArr[$theField]);
						}
						if (!$parsedArr[$theField]) {
							unset($parsedArr[$theField]);
						}
						break;
						case 'adodb_date':
						if (!is_object($adodbTime))	{
							include_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_adodb_time.php');

							// prepare for handling dates before 1970
							$adodbTime = t3lib_div::makeInstance('tx_srfeuserregister_pi1_adodb_time');
						}

						if($origArr[$theField]) {
							$parsedArr[$theField] = $adodbTime->adodb_date( 'Y-m-d', $origArr[$theField]);
						}
						if (!$parsedArr[$theField]) {
							unset($parsedArr[$theField]);
						}
						break;
					}
				}
			}
		}

		$fieldsList = array_keys($parsedArr);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				if (!$parsedArr[$colName]) {
					$parsedArr[$colName] = '';
				} else {
					$valuesArray = array();
					$res = $TYPO3_DB->exec_SELECTquery(
						'uid_local,uid_foreign,sorting',
						$colSettings['config']['MM'],
						'uid_local='.intval($parsedArr['uid']),
						'',
						'sorting');
					while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
						$valuesArray[] = $row['uid_foreign'];
					}
					$parsedArr[$colName] = implode(',', $valuesArray);
				}
			}
		}

		return $parsedArr;
	}	// parseIncomingData


	/**
		* Transforms outgoing dates into timestamps
		* and modifies the select fields into the count
		* if mm tables are used.
		*
		* @return parsedArray
		*/
	function parseOutgoingData($origArr = array()) {
		
		$parsedArr = array();
		$parsedArr = $origArr;
		if (is_array($this->conf['parseToDBValues.'])) {
			reset($this->conf['parseToDBValues.']);
			while (list($theField, $theValue) = each($this->conf['parseToDBValues.'])) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				while (list(, $cmd) = each($listOfCommands)) {
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'date':
						if($origArr[$theField]) {
							if(strlen($origArr[$theField]) == 8) { 
								$parsedArr[$theField] = substr($origArr[$theField],0,4).'-'.substr($origArr[$theField],4,2).'-'.substr($origArr[$theField],6,2);
							} else {
								$parsedArr[$theField] = $origArr[$theField];
							}
							list($year,$month,$day) = split('-', $parsedArr[$theField], 3);
							$parsedArr[$theField] = mktime(0,0,0,$month,$day,$year);
						}
						break;

						case 'adodb_date':
						if($origArr[$theField]) {
							if(strlen($origArr[$theField]) == 8) { 

								$parsedArr[$theField] = substr($origArr[$theField],0,4).'-'.substr($origArr[$theField],4,2).'-'.substr($origArr[$theField],6,2);
							} else {
								$parsedArr[$theField] = $origArr[$theField];
							}
							list($year,$month,$day) = split('-', $parsedArr[$theField], 3);

							if (!is_object($adodbTime))	{
								include_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_adodb_time.php');
	
								// prepare for handling dates before 1970
								$adodbTime = t3lib_div::makeInstance('tx_srfeuserregister_pi1_adodb_time');
							}

							$parsedArr[$theField] = $adodbTime->adodb_mktime(0,0,0,$month,$day,$year);
						}
						break;
					}
				}
			}
		}

			// update the MM relation count field
		$fieldsList = array_keys($parsedArr);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {	// +++
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				// set the count instead of the comma separated list
				if ($parsedArr[$colName])	{
					$parsedArr[$colName] = count(explode(',', $parsedArr[$colName]));
				} else {
					// $parsedArr[$colName] = 0; +++
				}
			}
		}
		
		return $parsedArr;
	}	// parseOutgoingData


	/**
	* Checks the error value from the upload $_FILES array.
	*
	* @param string  $error_code: the error code
	* @return boolean  true if ok
	*/
	function evalFileError($error_code) {
		if ($error_code == "0") {
			return true;
			// File upload okay
		} elseif ($error_code == '1') {
			return false; // filesize exceeds upload_max_filesize in php.ini
		} elseif ($error_code == '3') {
			return false; // The file was uploaded partially
		} elseif ($error_code == '4') {
			return true;
			// No file was uploaded
		} else {
			return true;
		}
	}	// evalFileError


	/**
		*  Get the stored variables using the hash value to access the database
		*/
	function getStoredURL($regHash) {
		global $TYPO3_DB;
		
			// get the serialised array from the DB based on the passed hash value
		$varArray = array();
		$res = $TYPO3_DB->exec_SELECTquery('params','cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash,'cache_md5params'));
		while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$varArray = unserialize($row['params']);
		}
			// convert the array to one that will be properly incorporated into the GET global array.
		$retArray = array();
		reset($varArray);
		while (list($key, $val) = each($varArray)){
			$search = array('[\]]', '[\[]');
			$replace = array ( '\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			eval("\$retArr".$newkey."='$val';");
		}
		return $retArr;
	}	// getStoredURL


	/**
		*  Clears obsolete hashes used for short url's
		*/
	function cleanShortUrlCache() {
		global $TYPO3_DB;

		$shortUrlLife = intval($this->conf['shortUrlLife']) ? strval(intval($this->conf['shortUrlLife'])) : '30';

		$max_life = time() - (86400 * intval($shortUrlLife));
		$res = $TYPO3_DB->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');	
	}	// cleanShortUrlCache
	// </Steve Webster added short url feature>


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php']);
}
?>
