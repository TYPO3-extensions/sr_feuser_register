<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * data store functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2008@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */
class tx_srfeuserregister_data {
	public $pibase;
	public $conf = array();
	public $config = array();
	public $lang;
	public $tca;
	public $freeCap; // object of type tx_srfreecap_pi2
	public $controlData;
	public $dataArray = array();
	public $origArray = array();
	public $cObj;
	protected $evalErrors = array();
	public $saved = FALSE; // is set if data is saved
	public $theTable;
	public $addTableArray = array();
	public $fileFunc = ''; // Set to a basic_filefunc object for file uploads

	public $error;
	public $additionalUpdateFields;
	public $fieldList; // List of fields from fe_admin_fieldList
	public $specialfieldlist; // list of special fields like captcha
	public $recUid = 0;
	public $missing = array(); // array of required missing fields
	public $inError = array(); // array of fields with eval errors other than absence
	public $templateCode;


	public function init (
		&$pibase,
		&$conf,
		&$config,
		&$lang,
		&$tca,
		&$control,
		$theTable,
		&$controlData
	) {
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->lang = &$lang;
		$this->tca = &$tca;
		$this->control = &$control;
		$this->controlData = &$controlData;
		$this->cObj = &$pibase->cObj;
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

		$captchaExtensions = $this->controlData->getCaptchaExtensions();
		if (!empty($captchaExtensions)) {
			$this->setSpecialFieldList('captcha_response');
		}

			// Get POST parameters
		$fe = t3lib_div::_GP('FE');

		if (isset($fe) && is_array($fe) && $this->controlData->isTokenValid()) {
			$feDataArray = $fe[$theTable];
			$bHtmlSpecialChars = FALSE;
			$this->controlData->secureInput($feDataArray, $bHtmlSpecialChars);
			$this->tca->modifyRow($feDataArray, FALSE);
			if ($theTable === 'fe_users') {
				$this->controlData->securePassword($feDataArray);
			}
			$this->setDataArray($feDataArray);
		}


			// Fetching the template file
		$this->setTemplateCode($this->cObj->fileResource($this->conf['templateFile']));
	}


	public function setError ($error) {
		$this->error = $error;
	}


	public function getError () {
		return $this->error;
	}


	public function setSaved ($value) {
		$this->saved = $value;
	}


	public function getSaved () {
		return $this->saved;
	}


	public function getTemplateCode () {
		return $this->templateCode;
	}
	/*
	 * Sets the source code of the HTML template
	 *
	 * @param string $templateCode: the source code
	 * @return void
	 */
	public function setTemplateCode ($templateCode) {
		$this->templateCode = $templateCode;
	}


	public function getFieldList () {
		return $this->fieldList;
	}


	public function setFieldList (&$fieldList) {
		$this->fieldList = $fieldList;
	}


	public function setSpecialFieldList ($specialfieldlist) {
		$this->specialfieldlist = $specialfieldlist;
	}


	public function getSpecialFieldList () {
		return $this->specialfieldlist;
	}


	public function getAdminFieldList () {
		return $this->adminFieldList;
	}


	public function setAdminFieldList ($adminFieldList) {
		$this->adminFieldList = $adminFieldList;
	}


	public function getAdditionalUpdateFields () {
		return $this->additionalUpdateFields;
	}


	public function setAdditionalUpdateFields ($additionalUpdateFields) {
		$this->additionalUpdateFields = $additionalUpdateFields;
	}


	public function setRecUid ($uid) {
		$this->recUid = intval($uid);
	}


	public function getRecUid () {
		return $this->recUid;
	}


	public function getAddTableArray () {
		return $this->addTableArray;
	}


	public function addTableArray ($table) {
		if (!in_array($table, $this->addTableArray)) {
			$this->addTableArray[] = $table;
		}
	}


	public function setDataArray (array $dataArray, $k = '', $bOverrride = TRUE) {
		if ($k != '') {
			if ($bOverrride || !isset($this->dataArray[$k])) {
				$this->dataArray[$k] = $dataArray;
			}
		} else {
			$this->dataArray = $dataArray;
		}
	}


	public function getDataArray ($k=0) {
		if ($k)	{
			$rc = $this->dataArray[$k];
		} else {
			$rc = $this->dataArray;
		}
		return $rc;
	}


	public function resetDataArray () {
		$this->dataArray = array();
	}


	public function setOrigArray (array $origArray) {
		$this->origArr = $origArray;
	}


	public function getOrigArray () {
		return $this->origArr;
	}

	public function bNewAvailable () {
		$dataArray = $this->getDataArray();
		$rc = ($dataArray['username'] != '' || $dataArray['email'] != '');
		return $rc;
	}

	/**
	* Overrides field values as specified by TS setup
	*
	* @return void  all overriding done directly on array $this->dataArray
	*/
	public function overrideValues (array &$dataArray, $cmdKey) {

		// Addition of overriding values
		if (is_array($this->conf[$cmdKey . '.']['overrideValues.'])) {
			foreach ($this->conf[$cmdKey . '.']['overrideValues.'] as $theField => $theValue) {
				if ($theField == 'usergroup' && $this->controlData->getTable() == 'fe_users' && $this->conf[$cmdKey.'.']['allowUserGroupSelection']) {
					$overrideArray = t3lib_div::trimExplode(',', $theValue, 1);
					if (is_array($dataArray[$theField])) {
						$dataValue = array_merge($dataArray[$theField], $overrideArray);
					} else {
						$dataValue = $overrideArray;
					}
					$dataValue = array_unique($dataValue);
				} else {
					$stdWrap = $this->conf[$cmdKey . '.']['overrideValues.'][$theField.'.'];
					if ($stdWrap) {
						$dataValue = $this->cObj->stdWrap($theValue, $stdWrap);
					} else if (isset($this->conf[$cmdKey . '.']['overrideValues.'][$theField])) {
						$dataValue = $this->conf[$cmdKey . '.']['overrideValues.'][$theField];
					} else {
						$dataValue = $theValue;
					}
				}
				$dataArray[$theField] = $dataValue;
			}
		}
	}	// overrideValues


	/**
	* fetches default field values as specified by TS setup
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return array the data row with key/value pairs
	*/
	public function defaultValues ($cmdKey) {
		$dataArray = array();

		// Addition of default values
		if (is_array($this->conf[$cmdKey . '.']['defaultValues.'])) {
			foreach($this->conf[$cmdKey . '.']['defaultValues.'] as $theField => $theValue) {
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
	public function getFailureText (
		$theField,
		$theRule,
		$label,
		$orderNo = '',
		$param = '',
		$bInternal = FALSE
	) {
 		if (
			$orderNo != '' &&
			$theRule &&
			isset($this->conf['evalErrors.'][$theField . '.'][$theRule . '.'])
		) {
			$count = 0;

			foreach ($this->conf['evalErrors.'][$theField . '.'][$theRule . '.'] as $k => $v) {
				$bKIsInt = (
					class_exists('t3lib_utility_Math') ?
						t3lib_utility_Math::canBeInterpretedAsInteger($k) :
						t3lib_div::testInt($k)
				);

				if ($bInternal) {
					if ($k == 'internal') {
						$failureLabel = $v;
						break;
					}
				} else if ($bKIsInt) {
					$count++;

					if ($count == $orderNo) {
						$failureLabel = $v;
						break;
					}
				}
			}
		}

		if (!isset($failureLabel)) {
			if (
				$theRule &&
				isset($this->conf['evalErrors.'][$theField . '.'][$theRule])
			) {
				$failureLabel = $this->conf['evalErrors.'][$theField . '.'][$theRule];
			} else {
				$failureLabel='';
				$internalPostfix = ($bInternal ? '_internal' : '');
				if ($theRule) {
					$labelname = 'evalErrors_' . $theRule . '_' . $theField . $internalPostfix;
					$failureLabel = $this->lang->getLL($labelname);
					$failureLabel = $failureLabel ? $failureLabel : $this->lang->getLL('evalErrors_' . $theRule . $internalPostfix);
				}
				if (!$failureLabel) { // this remains only for compatibility reasons
					$labelname = $label;
					$failureLabel = $this->lang->getLL($labelname);
				}
			}
		}

		if ($param != '') {
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
	public function evalValues (
		$theTable,
		array &$dataArray,
		array &$origArray,
		array &$markContentArray,
		$cmdKey,
		array $requiredArray
	) {
		$failureMsg = array();
		$displayFieldArray = t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1);
		if ($this->controlData->useCaptcha($cmdKey)) {
			$displayFieldArray = array_merge($displayFieldArray, array('captcha_response'));
		}

		// Check required, set failure if not ok.
		$failureArray = array();
		foreach ($requiredArray as $k => $theField) {

			$bIsMissing = FALSE;

			if (isset($dataArray[$theField])) {
				if (empty($dataArray[$theField]) && $dataArray[$theField] !== '0') {
					$bIsMissing = TRUE;
				}
			} else {
				$bIsMissing = TRUE;
			}

			if ($bIsMissing) {
				$failureArray[] = $theField;
				$this->missing[$theField] = TRUE;
			}
		}

		$pid = intval($dataArray['pid']);

		// Evaluate: This evaluates for more advanced things than "required" does. But it returns the same error code, so you must let the required-message tell, if further evaluation has failed!
		$bRecordExists = FALSE;

		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			$cmd = $this->controlData->getCmd();
			if ($cmd == 'edit' || $cmdKey == 'edit') {
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
			$countArray['hook'] = array();
			$countArray['preg'] = array();

			foreach ($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$this->evalErrors[$theField] = array();
				$failureMsg[$theField] = array();
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
					// Unset the incoming value is empty and unsetEmpty is specified
				if (array_search('unsetEmpty', $listOfCommands) !== FALSE) {
					if (
						isset($dataArray[$theField]) &&
						empty($dataArray[$theField]) &&
						trim($dataArray[$theField]) !== '0'
					) {
						unset($dataArray[$theField]);
					}
					if (
						isset($dataArray[$theField . '_again']) &&
						empty($dataArray[$theField . '_again']) &&
						trim($dataArray[$theField . '_again']) !== '0'
					) {
						unset($dataArray[$theField . '_again']);
					}
				}

				if (isset($dataArray[$theField]) || isset($dataArray[$theField . '_again']) || !count($origArray) || !isset($origArray[$theField])) {
					foreach ($listOfCommands as $k => $cmd) {
						$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'uniqueGlobal':
							case 'uniqueDeletedGlobal':
							case 'uniqueLocal':
							case 'uniqueDeletedLocal':
								$where = $theField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($dataArray[$theField], $theTable);
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueGlobal') {
									$where .= $GLOBALS['TSFE']->sys_page->deleteClause($theTable);
								}
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueDeletedLocal') {
									$where .= ' AND pid IN (' . $recordTestPid.')';
								}

								$DBrows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,' . $theField, $theTable, $where, '', '', '1');

								if (
									!is_array($dataArray[$theField]) &&
									trim($dataArray[$theField]) != '' &&
									isset($DBrows) &&
									is_array($DBrows) &&
									isset($DBrows[0]) &&
									is_array($DBrows[0])
								) {
									if (!$bRecordExists || $DBrows[0]['uid'] != $dataArray['uid']) {
										// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] =
											$this->getFailureText(
												$theField,
												'uniqueLocal',
												'evalErrors_existed_already'
											);
									}
								}
							break;
							case 'twice':
								$fieldValue = strval($dataArray[$theField]);
								$fieldAgainValue = strval($dataArray[$theField . '_again']);
								if (strcmp($fieldValue, $fieldAgainValue)) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										$this->getFailureText(
											$theField,
											$theCmd,
											'evalErrors_same_twice'
										);
								}
							break;
							case 'email':
								if (!is_array($dataArray[$theField]) && trim($dataArray[$theField]) && !t3lib_div::validEmail($dataArray[$theField])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										$this->getFailureText(
											$theField,
											$theCmd,
											'evalErrors_valid_email'
										);
								}
							break;
							case 'required':
								if (empty($dataArray[$theField]) && $dataArray[$theField] !== '0') {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										$this->getFailureText(
											$theField,
											$theCmd,
											'evalErrors_required'
										);
								}
							break;
							case 'atLeast':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) < $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										sprintf(
											$this->getFailureText(
												$theField,
												$theCmd,
												'evalErrors_atleast_characters'
											),
											$chars
										);
								}
							break;
							case 'atMost':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) > $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										sprintf(
											$this->getFailureText(
												$theField,
												$theCmd,
												'evalErrors_atmost_characters'
											),
											$chars
										);
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
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] =
											sprintf(
												$this->getFailureText(
													$theField,
													$theCmd,
													'evalErrors_unvalid_list'),
													$pid_list
												);
									}
								}
							break;
							case 'upload':
								if ($dataArray[$theField] && is_array($this->tca->TCA['columns'][$theField]['config']) ) {
									if (
										$this->tca->TCA['columns'][$theField]['config']['type'] == 'group' &&
										$this->tca->TCA['columns'][$theField]['config']['internal_type'] == 'file'
									) {
										$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
										$allowedExtArray = t3lib_div::trimExplode(',', $this->tca->TCA['columns'][$theField]['config']['allowed'], 1);
										$maxSize = $this->tca->TCA['columns'][$theField]['config']['max_size'];
										$fileNameArray = $dataArray[$theField];
										$newFileNameArray = array();

										if (is_array($fileNameArray) && $fileNameArray[0] != '') {
											foreach ($fileNameArray as $k => $filename) {
												if (is_array($filename)) {
													$filename = $filename['name'];
												}
												$bAllowedFilename = $this->checkFilename($filename);
												$fI = pathinfo($filename);
												$fileExtension = strtolower($fI['extension']);
												$fullfilename = PATH_site . $uploadPath . '/' . $filename;
												if (
													$bAllowedFilename &&
													(!count($allowedExtArray) || in_array($fileExtension, $allowedExtArray))
												) {
													if (@is_file($fullfilename)) {
														if (!$maxSize || (filesize(PATH_site.$uploadPath.'/'.$filename) < ($maxSize * 1024))) {
															$newFileNameArray[] = $filename;
														} else {
															$this->evalErrors[$theField][] = $theCmd;
															$failureMsg[$theField][] =
																sprintf(
																	$this->getFailureText(
																		$theField,
																		'max_size',
																		'evalErrors_size_too_large'
																	),
																$maxSize
															);
															$failureArray[] = $theField;
															$this->inError[$theField] = TRUE;
															if (@is_file(PATH_site.$uploadPath . '/' . $filename)) {
																@unlink(PATH_site.$uploadPath . '/' . $filename);
															}
														}
													} else {
														if (
															isset($_FILES) &&
															is_array($_FILES) &&
															isset($_FILES['FE']) &&
															is_array($_FILES['FE']) &&
															isset($_FILES['FE']['tmp_name']) &&
															is_array($_FILES['FE']['tmp_name']) &&
															isset($_FILES['FE']['tmp_name'][$theTable]) &&
															is_array($_FILES['FE']['tmp_name'][$theTable]) &&
															isset($_FILES['FE']['tmp_name'][$theTable][$theField]) &&
															is_array($_FILES['FE']['tmp_name'][$theTable][$theField]) &&
															isset($_FILES['FE']['tmp_name'][$theTable][$theField][$k])
														) {
															$bWritePermissionError = TRUE;
														} else {
															$bWritePermissionError = FALSE;
														}
														$this->evalErrors[$theField][] = $theCmd;
														$failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'isfile', ($bWritePermissionError ? 'evalErrors_write_permission' : 'evalErrors_file_upload')), $filename);
														$failureArray[] = $theField;
													}
												} else {
													$this->evalErrors[$theField][] = $theCmd;
													$failureMsg[$theField][] =
														sprintf(
															$this->getFailureText(
																$theField,
																'allowed',
																'evalErrors_file_extension'),
																$fileExtension
														);
													$failureArray[] = $theField;
													$this->inError[$theField] = TRUE;
													if ($bAllowedFilename && @is_file($fullfilename)) {
														@unlink($fullfilename);
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
									$urlParts = parse_url($dataArray[$theField]);
									if (
										$urlParts === FALSE ||
										!t3lib_div::isValidUrl($dataArray[$theField]) ||
										($urlParts['scheme'] !== 'http' && $urlParts['scheme'] !== 'https') ||
										$urlParts['user'] ||
										$urlParts['pass']
									) {
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] =
											$this->getFailureText(
												$theField,
												$theCmd,
												'evalErrors_unvalid_url'
											);
									}
								}
							break;
							case 'date':
								if (
									!is_array($dataArray[$theField]) &&
									$dataArray[$theField] &&
									!$this->evalDate(
										$dataArray[$theField],
										$this->conf['dateFormat']
									)
								) {
									$failureArray[] = $theField;
									$this->inError[$theField] = TRUE;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] =
										$this->getFailureText(
											$theField,
											$theCmd,
											'evalErrors_unvalid_date'
										);
								}
							break;
							case 'preg':
								if (!is_array($dataArray[$theField]) && !(empty($dataArray[$theField]) && $dataArray[$theField] !== '0')) {
									if (isset($countArray['preg'][$theCmd])) {
										$countArray['preg'][$theCmd]++;
									} else {
										$countArray['preg'][$theCmd] = 1;
									}
									$pattern = str_replace('preg[', '', $cmd);
									$pattern = substr($pattern, 0, strlen($pattern) - 1);
									$matches = array();
									$test = preg_match($pattern, $dataArray[$theField], $matches);

									if ($test === FALSE || $test == 0) {
										$failureArray[] = $theField;
										$this->inError[$theField] = TRUE;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] =
											$this->getFailureText(
												$theField,
												$theCmd,
												'evalErrors_' . $theCmd,
												$countArray['preg'][$theCmd],
												$cmd,
												($test === FALSE)
											);
									}
								}
							break;
							case 'hook':
							default:
								if (isset($countArray['hook'][$theCmd])) {
									$countArray['hook'][$theCmd]++;
								} else {
									$countArray['hook'][$theCmd] = 1;
								}
								$extKey = $this->controlData->getExtKey();
								$prefixId = $this->controlData->getPrefixId();
								$hookClassArray = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['model'];
								if (is_array($hookClassArray)) {
									foreach ($hookClassArray as $classRef) {
										$hookObj= t3lib_div::getUserObj($classRef);
										if (
											is_object($hookObj) &&
											method_exists($hookObj, 'evalValues')
										) {
											$test = FALSE; // set it to TRUE when you test the hooks
											$bInternal = FALSE;
											$errorField = $hookObj->evalValues(
												$theTable,
												$dataArray,
												$origArray,
												$markContentArray,
												$cmdKey,
												$requiredArray,
												$theField,
												$cmdParts,
												$bInternal,
												$test, // must be set to FALSE if it is not a test
												$this
											);
											if ($errorField != '') {
												$failureArray[] = $errorField;
												$this->evalErrors[$theField][] = $theCmd;
												if (!$test) {
													$this->inError[$theField] = TRUE;
													$failureMsg[$theField][] =
														$this->getFailureText(
															$theField,
															$theCmd,
															'evalErrors_' . $theCmd,
															$countArray['hook'][$theCmd],
															$cmd,
															$bInternal
														);
												}
												break;
											}
										} else {
											debug ($classRef, 'error in the class name for the hook "model"'); // keep debug
										}
									}
								}
							break;
						}
					}
				}

				if (
					in_array($theField, $displayFieldArray) ||
					in_array($theField, $failureArray)
				) {
					if (!empty($failureMsg[$theField])) {
						if ($markContentArray['###EVAL_ERROR_saved###']) {
							$markContentArray['###EVAL_ERROR_saved###'] .= '<br />';
						}
						$errorMsg = implode($failureMsg[$theField], '<br />');
						$markContentArray['###EVAL_ERROR_saved###'] .= $errorMsg;
					} else {
						$errorMsg = '';
					}
					$markContentArray['###EVAL_ERROR_FIELD_' . $theField . '###'] = ($errorMsg != '' ? $errorMsg : '<!--no error-->');
				}
			}
		}

		if (empty($markContentArray['###EVAL_ERROR_saved###'])) {
			$markContentArray['###EVAL_ERROR_saved###'] = '';
		}

		if ($this->missing['zone'] && t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey)) {
			$staticInfoObj = &t3lib_div::getUserObj('&tx_staticinfotables_pi1');
				// empty zone if there is not zone for the provided country
			$zoneArray = $staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);

			if (
				!isset($zoneArray) ||
				is_array($zoneArray) && !count($zoneArray)
			) {
				unset($this->missing['zone']);
				$k = array_search('zone', $failureArray);
				unset($failureArray[$k]);
			}
		}

		$failure = implode($failureArray, ',');
		$this->controlData->setFailure($failure);

		return $this->evalErrors;
	}


	/**
	* Transforms fields into certain things...
	*
	* @return void  all parsing done directly on input array $dataArray
	*/
	public function parseValues ($theTable, array &$dataArray, array &$origArray, $cmdKey) {

		if (is_array($this->conf['parseValues.'])) {

			foreach($this->conf['parseValues.'] as $theField => $theValue) {

				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				if (in_array('setEmptyIfAbsent', $listOfCommands)) {
					$this->setEmptyIfAbsent($theTable, $theField, $dataArray);
				}
				$internalType = $this->tca->TCA['columns'][$theField]['config']['internal_type'];

				if (
					isset($dataArray[$theField]) ||
					isset($origArray[$theField]) ||
					$internalType=='file'
				) {
					foreach($listOfCommands as $cmd) {
						$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						$bValueAssigned = TRUE;
						if (
							$theField === 'password' &&
							!isset($dataArray[$theField])
						) {
							$bValueAssigned = FALSE;
						}
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
								$dataValue = preg_replace('/[^a-zA-Z]/', '', $dataValue);
							break;
							case 'num':
								$dataValue = preg_replace('/[^0-9]/', '', $dataValue);
							break;
							case 'alphanum':
								$dataValue = preg_replace('/[^a-zA-Z0-9]/', '', $dataValue);
							break;
							case 'alphanum_x':
								$dataValue = preg_replace('/[^a-zA-Z0-9_-]/', '', $dataValue);
							break;
							case 'trim':
								$dataValue = trim($dataValue);
							break;
							case 'random':
								$dataValue = substr(md5(uniqid(microtime(), 1)), 0, intval($cmdParts[1]));
							break;
							case 'files':
								$fieldDataArray = array();
								if ($dataArray[$theField]) {
									if (is_array($dataValue)) {
										$fieldDataArray = $dataValue;
									} else if (is_string($dataValue) && $dataValue) {
										$fieldDataArray = t3lib_div::trimExplode(',', $dataValue, 1);
									}
								}
								$dataValue =
									$this->processFiles(
										$theTable,
										$theField,
										$fieldDataArray,
										$cmdKey
									);
							break;
							case 'multiple':
								$fieldDataArray = array();
								if (!empty($dataArray[$theField])) {
									if (is_array($dataArray[$theField])) {
										$fieldDataArray = $dataArray[$theField];
									} else if (is_string($dataArray[$theField]) && $dataArray[$theField]) {
										$fieldDataArray = t3lib_div::trimExplode(',', $dataArray[$theField], 1);
									}
								}
								$dataValue = $fieldDataArray;
							break;
							case 'checkArray':
								if (is_array($dataValue)) {
									$newDataValue = 0;
									foreach($dataValue as $kk => $vv) {
										$kk = (
											class_exists('t3lib_utility_Math') ?
												t3lib_utility_Math::forceIntegerInRange($kk, 0) :
												t3lib_div::intInRange($kk, 0)
										);

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
									$vv = $dataArray[$fN];
									$vv = preg_replace('/\s+/', '', $vv);
									$vv = preg_replace('/[^a-zA-Z0-9]/', '', $vv);
									$vv = strtolower($vv);
									$hashArray[] = $vv;
								}
								$dataValue = hexdec(substr(md5(serialize($hashArray)), 0, 8));
							break;
							case 'wwwURL':
								if ($dataValue) {
									$urlParts = parse_url($dataValue);
									if ($urlParts !== FALSE) {
										if (!$urlParts['scheme']) {
											$urlParts['scheme'] = 'http';
											$dataValue = $urlParts['scheme'] . '://' . $dataValue;
										}
										if (t3lib_div::isValidUrl($dataValue)) {
											$dataValue = $urlParts['scheme'] . '://' .
												$urlParts['host'] .
												$urlParts['path'] .
												($urlParts['query'] ? '?' . $urlParts['query'] : '') .
												($urlParts['fragment'] ? '#' . $urlParts['fragment'] : '');
										}
									}
								}
							break;
							case 'date':
								if(
									$dataValue &&
									$this->evalDate(
										$dataValue,
										$this->conf['dateFormat']
									)
								) {
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
	public function checkFilename ($filename) {
		$rc = TRUE;

		$fI = pathinfo($filename);
		$fileExtension = strtolower($fI['extension']);
		if (
			strpos($fileExtension, 'php') !== FALSE ||
			strpos($fileExtension, 'htaccess') !== FALSE
		) {
			$rc = FALSE; // no php files are allowed here
		}

		if (strpos($filename, '..') !== FALSE) {
			$rc = FALSE; //  no '..' path is allowed
		}
		return $rc;
	}


	/**
	* Processes uploaded files
	*
	* @param string $theTable: the name of the table being edited
	* @param string  $theField: the name of the field
	* @return array file names
	*/
	public function processFiles ($theTable, $theField, array $fieldDataArray, $cmdKey) {

		if (is_array($this->tca->TCA['columns'][$theField])) {
			$uploadPath = $this->tca->TCA['columns'][$theField]['config']['uploadfolder'];
		}
		$fileNameArray = array();

		if ($uploadPath) {
			if (count($fieldDataArray)) {
				foreach ($fieldDataArray as $file) {
					if (is_array($file)) {
						if ($this->checkFilename($file['name'])) {
							if ($file['submit_delete']) {
								if ($cmdKey !== 'edit') {
									if (@is_file(PATH_site . $uploadPath . '/' . $file['name'])) {
										@unlink(PATH_site . $uploadPath . '/' . $file['name']);
									}
								}
							} else {
								$fileNameArray[] = $file['name'];
							}
						}
					} else {
						if ($this->checkFilename($file)) {
							$fileNameArray[] = $file;
						}
					}
				}
			}
			if (is_array($_FILES['FE']['name'][$theTable][$theField])) {
				foreach($_FILES['FE']['name'][$theTable][$theField] as $i => $filename) {

					if (
						$filename &&
						$this->checkFilename($filename) &&
						$this->evalFileError($_FILES['FE']['error'][$theTable][$theField][$i])
					) {
						$fI = pathinfo($filename);

						if (t3lib_div::verifyFilenameAgainstDenyPattern($fI['name'])) {
							$tmpFilename = basename($filename, '.' . $fI['extension']) . '_' . t3lib_div::shortmd5(uniqid($filename)) . '.' . $fI['extension'];
							$cleanFilename = $this->fileFunc->cleanFileName($tmpFilename);
							$theDestFile = $this->fileFunc->getUniqueName($cleanFilename, PATH_site . $uploadPath . '/');
							$result = t3lib_div::upload_copy_move($_FILES['FE']['tmp_name'][$theTable][$theField][$i], $theDestFile);
							$fI2 = pathinfo($theDestFile);
							$fileNameArray[] = $fI2['basename'];
						}
					}
				}
			}
		}
		return $fileNameArray;
	}


	/**
	* Saves the data into the database
	*
	* @return void  sets $this->saved
	*/
	public function save (
		$theTable,
		array $dataArray,
		array $origArray,
		$token,
		array &$newRow,
		$cmd,
		$cmdKey,
		$pid,
		$password,
		&$hookClassArray
	) {
		$rc = 0;

		switch($cmdKey) {
			case 'edit':
			case 'password':
				$theUid = $dataArray['uid'];
				$rc = $theUid;
				$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
				$aCAuth = $authObj->aCAuth($origArray, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']);

					// Fetch the original record to check permissions
				if (
					$this->conf['edit'] &&
					($GLOBALS['TSFE']->loginUser || $aCAuth)
				) {
						// Must be logged in in order to edit  (OR be validated by email)
					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)));
					$newFieldArray = array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList())));
					$fieldArray = t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1);

						// Do not reset the name if we have no new value
					if (
						!in_array('name', $fieldArray) &&
						!in_array('first_name', $fieldArray) &&
						!in_array('last_name', $fieldArray)
					) {
						$newFieldArray = array_diff($newFieldArray, array('name'));
					}
						// Do not reset the username if we have no new value
					if (!in_array('username', $fieldArray) && $dataArray['username'] == '') {
						$newFieldArray = array_diff($newFieldArray, array('username'));
					}

					if (
						$aCAuth ||
						$this->cObj->DBmayFEUserEdit(
							$theTable,
							$origArray,
							$GLOBALS['TSFE']->fe_user->user,
							$this->conf['allowedGroups'],
							$this->conf['fe_userEditSelf']
						)
					) {
						$outGoingData =
							$this->parseOutgoingData(
								$theTable,
								$cmdKey,
								$pid,
								$this->conf,
								$dataArray,
								$origArray
							);

 						if ($theTable === 'fe_users' && isset($dataArray['password'])) {
 								// Do not set the outgoing password if the incoming password was unset
							$outGoingData['password'] = $password;
 						}
						$newFieldList = implode (',', $newFieldArray);
						if (isset($GLOBALS['TCA'][$theTable]['ctrl']['token'])) {
								// Save token in record
							$outGoingData['token'] = $token;
								// Could be set conditional to adminReview or user confirm
							$newFieldList .= ',token';
						}
						$res =
							$this->cObj->DBgetUpdate(
								$theTable,
								$theUid,
								$outGoingData,
								$newFieldList,
								TRUE
							);
						$this->updateMMRelations($dataArray);
						$this->setSaved(TRUE);
						$newRow = $this->parseIncomingData($outGoingData);
						$this->tca->modifyRow($newRow, FALSE);
						$newRow = array_merge($origArray, $newRow);
						$this->control->userProcess_alt(
							$this->conf['edit.']['userFunc_afterSave'],
							$this->conf['edit.']['userFunc_afterSave.'],
							array('rec' => $newRow, 'origRec' => $origArray)
						);

						// Post-edit processing: call user functions and hooks
						// Call all afterSaveEdit hooks after the record has been edited and saved
						if (is_array($hookClassArray)) {
							foreach($hookClassArray as $classRef) {
								$hookObj = t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveEdit')) {
									$hookObj->registrationProcess_afterSaveEdit(
										$theTable,
										$dataArray,
										$origArray,
										$token,
										$newRow,
										$cmd,
										$cmdKey,
										$pid,
										$newFieldList,
										$this
									);
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

					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)));
					$newFieldList  = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList()))));
					$parsedArray =
						$this->parseOutgoingData(
							$theTable,
							$cmdKey,
							$pid,
							$this->conf,
							$dataArray,
							$origArray
						);

					if ($theTable === 'fe_users') {
						$parsedArray['password'] = $password;
					}
					if (isset($GLOBALS['TCA'][$theTable]['ctrl']['token'])) {

						$parsedArray['token'] = $token;
						$newFieldList  .= ',token';
					}

					$res =
						$this->cObj->DBgetInsert(
							$theTable,
							$this->controlData->getPid(),
							$parsedArray,
							$newFieldList,
							TRUE
						);
					$newId = $GLOBALS['TYPO3_DB']->sql_insert_id();
					$rc = $newId;

						// Enable users to own themselves.
					if (
						$theTable == 'fe_users' &&
						$this->conf['fe_userOwnSelf']
					) {
						$extraList = '';
						$tmpDataArray = array();
						if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id']) {
							$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_cruser_id'];
							$dataArray[$field] = $newId;
							$tmpDataArray[$field] = $newId;
							$extraList .= ',' . $field;
						}

						if ($GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id']) {
							$field = $GLOBALS['TCA'][$theTable]['ctrl']['fe_crgroup_id'];
							if (is_array($dataArray['usergroup'])) {
								list($tmpDataArray[$field]) = $dataArray['usergroup'];
							} else {
								$tmpArray = explode(',', $dataArray['usergroup']);
								list($tmpDataArray[$field]) = $tmpArray;
							}
							$tmpDataArray[$field] = intval($tmpDataArray[$field]);
							$extraList .= ',' . $field;
						}

						if (count($tmpDataArray)) {
							$res =
								$this->cObj->DBgetUpdate(
									$theTable,
									$newId,
									$tmpDataArray,
									$extraList,
									TRUE
								);
						}
					}
					$dataArray['uid'] = $newId;
					$this->updateMMRelations($dataArray);
					$this->setSaved(TRUE);

					$newRow = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $newId);

					if (is_array($newRow)) {
							// Post-create processing: call user functions and hooks
						$newRow = $this->parseIncomingData($newRow);
						$this->tca->modifyRow($newRow, TRUE);
						$this->control->userProcess_alt(
							$this->conf['create.']['userFunc_afterSave'],
							$this->conf['create.']['userFunc_afterSave.'],
							array('rec' => $newRow)
						);

						// Call all afterSaveCreate hooks after the record has been created and saved
						if (is_array ($hookClassArray)) {
							foreach ($hookClassArray as $classRef) {
								$hookObj = t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveCreate')) {
									$hookObj->registrationProcess_afterSaveCreate(
										$theTable,
										$dataArray,
										$origArray,
										$token,
										$newRow,
										$cmd,
										$cmdKey,
										$pid,
										$extraList,
										$this
									);
								}
							}
						}
					} else {
						$this->setError('###TEMPLATE_NO_PERMISSIONS###');
						$this->setSaved(FALSE);
						$rc = FALSE;
					}
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
	public function deleteRecord (
		$theTable,
		array &$origArray,
		array &$dataArray
	) {
		$prefixId = $this->controlData->getPrefixId();

		if ($this->conf['delete']) {
			// If deleting is enabled

			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			$aCAuth = $authObj->aCAuth($origArray,$this->conf['setfixed.']['DELETE.']['_FIELDLIST']);

			if ($GLOBALS['TSFE']->loginUser || $aCAuth) {
				// Must be logged in OR be authenticated by the aC code in order to delete
				// If the recUid selects a record.... (no check here)

				if (is_array($origArray)) {
					if (
						$aCAuth ||
						$this->cObj->DBmayFEUserEdit(
							$theTable,
							$origArray,
							$GLOBALS['TSFE']->fe_user->user,
							$this->conf['allowedGroups'],
							$this->conf['fe_userEditSelf']
						)
					) {
							// Delete the record and display form, if access granted.
						$extKey = $this->controlData->getExtKey();

							// <Ries van Twisk added registrationProcess hooks>
							// Call all beforeSaveDelete hooks BEFORE the record is deleted
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'])) {
							foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'] as $classRef) {
								$hookObj = t3lib_div::getUserObj($classRef);
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
						$res =
							$this->cObj->DBgetDelete(
								$theTable,
								$this->getRecUid(),
								TRUE
							);
						$this->deleteMMRelations(
							$theTable,
							$this->getRecUid(),
							$origArray
						);
						$dataArray = $origArray;
						$this->setSaved(TRUE);
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
	public function deleteFilesFromRecord ($uid) {
		$rec = $GLOBALS['TSFE']->sys_page->getRawRecord($this->controlData->getTable(), $uid);
		$updateFields = array();
		foreach($this->tca->TCA['columns'] as $field => $conf) {
			if ($conf['config']['type'] == 'group' && $conf['config']['internal_type'] == 'file') {
				$updateFields[$field] = '';
				$res =
					$this->cObj->DBgetUpdate(
						$this->controlData->getTable(),
						$uid,
						$updateFields,
						$field,
						TRUE
					);
				unset($updateFields[$field]);
				$delFileArr = explode(',', $rec[$field]);
				foreach($delFileArr as $n) {
					if ($n) {
						$fpath = PATH_site.$conf['config']['uploadfolder'] . '/' . $n;
						if(@is_file($fpath)) {
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
	public function fetchDate ($value, $dateFormat) {

		$rcArray = array('m' => '', 'd' => '', 'y' => '');
		$dateValue = trim($value);
		$split = $this->conf['dateSplit'];
		if (!$split) {
			$split = '-';
		}
		$split = '/' . $split . '/';
		$dateFormatArray = preg_split($split, $dateFormat);
		$dateValueArray = preg_split($split, $dateValue);

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
	public function evalDate ($value, $dateFormat) {
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
	public function updateMMRelations (array $row) {

			// update the MM relation
		$fieldsList = array_keys($row);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {

			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$valuesArray = $row[$colName];
				if (isset($valuesArray) && is_array($valuesArray)) {
					$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($row['uid']));
					$insertFields = array();
					$insertFields['uid_local'] = intval($row['uid']);
					$insertFields['tablenames'] = '';
					$insertFields['sorting'] = 0;
					foreach($valuesArray as $theValue) {
						$insertFields['uid_foreign'] = intval($theValue);
						$insertFields['sorting']++;
						$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($colSettings['config']['MM'], $insertFields);
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
	public function deleteMMRelations ($table, $uid, array $row = array()) {
			// update the MM relation
		$fieldsList = array_keys($row);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (
				in_array($colName, $fieldsList) &&
				$colSettings['config']['type'] == 'select' &&
				$colSettings['config']['MM']
			) {
				$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($colSettings['config']['MM'], 'uid_local=' . intval($uid));
			}
		}
	}	// deleteMMRelations


	/**
	* Updates the input array from preview
	*
	* @param array  $inputArr: new values
	* @return array  updated array
	*/
	public function modifyDataArrForFormUpdate (array $inputArr, $cmdKey) {

		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			foreach($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach($listOfCommands as $k => $cmd) {
					$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					switch($theCmd) {
						case 'twice':
						if (isset($inputArr[$theField])) {
							if (!isset($inputArr[$theField . '_again'])) {
								$inputArr[$theField . '_again'] = $inputArr[$theField];
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
					$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
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
									$alt_theField = $theField . '][' . $a;
									$inputArr[$alt_theField] = 1;
									$this->setAdditionalUpdateFields($this->getAdditionalUpdateFields() . ',' . $alt_theField);
								}
							}
						}
						break;
					}
				}
			}
		}
		$inputArr =
			$this->control->userProcess_alt(
				$this->conf['userFunc_updateArray'],
				$this->conf['userFunc_updateArray.'],
				$inputArr
			);

		foreach($inputArr as $theField => $value) {

			if (is_array($value)) {
				$value = implode(',', $value);
			}
			$inputArr[$theField] = $value;
		}

		$this->controlData->secureInput($inputArr, TRUE);

		return $inputArr;
	}	// modifyDataArrForFormUpdate

	/**
	* Moves first, middle and last name into name
	*
	* @param array $dataArray: incoming array
	* @param string $cmdKey: the command key
	* @param string $theTable: the table in use
	* @return void  done directly on $dataArray passed by reference
	*/
	public function setName (array &$dataArray, $cmdKey, $theTable) {
		if (
			in_array('name', explode(',', $this->getFieldList())) &&
			!in_array('name', t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)) &&
			in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)) &&
			in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1))
		) {
				// Honour Address List (tt_address) configuration settings
			$nameFormat = '';
			if ($theTable === 'tt_address' && t3lib_extMgm::isLoaded('tt_address') && isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address'])) {
				$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address']);
				if (is_array($extConf) && isset($extConf['backwardsCompatFormat'])) {
					$nameFormat = $extConf['backwardsCompatFormat'];
				}
			}
			if ($nameFormat != '') {
				$dataArray['name'] = sprintf(
					$nameFormat,
					$dataArray['first_name'],
					$dataArray['middle_name'],
					$dataArray['last_name']
				);
			} else {
				$dataArray['name'] = trim(trim($dataArray['first_name'])
					. ((in_array('middle_name', t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)) && trim($dataArray['middle_name']) != '') ? ' ' . trim($dataArray['middle_name']) : '' )
					. ' ' . trim($dataArray['last_name']));
			}
		}
	}

	/**
	* Moves email into username if useEmailAsUsername is set
	*
	* @return void  done directly on array $this->dataArray
	*/
	public function setUsername ($theTable, array &$dataArray, $cmdKey) {
		if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $theTable === 'fe_users' && t3lib_div::inList($this->getFieldList(), 'username') && empty($this->evalErrors['email'])) {
			$dataArray['username'] = trim($dataArray['email']);
		}
	}

	/**
	* Transforms incoming timestamps into dates
	*
	* @return parsedArray
	*/
	public function parseIncomingData (array $origArray, $bUnsetZero = TRUE) {

		$parsedArray = array();
		$parsedArray = $origArray;
		if (is_array($this->conf['parseFromDBValues.'])) {
			foreach($this->conf['parseFromDBValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				if (is_array($listOfCommands)) {
					foreach($listOfCommands as $k2 => $cmd) {
						$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'date':
							case 'adodb_date':
								if ($origArray[$theField]) {
									$parsedArray[$theField] = date(
										$this->conf['dateFormat'],
										$origArray[$theField]
									);
								}
								if (!$parsedArray[$theField]) {
									if ($bUnsetZero) {
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
	 * Processes data before entering the database
	 * 1. Transforms outgoing dates into timestamps
	 * 2. Modifies the select fields into the count if mm tables are used.
	 * 3. Deletes de-referenced files
	 *
	 * @return parsedArray
	 */
	public function parseOutgoingData (
		$theTable,
		$cmdKey,
		$pid,
		$conf,
		array $dataArray,
		array $origArray
	) {
		$tablesObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');
		$parsedArray = $dataArray;

		if (is_array($this->conf['parseToDBValues.'])) {

			foreach ($this->conf['parseToDBValues.'] as $theField => $theValue) {
				$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
				foreach($listOfCommands as $k2 => $cmd) {
					$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
					$theCmd = trim($cmdParts[0]);
					if (($theCmd == 'date' || $theCmd == 'adodb_date') && $dataArray[$theField]) {
						if(strlen($dataArray[$theField]) == 8) {
							$parsedArray[$theField] = substr($dataArray[$theField], 0, 4) . '-' . substr($dataArray[$theField], 4, 2) . '-' . substr($dataArray[$theField], 6, 2);
						} else {
							$parsedArray[$theField] = $dataArray[$theField];
						}
						$dateArray = $this->fetchDate($parsedArray[$theField], $this->conf['dateFormat']);
					}

					switch ($theCmd) {
						case 'date':
						case 'adodb_date':
							if ($dataArray[$theField]) {
								$parsedArray[$theField] =
									mktime(
										0,
										0,
										0,
										$dateArray['m'],
										$dateArray['d'],
										$dateArray['y']
									);

								// Consider time zone offset
								// This is necessary if the server wants to have the date not in GMT,
								// so the offset must be added first to compensate for this
								// it stands to reason to execute it all the time
								if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['serverTimeZone'])) {
									$parsedArray[$theField] += ($GLOBALS['TYPO3_CONF_VARS']['SYS']['serverTimeZone'] * 3600);
								}
							}
							break;
						case 'deleteUnreferencedFiles':
							$fieldConfig = $this->tca->TCA['columns'][$theField]['config'];
							if (
								is_array($fieldConfig) &&
								$fieldConfig['type'] === 'group' &&
								$fieldConfig['internal_type'] === 'file' &&
								$fieldConfig['uploadfolder']
							) {
								$uploadPath = $fieldConfig['uploadfolder'];
								$origFiles = array();
								if (is_array($origArray[$theField])) {
									$origFiles = $origArray[$theField];
								} else if ($origArray[$theField]) {
									$origFiles = t3lib_div::trimExplode(',', $origArray[$theField], 1);
								}
								$updatedFiles = array();
								if (is_array($dataArray[$theField])) {
									$updatedFiles = $dataArray[$theField];
								} else if ($dataArray[$theField]) {
									$updatedFiles = t3lib_div::trimExplode(',', $dataArray[$theField], 1);
								}
								$unReferencedFiles = array_diff($origFiles, $updatedFiles);
								foreach ($unReferencedFiles as $file) {
									if(@is_file(PATH_site . $uploadPath . '/' . $file)) {
										@unlink(PATH_site . $uploadPath . '/' . $file);
									}
								}
							}
							break;
					}
				}
			}
		}

			// update the MM relation count field
		$fieldsList = array_keys($parsedArray);
		foreach ($this->tca->TCA['columns'] as $colName => $colSettings) {
			if (isset($parsedArray[$colName])) {

				$fieldObj = &$addressObj->getFieldObj($colName);
				if (isset($fieldObj) && is_object($fieldObj)) {

					$foreignTable = $this->tca->getForeignTable($colName);
					$fieldObj->parseOutgoingData(
						$theTable,
						$colName,
						$foreignTable,
						$cmdKey,
						$pid,
						$conf,
						$dataArray,
						$origArray,
						$parsedArray
					);
				}

				if (is_array ($parsedArray[$colName])) {
					if (
						in_array($colName, $fieldsList) &&
						$colSettings['config']['type'] == 'select' &&
						$colSettings['config']['MM']
					) {
						// set the count instead of the comma separated list
						if ($parsedArray[$colName]) {
							$parsedArray[$colName] = count($parsedArray[$colName]);
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
	public function evalFileError ($error_code) {
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


	public function getInError () {
		return $this->inError;
	}

	/*
	 * Sets the index $theField of the incoming data array to empty value depending on type of $theField
	 * as defined in the TCA for $theTable
	 *
	 * @param string $theTable: the name of the table
	 * @param string $theField: the name of the field
	 * @param array $dataArray: the incoming data array
	 * @return void
	 */
	protected function setEmptyIfAbsent($theTable, $theField, array &$dataArray) {
		if (!isset($dataArray[$theField])) {
			$fieldConfig = $this->tca->TCA['columns'][$theField]['config'];
			if (is_array($fieldConfig)) {
				$type = $fieldConfig['type'];
				switch ($type) {
					case 'check':
					case 'radio':
						$dataArray[$theField] = 0;
						break;
					case 'input':
						$eval = $fieldConfig['eval'];
						switch ($eval) {
							case 'int':
							case 'date':
							case 'datetime':
							case 'time':
							case 'timesec':
								$dataArray[$theField] = 0;
								break;
							default:
								$dataArray[$theField] = '';
								break;
						}
						break;
					default:
						$dataArray[$theField] = '';
						break;
				}
			} else {
				$dataArray[$theField] = '';
			}
		}
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_data.php']);
}
?>