<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
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
 * @author	Kasper Skaarhoj <kasper2008@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
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
	var $freeCap; // object of type tx_srfreecap_pi2
	var $controlData;
	var $dataArray = array();
	var $origArray = array();
	var $cObj;
	var $failureMsg = array();
	var $saved = FALSE; // is set if data is saved
	var $theTable;
	var $addTableArray = array();
	var $fileFunc = ''; // Set to a basic_filefunc object for file uploads

	var $error;
	var $additionalUpdateFields;
	var $fieldList; // List of fields from fe_admin_fieldList
	var $specialfieldlist; // list of special fields like captcha
	var $recUid = 0;
	var $missing = array(); // array of required missing fields
	var $inError = array(); // array of fields with eval errors other than absence
	var $templateCode;
	var $password = '';


	function init (&$pibase, &$conf, &$config, &$lang, &$tca, &$control, $theTable, &$controlData)	{
		global $TSFE, $TCA;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->lang = &$lang;
		$this->tca = &$tca;
		$this->control = &$control;
		$this->controlData = &$controlData;
		$this->cObj = &$pibase->cObj;
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

		if (t3lib_extMgm::isLoaded('sr_freecap') ) {
			require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = &t3lib_div::getUserObj('&tx_srfreecap_pi2');
			$this->setSpecialFieldList('captcha_response');
		}

			// Get parameters
		$fe = t3lib_div::_GP('FE');

		if (isset($fe) && is_array($fe))	{
			$feDataArray = $fe[$theTable];
			$this->controlData->decodeInput($feDataArray);
			$this->tca->modifyRow($feDataArray, FALSE);
			$this->setDataArray($feDataArray);
		}
	}


	function setError ($error)	{
		$this->error = $error;
	}


	function getError ()	{
		return $this->error;
	}


	function &getTemplateCode ()	{
		return $this->templateCode;
	}


	function setTemplateCode (&$templateCode)	{
		$this->templateCode = $templateCode;
	}


	function getFieldList ()	{
		return $this->fieldList;
	}


	function setFieldList (&$fieldList)	{
		$this->fieldList = $fieldList;
	}


	function setSpecialFieldList ($specialfieldlist)	{
		$this->specialfieldlist = $specialfieldlist;
	}


	function getSpecialFieldList ()	{
		return $this->specialfieldlist;
	}


	function getAdminFieldList ()	{
		return $this->adminFieldList;
	}


	function setAdminFieldList ($adminFieldList)	{
		$this->adminFieldList = $adminFieldList;
	}


	function getAdditionalUpdateFields ()	{
		return $this->additionalUpdateFields;
	}


	function setAdditionalUpdateFields ($additionalUpdateFields)	{
		$this->additionalUpdateFields = $additionalUpdateFields;
	}


	function setRecUid ($uid)	{
		$this->recUid = intval($uid);
	}


	function getRecUid ()	{
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


	function setDataArray ($dataArray, $k='', $bOverrride=TRUE)	{

		if ($k != '')	{
			if ($bOverrride || !isset($this->dataArray[$k]))	{
				$this->dataArray[$k] = $dataArray;
			}
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


	function resetDataArray ()	{
		$this->dataArray = array();
	}


	function setOrigArray ($origArray)	{
		$this->origArr = $origArray;
	}


	function getOrigArray ()	{
		return $this->origArr;
	}


	function setPassword ($password)	{
		$this->password = $password;
	}


	function getPassword (&$dataArray)	{
		$rc = ($this->password != '' ? $this->password : $dataArray);
		return $rc;
	}


	/**
	* Overrides field values as specified by TS setup
	*
	* @return void  all overriding done directly on array $this->dataArray
	*/
	function overrideValues (&$dataArray, $cmdKey) {

		// Addition of overriding values
		if (is_array($this->conf[$cmdKey.'.']['overrideValues.'])) {
			foreach ($this->conf[$cmdKey.'.']['overrideValues.'] as $theField => $theValue) {

				if ($theField == 'usergroup' && $this->controlData->getTable() == 'fe_users' && $this->conf[$cmdKey.'.']['allowUserGroupSelection']) {
					$dataDiff = array_diff($dataArray[$theField], t3lib_div::trimExplode(',', $theValue, 1));
					$dataValue = implode(',', array_merge($dataDiff, t3lib_div::trimExplode(',', $theValue, 1)));
				} else {
					$stdWrap = $this->conf[$cmdKey.'.']['overrideValues.'][$theField.'.'];
					if ($stdWrap)	{
						$dataValue = $this->cObj->stdWrap($theValue, $stdWrap);
					} else if (isset($this->conf[$cmdKey.'.']['overrideValues.'][$theField])) {
						$dataValue = $this->conf[$cmdKey.'.']['overrideValues.'][$theField];
					} else {
						$dataValue = $theValue;
					}
				}
				$dataArray [$theField] = $dataValue;
			}
		}
	}	// overrideValues


	/**
	* fetches default field values as specified by TS setup
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return array the data row with key/value pairs
	*/
	function defaultValues ($cmdKey) {
		$dataArray = array();

		// Addition of default values
		if (is_array($this->conf[$cmdKey.'.']['defaultValues.'])) {
			foreach($this->conf[$cmdKey.'.']['defaultValues.'] as $theField => $theValue) {
				$dataArray[$theField] = $theValue;
			}
		}
		return $dataArray;
	}


	/**
	* Gets the error message to be displayed
	*
	* @param string  $theField: the name of the field being validated
	* @param string  $theRule: the name of the validation rule being evaluated
	* @param string  $label: a default error message provided by the invoking function
	* @param integer $orderNo: ordered number of the rule for the field (>0 if used)
	* @param string  $param: parameter for the error message
	* @param boolean $bInternal: if the bug is caused by an internal problem
	* @return string  the error message to be displayed
	*/
	function getFailureText ($theField, $theRule, $label, $orderNo='', $param='', $bInternal=FALSE) {

 		if ($orderNo != '' && $theRule && isset($this->conf['evalErrors.'][$theField . '.'][$theRule . '.']))	{
			$count = 0;
			foreach ($this->conf['evalErrors.'][$theField . '.'][$theRule . '.'] as $k => $v)	{
				if ($bInternal)	{
					if ($k=='internal')	{
						$failureLabel = $v;
						break;
					}
				} else if (t3lib_div::testInt($k))	{
					$count++;
					if ($count == $orderNo)	{
						$failureLabel = $v;
						break;
					}
				}
			}
		}
		if (!isset($failureLabel))	{
			if ($theRule && isset($this->conf['evalErrors.'][$theField . '.'][$theRule]))	{
				$failureLabel = $this->conf['evalErrors.'][$theField . '.'][$theRule];
			} else {
				$failureLabel='';
				$internalPostfix = ($bInternal ? '_internal' : '');
				if ($theRule)	{
					$labelname = 'evalErrors_' . $theRule . '_' . $theField . $internalPostfix;
					$failureLabel = $this->lang->getLL($labelname);
					$failureLabel = $failureLabel ? $failureLabel : $this->lang->getLL('evalErrors_' . $theRule . $internalPostfix);
				}
				if (!$failureLabel)	{ // this remains only for compatibility reasons
					$labelname = $label;
					$failureLabel = $this->lang->getLL($labelname);
				}
			}
		}
		if ($param != '')	{
			$failureLabel = sprintf($failureLabel,$param);
		}

		return $failureLabel;
	}	// getFailureText


	/**
	* Applies validation rules specified in TS setup
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return void  on return, the ControlData failure will contain the list of fields which were not ok
	*/
	function evalValues ($theTable, &$dataArray, &$origArray, &$markContentArray, $cmdKey, $requiredArray) {
		global $TYPO3_DB, $TSFE;

		$displayFieldArray = t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1);
		if($this->controlData->useCaptcha($cmdKey))	{
			$displayFieldArray[] = 'captcha_response';
		}
		// Check required, set failure if not ok.
		$failureArray = array();

		foreach ($requiredArray as $k => $theField)	{
			if (!trim($dataArray[$theField]) && trim($dataArray[$theField]) != '0') {
				if (isset($dataArray[$theField]))	{
					$failureArray[] = $theField;
					$this->missing[$theField] = TRUE;
				}
			}
		}
		$pid = intval($dataArray['pid']);

		// Evaluate: This evaluates for more advanced things than "required" does. But it returns the same error code, so you must let the required-message tell, if further evaluation has failed!
		$bRecordExists = FALSE;

		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			$cmd = $this->controlData->getCmd();
			if ($cmd == 'edit' || $cmdKey == 'edit')	{
				if ($pid) {
						// This may be tricked if the input has the pid-field set but the edit-field list does NOT allow the pid to be edited. Then the pid may be false.
					$recordTestPid = $pid;
				} else {
					$tempRecArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->controlData->getTable(), $dataArray['uid']);
					$recordTestPid = intval($tempRecArr['pid']);
				}
				$bRecordExists = ($recordTestPid != 0);
			} else {
				$thePid = $this->controlData->getPid();
				$recordTestPid = $thePid ? $thePid :
				t3lib_div::intval_positive($pid);
			}
			$countArray = array();

			foreach($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {

				if (isset($dataArray[$theField]) || !isset($origArray[$theField]))	{
					$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);

					foreach ($listOfCommands as $k => $cmd)	{
						$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'uniqueGlobal':
							case 'uniqueDeletedGlobal':
							case 'uniqueLocal':
							case 'uniqueDeletedLocal':
								$where = $theField . '=' . $TYPO3_DB->fullQuoteStr($dataArray[$theField], $theTable);
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueGlobal')	{
									$where .= $TSFE->sys_page->deleteClause($theTable);
								}
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueDeletedLocal')	{
									$where .= ' AND pid IN ('.$recordTestPid.')';
								}
								$DBrows = $TYPO3_DB->exec_SELECTgetRows('uid,'.$theField,$theTable,$where,'','','1');

								if (trim($dataArray[$theField])!='' && isset($DBrows) && is_array($DBrows) && isset($DBrows[0]) && is_array($DBrows[0])) {
									if (!$bRecordExists || $DBrows[0]['uid'] != $dataArray['uid']) {
										// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->failureMsg[$theField][] = $this->getFailureText($theField, 'uniqueLocal', 'evalErrors_existed_already');
									}
								}
							break;
							case 'twice':
								if (strcmp($dataArray[$theField], $dataArray[$theField.'_again'])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_same_twice');
								}
							break;
							case 'email':
								if (trim($dataArray[$theField]) && !$this->cObj->checkEmail($dataArray[$theField])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_valid_email');
								}
							break;
							case 'required':
								if (!trim($dataArray[$theField])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_required');
								}
							break;
							case 'atLeast':
								$chars = intval($cmdParts[1]);

								if (strlen($dataArray[$theField]) < $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_atleast_characters'), $chars);
								}
							break;
							case 'atMost':
								$chars = intval($cmdParts[1]);

								if (strlen($dataArray[$theField]) > $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_atmost_characters'), $chars);
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
									if (!$pid_list || !t3lib_div::inList($pid_list, $dataArray[$theField])) {
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_list'), $pid_list);
									}
								}
							break;
							case 'unsetEmpty':
								if (!$dataArray[$theField]) {
									$hash = array_flip($failureArray);
									unset($hash[$theField]);
									$failureArray = array_keys($hash);
									unset($this->inError[$theField]);
									unset($this->failureMsg[$theField]);
									unset($dataArray[$theField]); // This should prevent the field from entering the database.
								}
							break;
							case 'upload':
								if ($dataArray[$theField] && is_array($this->tca->TCA['columns'][$theField]['config']) ) {
									if ($this->tca->TCA['columns'][$theField]['config']['type'] == 'group' && $this->tca->TCA['columns'][$theField]['config']['internal_type'] == 'file') {
										$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
										$allowedExtArray = t3lib_div::trimExplode(',', $this->tca->TCA['columns'][$theField]['config']['allowed'], 1);
										$maxSize = $this->tca->TCA['columns'][$theField]['config']['max_size'];
										$fileNameArray = $dataArray[$theField];
										$newFileNameArray = array();
										if ($fileNameArray[0]!='')	{
											foreach($fileNameArray as $filename) {
												$fI = pathinfo($filename);
												$fileExtension = strtolower($fI['extension']);
												$bAllowedFilename = $this->checkFilename($filename);
												if (
													$bAllowedFilename &&
													(!count($allowedExtArray) || in_array($fileExtension, $allowedExtArray))
												) {
													if (@is_file(PATH_site.$uploadPath.'/'.$filename)) {
														if (!$maxSize || (filesize(PATH_site.$uploadPath.'/'.$filename) < ($maxSize * 1024))) {
															$newFileNameArray[] = $filename;
														} else {
															$this->failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'max_size', 'evalErrors_size_too_large'), $maxSize);
															$failureArray[] = $theField;
															$this->inError[$theField] = TRUE;
															if (@is_file(PATH_site.$uploadPath.'/'.$filename))	{
																@unlink(PATH_site.$uploadPath.'/'.$filename);
															}
														}
													}
												} else {
													$this->failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'allowed', 'evalErrors_file_extension'), $fileExtension);
													$failureArray[] = $theField;
													$this->inError[$theField] = TRUE;
													if ($bAllowedFilename && @is_file(PATH_site.$uploadPath.'/'.$filename)) {
														@unlink(PATH_site.$uploadPath.'/'.$filename);
													}
												}
											}
											$dataValue = $newFileNameArray;
											$dataArray[$theField] = $dataValue;
										}
									}
								}
							break;
							case 'wwwURL':
								if ($dataArray[$theField]) {
									$wwwURLOptions = array (
									'AssumeProtocol' => 'http' ,
										'AllowBracks' => TRUE ,
										'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
										'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
										'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
										);
									$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($dataArray[$theField], $wwwURLOptions);
									if ($wwwURLResult['Result'] != 'EW_OK' ) {
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_url');
									}
								}
							break;
							case 'date':
								if ($dataArray[$theField] && !$this->evalDate($dataArray[$theField], $this->conf['dateFormat']) )	{
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_date');
								}
							break;
							case 'freecap':
								if ($this->controlData->useCaptcha($cmdKey) && is_object($this->freeCap) && isset($dataArray['captcha_response']))	{
									// Store the sr_freecap word_hash
									// sr_freecap will invalidate the word_hash after calling checkWord
									$er = session_start();
									$sr_freecap_word_hash = $_SESSION[$this->freeCap->extKey.'_word_hash'];
									if (!$this->freeCap->checkWord($dataArray['captcha_response'])) {
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_captcha');
									} else {
										// Restore sr_freecap word_hash
										$_SESSION[$this->freeCap->extKey . '_word_hash'] = $sr_freecap_word_hash;
									}
								}
							break;
							case 'preg':
								if (trim($dataArray[$theField])) {
									if (isset($countArray[$theCmd]))	{
										$countArray[$theCmd]++;
									} else {
										$countArray[$theCmd] = 1;
									}
									$pattern = str_replace('preg[','',$cmd);
									$pattern = substr($pattern, 0, strlen($pattern) - 1);
									$matches = array();
									$test = preg_match($pattern,$dataArray[$theField],$matches);

									if ($test === FALSE || $test == 0)	{
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_' . $theCmd, $countArray[$theCmd], $cmd, ($test === FALSE));
									}
								}
							case 'hook':
								if (trim($dataArray[$theField])) {
									$extKey = $this->controlData->getExtKey();
									$prefixId = $this->controlData->getPrefixId();
									$hookClassArray = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['model'];
									if (is_array($hookClassArray)) {
										foreach($hookClassArray as $classRef) {
											$hookObj= &t3lib_div::getUserObj($classRef);
											if (method_exists($hookObj, 'evalValues')) {
												$errorField = $hookObj->evalValues($theTable, $dataArray, $origArray, $markContentArray, $cmdKey, $requiredArray, $theField, $cmdParts, $this);
												if ($errorField!='')	{
													$failureArray[] = $errorField;
												}
											}
										}
									}
								}
							break;
						}
					}
				}
				if (in_array($theField, $displayFieldArray))	{
					$markContentArray['###EVAL_ERROR_FIELD_'.$theField.'###'] = is_array($this->failureMsg[$theField]) ? implode($this->failureMsg[$theField], '<br />'): '<!--no error-->';
				} else {
					if (is_array($this->failureMsg[$theField]))	{
						if ($markContentArray['###EVAL_ERROR_saved###'])	{
							$markContentArray['###EVAL_ERROR_saved###'].='<br />';
						}
						$errorMsg = implode($this->failureMsg[$theField], '<br />');
						$markContentArray['###EVAL_ERROR_saved###'] .= $errorMsg;
					}
				}
			} // foreach
		}
		if (empty($markContentArray['###EVAL_ERROR_saved###']))	{
			$markContentArray['###EVAL_ERROR_saved###'] = '';
		}

		if ($this->missing['zone'] && t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey))	{
			$staticInfoObj = &t3lib_div::getUserObj('&tx_staticinfotables_pi1');
				// empty zone if there is not zone for the provided country
			$zoneArray = $staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);

			if (!isset($zoneArray) || is_array($zoneArray) && !count($zoneArray))	{
				unset($this->missing['zone']);
				$k = array_search('zone', $failureArray);
				unset($failureArray[$k]);
			}
		}

		$failure = implode($failureArray, ',');
		$this->controlData->setFailure($failure);
	}	// evalValues


	/**
	* Transforms fields into certain things...
	*
	* @return void  all parsing done directly on input array $dataArray
	*/
	function parseValues ($theTable, &$dataArray, &$origArray) {

		if (is_array($this->conf['parseValues.'])) {

			foreach($this->conf['parseValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				if (in_array('setEmptyIfAbsent', $listOfCommands) && !isset($dataArray[$theField]))	{
					$dataArray[$theField]='';
				}
				$internalType = $this->tca->TCA['columns'][$theField]['config']['internal_type'];

				if (isset($dataArray[$theField]) || isset($origArray[$theField]) || $internalType=='file')	{

					foreach($listOfCommands as $cmd) {
						$cmdParts = split('\[|\]', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						$bValueAssigned = TRUE;
						$dataValue = (isset($dataArray[$theField]) ? $dataArray[$theField] : $origArray[$theField]);
						switch($theCmd) {
							case 'int':
								$dataValue = intval($dataValue);
							break;
							case 'lower':
							case 'upper':
								$dataValue = $this->cObj->caseshift($dataValue, $theCmd);
							break;
							case 'nospace':
								$dataValue = str_replace(' ', '', $dataValue);
							break;
							case 'alpha':
								$dataValue = ereg_replace('[^a-zA-Z]', '', $dataValue);
							break;
							case 'num':
								$dataValue = ereg_replace('[^0-9]', '', $dataValue);
							break;
							case 'alphanum':
								$dataValue = ereg_replace('[^a-zA-Z0-9]', '', $dataValue);
							break;
							case 'alphanum_x':
								$dataValue = ereg_replace('[^a-zA-Z0-9_-]', '', $dataValue);
							break;
							case 'trim':
								$dataValue = trim($dataValue);
							break;
							case 'random':
								$dataValue = substr(md5(uniqid(microtime(), 1)), 0, intval($cmdParts[1]));
							break;
							case 'files':
								if (is_array($dataValue))	{
									$fieldDataArray = $dataValue;
								} else if(is_string($dataValue) && $dataValue) {
									$fieldDataArray = explode(',', $dataValue);
								}
								$dataValue = $this->processFiles($theTable, $theField, $fieldDataArray);
							break;
							case 'multiple':
								if (!is_array($dataValue)) {
									$fieldDataArray = t3lib_div::trimExplode(',', $dataValue);
									$dataValue = $fieldDataArray;
								}
							break;
							case 'checkArray':
								if (is_array($dataValue)) {
									$newDataValue = 0;
									foreach($dataValue as $kk => $vv) {
										$kk = t3lib_div::intInRange($kk, 0);
										if ($kk <= 30) {
											if ($vv) {
												$newDataValue|= pow(2, $kk);
											}
										}
									}
									$dataValue = $newDataValue;
								}
							break;
							case 'uniqueHashInt':
								$otherFields = t3lib_div::trimExplode(';', $cmdParts[1], 1);
								$hashArray = array();
								foreach($otherFields as $fN) {
									$vv = $this->dataArray[$fN];
									$vv = ereg_replace('[[:space:]]', '', $vv);
									$vv = ereg_replace('[^[:alnum:]]', '', $vv);
									$vv = strtolower($vv);
									$hashArray[] = $vv;
								}
								$dataValue = hexdec(substr(md5(serialize($hashArray)), 0, 8));
							break;
							case 'wwwURL':
								if ($dataValue) {
									$wwwURLOptions = array (
									'AssumeProtocol' => 'http' ,
										'AllowBracks' => TRUE ,
										'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
										'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
										'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
										);
									$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($dataValue, $wwwURLOptions);
									if ($wwwURLResult['Result'] = 'EW_OK' ) {
										$dataValue = $wwwURLResult['Value'];
									}
								}
							break;
							case 'date':
								if($dataValue && $this->evalDate($dataValue, $this->conf['dateFormat'])) {
									$dateArray = $this->fetchDate($dataValue, $this->conf['dateFormat']);
									$dataValue = $dateArray['y'] . '-' . $dateArray['m'] . '-'.$dateArray['d'];
									$translateArray = array(
										'd' => ($dateArray['d'] < 10 ? '0'.$dateArray['d'] : $dateArray['d']),
										'j' => $dateArray['d'],
										'm' => ($dateArray['m'] < 10 ? '0'.$dateArray['m'] : $dateArray['m']),
										'n' => $dateArray['m'],
										'y' => $dateArray['y'],
										'Y' => $dateArray['y']
									);
									$searchArray = array_keys($translateArray);
									$replaceArray = array_values($translateArray);
									$dataValue = str_replace($searchArray, $replaceArray, $this->conf['dateFormat']);
								} else if (!isset($dataArray[$theField])) {
									$bValueAssigned = FALSE;
								}
							break;
							default:
								$bValueAssigned = FALSE;
							break;
						}
						if ($bValueAssigned)	{
							$dataArray[$theField] = $dataValue;
						}
					}
				}
			}
		}
	}	// parseValues


	/**
	* Checks for valid filenames
	*
	* @param string  $filename: the name of the file
	* @return void
	*/
	function checkFilename ($filename)	{
		$rc = TRUE;

		$fI = pathinfo($filename);
		$fileExtension = strtolower($fI['extension']);
		if (strpos($fileExtension,'php') !== FALSE || strpos($fileExtension,'htaccess') !== FALSE)	{
			$rc = FALSE; // no php files are allowed here
		}
		if (strpos($filename,'..') !== FALSE)	{
			$rc = FALSE; //  no '..' path is allowed
		}
		return $rc;
	}


	/**
	* Processes uploaded files
	*
	* @param string  $theField: the name of the field
	* @return void
	*/
	function processFiles ($theTable, $theField, &$fieldDataArray) {

		if (is_array($this->tca->TCA['columns'][$theField])) {
			$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
		}
		$fileNameArray = array();
		if (is_array($fieldDataArray) && count($fieldDataArray)) {
			foreach($fieldDataArray as $i => $file) {
				if (is_array($file)) {
					if ($this->checkFilename($file['name']) == FALSE)	{
						continue; // no php files are allowed here
					}

					if ($uploadPath && $file['submit_delete']) {
						if(@is_file(PATH_site.$uploadPath.'/'.$file['name']))	{
							@unlink(PATH_site.$uploadPath.'/'.$file['name']);
						}
					} else {
						$fileNameArray[] = $file['name'];
					}
				} else {
					if ($this->checkFilename($file))	{
						$fileNameArray[] = $file;
					}
				}
			}
		}

		if ($uploadPath && is_array($_FILES['FE']['name'][$theTable][$theField])) {
			foreach($_FILES['FE']['name'][$theTable][$theField] as $i => $filename) {
				if ($filename && $this->checkFilename($filename) && $this->evalFileError($_FILES['FE']['error'][$theTable][$theField][$i])) {
					$fI = pathinfo($filename);
					if (t3lib_div::verifyFilenameAgainstDenyPattern($fI['name'])) {
						$tmpFilename = (($GLOBALS['TSFE']->loginUser)?($GLOBALS['TSFE']->fe_user->user['username'].'_'):'').basename($filename, '.'.$fI['extension']).'_'.t3lib_div::shortmd5(uniqid($filename)).'.'.$fI['extension'];
						$cleanFilename = $this->fileFunc->cleanFileName($tmpFilename);
						$theDestFile = $this->fileFunc->getUniqueName($cleanFilename, PATH_site.$uploadPath.'/');
						t3lib_div::upload_copy_move($_FILES['FE']['tmp_name'][$theTable][$theField][$i], $theDestFile);
						$fI2 = pathinfo($theDestFile);
						$fileNameArray[] = $fI2['basename'];
					}
				}
			}
		}
		$dataValue = $fileNameArray;
		return $dataValue;
	}	// processFiles


	/**
	* Saves the data into the database
	*
	* @return void  sets $this->saved
	*/
	function save ($theTable, $dataArray, $origArray, &$newDataArray, $cmd, $cmdKey, &$hookClassArray) {
		global $TYPO3_DB, $TSFE;
		$rc = 0;

		switch($cmdKey) {
			case 'edit':
				$theUid = $dataArray['uid'];
				$rc = $theUid;
				$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

					// Fetch the original record to check permissions
				if ($this->conf['edit'] && ($TSFE->loginUser || $authObj->aCAuth($origArray))) {
						// Must be logged in in order to edit  (OR be validated by email)
					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)));
					$newFieldArray = array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList())));
					$fieldArray = t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1);

						// Do not reset the name if we have no new value
					if (!in_array('name', $fieldArray) && !in_array('first_name', $fieldArray) && !in_array('last_name', $fieldArray)) {
						$newFieldArray = array_diff($newFieldArray, array('name'));
					}
						// Do not reset the username if we have no new value
					if (!in_array('username', $fieldArray) && $dataArray['username'] == '') {
						$newFieldArray = array_diff($newFieldArray, array('username'));
					}

					$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
					if ($authObj->aCAuth($origArray) || $this->cObj->DBmayFEUserEdit($theTable, $origArray, $TSFE->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {

						$outGoingData = $this->parseOutgoingData($dataArray,$origArray);
						$newFieldList = implode (',', $newFieldArray);

						$res = $this->cObj->DBgetUpdate($theTable, $theUid, $outGoingData, $newFieldList, TRUE);
						$this->updateMMRelations($dataArray);
						$this->saved = TRUE;
						$newRow = array_merge($origArray, $this->parseIncomingData($outGoingData));
						$dataArray = $newRow;
						$this->control->userProcess_alt($this->conf['edit.']['userFunc_afterSave'], $this->conf['edit.']['userFunc_afterSave.'], array('rec' => $dataArray, 'origRec' => $origArray));

						// Post-edit processing: call user functions and hooks
						// Call all afterSaveEdit hooks after the record has been edited and saved
						if (is_array($hookClassArray)) {
							foreach($hookClassArray as $classRef) {
								$hookObj= &t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveEdit')) {
									$hookObj->registrationProcess_afterSaveEdit($dataArray, $this);
								}
							}
						}
					} else {
						$this->setError('###TEMPLATE_NO_PERMISSIONS###');
					}
				}
			break;
			default:
				if (is_array($this->conf[$cmdKey.'.'])) {
					$password = $dataArray['password'];
					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)));
					$newFieldList  = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList()))));
					$parsedArray = array();
					$parsedArray = $this->parseOutgoingData($dataArray, $origArray);

					if (($cmdKey == 'invite' || $cmdKey == 'create' && $this->conf[$cmdKey . '.']['generatePassword']) && $this->controlData->getUseMd5Password()) {
						$parsedArray['password'] = md5($password);
					}
					$res = $this->cObj->DBgetInsert($theTable, $this->controlData->getPid(), $parsedArray, $newFieldList, TRUE);
					$newId = $TYPO3_DB->sql_insert_id();
					$rc = $newId;

						// Enable users to own themselves.
					if ($theTable == 'fe_users' && $this->conf['fe_userOwnSelf']) {
						$extraList = '';
						$tmpDataArray = array();
						if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id']) {
							$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id'];
							$dataArray[$field] = $newId;
							$extraList .= ','.$field;
						}
						if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id']) {
							$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id'];
							if (is_array($dataArray['usergroup']))	{
								list($tmpDataArray[$field]) = $dataArray['usergroup'];
							} else {
								$tmpArray = explode(',', $dataArray['usergroup']);
								list($tmpDataArray[$field]) = $tmpArray;
							}
							$tmpDataArray[$field] = intval($tmpDataArray[$field]);
							$extraList .= ','.$field;
						}
						if (count($tmpDataArray)) {
							$res = $this->cObj->DBgetUpdate($theTable, $newId, $tmpDataArray, $extraList, TRUE);
						}
					}
					$dataArray['uid'] = $newId;
					$this->updateMMRelations($dataArray);
					$this->saved = TRUE;

						// Post-create processing: call user functions and hooks
					$newDataArray = $this->parseIncomingData($TSFE->sys_page->getRawRecord($theTable, $newId));
					$this->tca->modifyRow($newDataArray, TRUE);
					$this->control->userProcess_alt($this->conf['create.']['userFunc_afterSave'], $this->conf['create.']['userFunc_afterSave.'], array('rec' => $newDataArray));

					// Call all afterSaveCreate hooks after the record has been created and saved
					if (is_array ($hookClassArray)) {
						foreach  ($hookClassArray as $classRef) {
							$hookObj= &t3lib_div::getUserObj($classRef);
							if (method_exists($hookObj, 'registrationProcess_afterSaveCreate')) {
								$hookObj->registrationProcess_afterSaveCreate($newDataArray, $this);
							}
						}
					}

					$newDataArray['password'] = $password; // restore password before MD5 encryption
				}
			break;
		}
		return $rc;
	}	// save


	/**
	* Processes a record deletion request
	*
	* @return void  sets $this->saved
	*/
	function deleteRecord ($theTable, &$origArray, &$dataArray) {
		$prefixId = $this->controlData->getPrefixId();

		if ($this->conf['delete']) {
			// If deleting is enabled

			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			if ($GLOBALS['TSFE']->loginUser || $authObj->aCAuth($origArray)) {
				// Must be logged in OR be authenticated by the aC code in order to delete
				// If the recUid selects a record.... (no check here)

				if (is_array($origArray)) {
					if ($authObj->aCAuth($origArray) || $this->cObj->DBmayFEUserEdit($theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
							// Delete the record and display form, if access granted.

						$extKey = $this->controlData->getExtKey();

							// <Ries van Twisk added registrationProcess hooks>
							// Call all beforeSaveDelete hooks BEFORE the record is deleted
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'])) {
							foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'] as $classRef) {
								$hookObj= &t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_beforeSaveDelete')) {
									$hookObj->registrationProcess_beforeSaveDelete($origArray, $this);
								}
							}
						}
							// </Ries van Twisk added registrationProcess hooks>

						if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
								// If the record is being fully deleted... then remove the images or files attached.
							$this->deleteFilesFromRecord($this->getRecUid());
						}
						$res = $this->cObj->DBgetDelete($theTable, $this->getRecUid(), TRUE);
						$this->deleteMMRelations($theTable, $this->getRecUid(), $origArray);
						$dataArray = $origArray;
						$this->saved = TRUE;
					} else {
						$this->setError('###TEMPLATE_NO_PERMISSIONS###');
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
	function deleteFilesFromRecord ($uid) {
		$rec = $GLOBALS['TSFE']->sys_page->getRawRecord($this->controlData->getTable(), $uid);
		$updateFields = array();
		foreach($this->tca->TCA['columns'] as $field => $conf) {
			if ($conf['config']['type'] == 'group' && $conf['config']['internal_type'] == 'file') {
				$updateFields[$field] = '';
				$res = $this->cObj->DBgetUpdate($this->controlData->getTable(), $uid, $updateFields, $field, TRUE);
				unset($updateFields[$field]);
				$delFileArr = explode(',', $rec[$field]);
				foreach($delFileArr as $n) {
					if ($n) {
						$fpath = PATH_site.$conf['config']['uploadfolder'].'/'.$n;
						if(@is_file($fpath))	{
							@unlink($fpath);
						}
					}
				}
			}
		}
	}	// deleteFilesFromRecord


	/** fetchDate($value)
		*
		*  Check if the value is a correct date in format yyyy-mm-dd
	*/
	function fetchDate ($value, $dateFormat) {

		$rcArray = array('m' => '', 'd' => '', 'y' => '');
		$dateValue = trim($value);
		$split = $this->conf['dateSplit'];
		if (!$split)	{
			$split = '-';
		}
		$dateFormatArray = split($split, $dateFormat);
		$dateValueArray = split($split, $dateValue);
		$max = sizeof($dateFormatArray);
		$yearOffset = 0;
		for ($i=0; $i < $max; $i++) {

			switch($dateFormatArray[$i]) {
				// day
				// d - day of the month, 2 digits with leading zeros; i.e. "01" to "31"
				// j - day of the month without leading zeros; i.e. "1" to "31"
				case 'd':
				case 'j':
					$rcArray['d'] = intval($dateValueArray[$i]);
				break;
				// month
				// m - month; i.e. "01" to "12"
				// n - month without leading zeros; i.e. "1" to "12"
				case 'm':
				case 'n':
					$rcArray['m'] = intval($dateValueArray[$i]);
				break;
				// M - month, textual, 3 letters; e.g. "Jan"
				// F - month, textual, long; e.g. "January"
				// case 'M','F': ...to be written ;break;
				// year

				// Y - year, 4 digits; e.g. "1999"
				case 'Y':
					$rcArray['y'] = intval($dateValueArray[$i]);
				break;
				// y - year, 2 digits; e.g. "99"
				case 'y':
					$yearVal = intval($dateValueArray[$i]);
					if($yearVal <= 11) {
						$rcArray['y'] = '20' . $yearVal;
					} else {
						$rcArray['y'] = '19' . $yearVal;
					}
				break;
			}
		}
		return $rcArray;
	}


	/** evalDate($value)
	 *
	 *  Check if the value is a correct date in format yyyy-mm-dd
	 */
	function evalDate ($value, $dateFormat) {
		if( !$value) {
			return FALSE;
		}
		$dateArray = $this->fetchDate($value, $dateFormat);

		if(is_numeric($dateArray['y']) && is_numeric($dateArray['m']) && is_numeric($dateArray['d'])) {
			$rc = checkdate($dateArray['m'], $dateArray['d'], $dateArray['y']);
		} else {
			$rc = FALSE;
		}
		return $rc;
	}	// evalDate


	/**
		* Update MM relations
		*
		* @return void
		*/
	function updateMMRelations ($row) {
		global $TYPO3_DB;

			// update the MM relation
		$fieldsList = array_keys($row);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {

			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$valuesArray = $row[$colName];
				if (isset($valuesArray) && is_array($valuesArray)) {
					$res = $TYPO3_DB->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($row['uid']));
					$insertFields = array();
					$insertFields['uid_local'] = intval($row['uid']);
					$insertFields['tablenames'] = '';
					$insertFields['sorting'] = 0;
					foreach($valuesArray as $theValue) {
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
	function deleteMMRelations ($table,$uid,$row = array()) {
		global $TYPO3_DB;
			// update the MM relation
		$fieldsList = array_keys($row);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$res = $TYPO3_DB->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($uid));
			}
		}
	}	// deleteMMRelations


	/**
	* Updates the input array from preview
	*
	* @param array  $inputArr: new values
	* @return array  updated array
	*/
	function modifyDataArrForFormUpdate ($inputArr, $cmdKey) {

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
							$this->setAdditionalUpdateFields($this->getAdditionalUpdateFields() . ',' . $theField . '_again');
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
						if (isset($inputArr[$theField])) {
							unset($inputArr[$theField]);
						}
						break;
						case 'checkArray':
						if ($inputArr[$theField] && !$this->controlData->isPreview()) {
							for($a = 0; $a <= 50; $a++) {
								if ($inputArr[$theField] & pow(2, $a)) {
									$alt_theField = $theField.']['.$a;
									$inputArr[$alt_theField] = 1;
									$this->setAdditionalUpdateFields($this->getAdditionalUpdateFields() . ','.$alt_theField);
								}
							}
						}
						break;
					}
				}
			}
		}
		$inputArr = $this->control->userProcess_alt($this->conf['userFunc_updateArray'], $this->conf['userFunc_updateArray.'], $inputArr);

		foreach($inputArr as $theField => $value)	{
			if (is_array($value))	{
				$value = implode (',', $value);
			}
			$inputArr[$theField] = htmlspecialchars_decode($value);
		}
		return $inputArr;
	}	// modifyDataArrForFormUpdate


	/**
	* Moves first and last name into name
	*
	* @return void  done directly on array $this->dataArray
	*/
	function setName (&$dataArray, $cmdKey) {

		if (in_array('name', explode(',', $this->getFieldList())) && !in_array('name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1))
			&& in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)) && in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1))  ) {
			$dataArray['name'] = trim(trim($dataArray['first_name']) . ' ' . trim($dataArray['last_name']));
		}
	}	// setName


	/**
		* Moves email into username if useEmailAsUsername is set
		*
		* @return void  done directly on array $this->dataArray
		*/
	function setUsername ($theTable, &$dataArray, $cmdKey) {

		if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $theTable == "fe_users" && t3lib_div::inList($this->getFieldList(), 'username') && !$this->failureMsg['email']) {
			$dataArray['username'] = trim($dataArray['email']);
		}
	}	// setUsername


	/**
		* Assigns a value to the password if this is an invitation and md5 password encryption if kb_md5fepw is enabled
		* or if we are creating and generatePassword is set.
		*
		* @return void  done directly on array $this->dataArray
		*/
	function generatePassword (&$dataArray, $cmdKey) {

		if (
			($cmdKey == 'invite' && ($this->controlData->getUseMd5Password() || $this->conf[$cmdKey.'.']['generatePassword'])) ||

			($cmdKey == 'create' && $this->conf[$cmdKey.'.']['generatePassword'])
		)	{
			$genLength = intval($this->conf[$cmdKey.'.']['generatePassword']);
			$genPassword = substr(md5(uniqid(microtime(), 1)), 0, $genLength);

			if ($this->controlData->getUseMd5Password()) {
				if (t3lib_extMgm::isLoaded('kb_md5fepw'))	{
					$length = intval($GLOBALS['TSFE']->config['plugin.']['tx_newloginbox_pi1.']['defaultPasswordLength']);
					if (!$length)	{
						$length = ($genLength ? $genLength : 32);
					}

					include_once(t3lib_extMgm::extPath('kb_md5fepw').'class.tx_kbmd5fepw_funcs.php');
					$dataArray['password'] = tx_kbmd5fepw_funcs::generatePassword($length );
				} else {
					$dataArray['password'] = $genPassword;
				}
			} else {
				$dataArray['password'] = $genPassword;
			}
			$this->setPassword ($dataArray['password']);
		}
	}	// setPassword


	/**
	* Transforms incoming timestamps into dates
	*
	* @return parsedArray
	*/
	function parseIncomingData ($origArray, $bUnsetZero=TRUE) {
		global $TYPO3_DB;

		$parsedArray = array();
		$parsedArray = $origArray;
		if (is_array($this->conf['parseFromDBValues.'])) {
			foreach($this->conf['parseFromDBValues.'] as $theField => $theValue)	{
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				if (is_array($listOfCommands))	{
					foreach($listOfCommands as $k2 => $cmd) {
						$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'date':
								if($origArray[$theField]) {
									$parsedArray[$theField] = date($this->conf['dateFormat'], $origArray[$theField]);
								}
								if (!$parsedArray[$theField]) {
									if ($bUnsetZero)	{
										unset($parsedArray[$theField]);
									} else {
										$parsedArray[$theField] = '';
									}
								}
							break;
							case 'adodb_date':
								if (!is_object($adodbTime))	{
									include_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_adodb_time.php');

									// prepare for handling dates before 1970
									$adodbTime = &t3lib_div::getUserObj('&tx_srfeuserregister_pi1_adodb_time');
								}

								if($origArray[$theField]) {
									$parsedArray[$theField] = $adodbTime->adodb_date(
										$this->conf['dateFormat'],
										$origArray[$theField]
									);
								}
								if (!$parsedArray[$theField]) {
									if ($bUnsetZero)	{
										unset($parsedArray[$theField]);
									} else {
										$parsedArray[$theField] = '';
									}
								}
							break;
						}
					}
				}
			}
		}
		return $parsedArray;
	}	// parseIncomingData


	/**
	 * Transforms outgoing dates into timestamps
	 * and modifies the select fields into the count
	 * if mm tables are used.
	 *
	 * @return parsedArray
	 */
	function parseOutgoingData (&$dataArray, $origArray) {
		$tablesObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');
		$parsedArray = $dataArray;

		if (is_array($this->conf['parseToDBValues.'])) {

			foreach($this->conf['parseToDBValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach($listOfCommands as $k2 => $cmd) {
					$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					if (($theCmd == 'date' || $theCmd == 'adodb_date') && $dataArray[$theField])	{
						if(strlen($dataArray[$theField]) == 8) {
							$parsedArray[$theField] = substr($dataArray[$theField],0,4).'-'.substr($dataArray[$theField],4,2).'-'.substr($dataArray[$theField],6,2);
						} else {
							$parsedArray[$theField] = $dataArray[$theField];
						}
						$dateArray = $this->fetchDate($parsedArray[$theField], $this->conf['dateFormat']);
					}

					switch($theCmd) {
						case 'date':
							if($dataArray[$theField]) {
								$parsedArray[$theField] = mktime(0,0,0,$dateArray['m'],$dateArray['d'],$dateArray['y']);
							}
						break;
						case 'adodb_date':
							if($dataArray[$theField]) {
								if (!is_object($adodbTime))	{
									include_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_adodb_time.php');

									// prepare for handling dates before 1970
									$adodbTime = &t3lib_div::getUserObj('&tx_srfeuserregister_pi1_adodb_time');
								}
								$parsedArray[$theField] = $adodbTime->adodb_mktime(0,0,0,$dateArray['m'],$dateArray['d'],$dateArray['y']);
							}
						break;
					}
				}
			}
		}

			// update the MM relation count field
		$fieldsList = array_keys($parsedArray);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (isset($parsedArray[$colName]))	{

				$fieldObj = &$addressObj->getFieldObj($colName);
				if (isset($fieldObj) && is_object($fieldObj))	{
					$fieldObj->parseOutgoingData($colName, $dataArray, $origArray, $parsedArray);
				}
				if (is_array ($parsedArray[$colName]))	{
					if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
						// set the count instead of the comma separated list
						if ($parsedArray[$colName])	{
							$parsedArray[$colName] = count(explode(',', $parsedArray[$colName]));
						} else {
							$parsedArray[$colName] = '';
						}
					} else {
						$parsedArray[$colName] = implode (',', $parsedArray[$colName]);
					}
				}
			}
		}
		return $parsedArray;
	}	// parseOutgoingData


	/**
	* Checks the error value from the upload $_FILES array.
	*
	* @param string  $error_code: the error code
	* @return boolean  TRUE if ok
	*/
	function evalFileError ($error_code) {
		$rc = FALSE;
		if ($error_code == "0") {
			$rc = TRUE;
			// File upload okay
		} elseif ($error_code == '1') {
			$rc = FALSE; // filesize exceeds upload_max_filesize in php.ini
		} elseif ($error_code == '3') {
			return FALSE; // The file was uploaded partially
		} elseif ($error_code == '4') {
			$rc = TRUE;
			// No file was uploaded
		} else {
			$rc = TRUE;
		}

		return $rc;
	}	// evalFileError
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php']);
}
?>
