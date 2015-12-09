<?php
namespace SJBR\SrFeuserRegister\Domain;
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

use SJBR\SrFeuserRegister\Captcha\CaptchaManager;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Security\StorageSecurity;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Data store functions
 */
class Data
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
	 * Content object
	 *
	 * @var ContentObjectRenderer
	 */
	protected $cObj;

	/**
	 * The request parameters object
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * The Static Info object
	 *
	 * @var \SJBR\StaticInfoTables\PiBaseApi
	 */
	protected $staticInfoObj = null;

	/**
	 * The basic file functions utility object
	 *
	 * @var \TYPO3\CMS\Core\Utility\File\BasicFileUtility
	 */
	public $fileFunc = null;

	/**
	 * List of fields allowed for editing and creation
	 *
	 * @var string
	 */
	protected $fieldList = '';

	/**
	 * List of fields reserved as administration fields
	 *
	 * @var string
	 */
	protected $adminFieldList = '';

	/**
	 * List of special fields like captcha
	 *
	 * @var string
	 */
	protected $specialFieldList = '';

	/**
	 * Array of required field names per command key
	 *
	 * @var array
	 */
	 protected $requiredFieldsArray = array();

	/**
	 * The array of incoming data
	 *
	 * @var array
	 */
	protected $dataArray = array();

	/**
	 * The array of incoming data
	 *
	 * @var array
	 */
	protected $origArray = array();
	
	/**
	 * The list of fields that ar in error
	 *
	 * @var string
	 */
	protected $failure = '';

	protected $evalErrors = array();

	/**
	 * True when data was saved
	 *
	 * @var bool
	 */
	protected $saved = false;

	public $addTableArray = array();

	public $error;
	public $additionalUpdateFields = '';

	/**
	 * The uid of the current record
	 *
	 * @var int
	 */
	protected $recUid = 0;

	public $missing = array(); // array of required missing fields
	public $inError = array(); // array of fields with eval errors other than absence

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param ContentObjectRenderer $cObj: the content object
	 * @param Parameters $parameters: the request parameters object
	 * @param string $adminFieldList: list of table fields that are considered reserved for administration purposes
	 * @return void
	 */
	public function __construct(
		$extensionKey,
		$prefixId,
		$theTable,
		array &$conf,
		ContentObjectRenderer $cObj,
		Parameters $parameters,
		$adminFieldList
	) {
	 	$this->extensionKey = $extensionKey;
	 	$this->extensionName = GeneralUtility::underscoredToUpperCamelCase($this->extensionKey);
	 	$this->prefixId = $prefixId;
	 	$this->theTable = $theTable;
	 	$this->conf =& $conf;
	 	$this->cObj = $cObj;
	 	$this->parameters = $parameters;
	 	$this->fileFunc = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\File\\BasicFileUtility');
		if (ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$this->staticInfoObj = GeneralUtility::makeInstance('SJBR\\StaticInfoTables\\PiBaseApi');
			if ($this->staticInfoObj->needsInit()) {
				$this->staticInfoObj->init();
			}
		}
		// Set the lists of admin fields
		$this->setAdminFieldList($adminFieldList);
		// Get the incoming data
		$this->getIncomingData();
	}

	/**
	 * Get the data entered in the displayed form
	 *
	 * @return void
	 */
	protected function getIncomingData()
	{
		// Get POST parameters
		$fe = GeneralUtility::_POST('FE');
		if (isset($fe) && is_array($fe)) {
			$feDataArray = $fe[$this->theTable];
			SecuredData::secureInput($feDataArray, false);
			$this->modifyRow($feDataArray, false);
			SessionData::securePassword($this->extensionKey, $feDataArray);
			$this->setDataArray($feDataArray);
		}	
	}

	/**
	 * Set the incoming data array
	 *
	 * @param array $dataArray: the incoming data array
	 * @return void
	 */
	public function setDataArray(array $dataArray)
	{
		$this->dataArray = $dataArray;
	}

	/**
	 * Set configuration
	 *
	 * @param array $conf: the configuration array
	 * @return void
	 */
	public function setConfiguration(array $conf)
	{
		$this->conf = $conf;
	}

	/**
	 * Get the incoming data array
	 *
	 * @return array the incoming data array
	 */
	public function getDataArray()
	{
		return $this->dataArray;
	}

	/**
	 * Resets the incoming data array
	 *
	 * @return void
	 */
	public function resetDataArray()
	{
		$this->dataArray = array();
	}

	/**
	 * Get the list of fields that are in error
	 *
	 * @return string the list of fields that are in error
	 */
	public function getFailure()
	{
		return $this->failure;
	}

	/**
	 * Set the list of fields that are in error
	 *
	 * @param string the list of fields that are in error
	 * @return void
	 */
	protected function setFailure($failure)
	{
		$this->failure = $failure;
	}

	protected function setError($error) {
		$this->error = $error;
	}

	public function getError () {
		return $this->error;
	}

	public function setSaved($value)
	{
		$this->saved = $value;
	}

	public function getSaved()
	{
		return $this->saved;
	}

	/**
	 * Get the list of fields allowed for editing and creation
	 *
	 * @return string a list of fields allowed for editing and creation
	 */
	public function getFieldList()
	{
		if (empty($this->fieldList)) {
			$excludeFields = array('felogin_forgotHash', 'felogin_redirectPid', 'lastlogin', 'lockToDomain', 'starttime', 'endtime', 'token', 'TSconfig');
			$this->fieldList = implode(',', array_diff(array_keys($GLOBALS['TCA'][$this->theTable]['columns']), $excludeFields));		
		}
		return $this->fieldList;
	}

	/**
	 * Get the array of required fields names
	 *
	 * @return array required fields names
	 */
	public function getRequiredFieldsArray($cmdKey)
	{
		if (!isset($this->requiredFieldsArray[$cmdKey])) {
			$this->requiredFieldsArray[$cmdKey] = array();
			if (isset($this->conf[$cmdKey . '.']['required']) && isset($this->conf[$cmdKey . '.']['fields'])) {
				$this->requiredFieldsArray[$cmdKey] = array_intersect(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'], true), GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true));
			}
		}
		return $this->requiredFieldsArray[$cmdKey];
	}

	/**
	 * Set the list of reserved administration fields
	 *
	 * @param string a list of reserved fields
	 * @return void
	 */
	protected function setAdminFieldList($adminFieldList)
	{
		if (!$adminFieldList) {
			$adminFieldList = 'username,password,name,disable,usergroup,by_invitation,tx_srfeuserregister_password';
		}
		if (trim($this->conf['addAdminFieldList'])) {
			$adminFieldList .= ',' . trim($conf['addAdminFieldList']);
		}
		// Honour Address List (tt_address) configuration settings
		if ($this->theTable === 'tt_address' && ExtensionManagementUtility::isLoaded('tt_address')) {
			$settings = \TYPO3\TtAddress\Utility\SettingsUtility::getSettings();
			if (!$settings->isStoreBackwardsCompatName()) {
				// Remove name from adminFieldList
				$adminFieldList = GeneralUtility::rmFromList('name', $adminFieldList);
			}
		}
		$this->adminFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), GeneralUtility::trimExplode(',', $adminFieldList, true)));
	}

	/**
	 * Get the list of reserved administration fields
	 *
	 * @return string a list of reserved fields
	 */
	public function getAdminFieldList()
	{
		return $this->adminFieldList;
	}

	/**
	 * Get the list of special fields like captcha
	 *
	 * @return string a list of special fields
	 */
	public function getSpecialFieldList()
	{
		if (empty($this->specialFieldList)) {
			if (CaptchaManager::isLoaded($this->extensionKey)) {
				$this->specialFieldList = 'captcha_response';
			}
		}
		return $this->specialFieldList;
	}

	public function getAdditionalUpdateFields()
	{
		return $this->additionalUpdateFields;
	}


	public function setAdditionalUpdateFields ($additionalUpdateFields) {
		$this->additionalUpdateFields = $additionalUpdateFields;
	}


	public function setRecUid($uid)
	{
		$this->recUid = (int) $uid;
	}


	public function getRecUid()
	{
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


	public function setOrigArray(array $origArray)
	{
		$this->origArray = $origArray;
	}


	public function getOrigArray()
	{
		return $this->origArray;
	}


	public function bNewAvailable () {
		$dataArray = $this->getDataArray();
		$rc = ($dataArray['username'] != '' || $dataArray['email'] != '');
		return $rc;
	}

	/**
	 * Overrides field values as specified by TS setup
	 *
	 * @param array $dataArray: the incoming data array
	 * @param string $cmdKey: the command being processed
	 * @return void all overriding done directly on array $dataArray
	 */
	public function overrideValues(array &$dataArray, $cmdKey)
	{
		if (is_array($this->conf[$cmdKey . '.']['overrideValues.'])) {
			foreach ($this->conf[$cmdKey . '.']['overrideValues.'] as $theField => $theValue) {
				if ($theField === 'usergroup' && $this->theTable === 'fe_users' && $this->conf[$cmdKey . '.']['allowUserGroupSelection']) {
					$overrideArray = GeneralUtility::trimExplode(',', $theValue, true);
					if (is_array($dataArray[$theField])) {
						$dataValue = array_merge($dataArray[$theField], $overrideArray);
					} else {
						$dataValue = $overrideArray;
					}
					$dataValue = array_unique($dataValue);
				} else {
					$stdWrap = $this->conf[$cmdKey . '.']['overrideValues.'][$theField . '.'];
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
	}

	/**
	 * Fetches default field values as specified by TS setup
	 *
	 * @param string $cmdKey: the command being processed
	 * @return array a data row with default key/value pairs
	 */
	public function defaultValues($cmdKey)
	{
		$dataArray = array();
		if (is_array($this->conf[$cmdKey . '.']['defaultValues.'])) {
			foreach ($this->conf[$cmdKey . '.']['defaultValues.'] as $theField => $theValue) {
				$dataArray[$theField] = $theValue;
			}
		}
		return $dataArray;
	}

	/**
	 * Gets the error message to be displayed
	 *
	 * @param string $theField: the name of the field being validated
	 * @param string $theRule: the name of the validation rule being evaluated
	 * @param string $label: a default error message provided by the invoking function
	 * @param integer $orderNo: ordered number of the rule for the field (>0 if used)
	 * @param string $param: parameter for the error message
	 * @return string the error message to be displayed
	 */
	public function getFailureText($theField, $theRule, $label, $orderNo = 0, $param = '')
	{
		$failureLabel = '';
 		if ($orderNo && $theRule && isset($this->conf['evalErrors.'][$theField . '.'][$theRule . '.'])) {
			$count = 0;
			foreach ($this->conf['evalErrors.'][$theField . '.'][$theRule . '.'] as $k => $v) {
				if (MathUtility::canBeInterpretedAsInteger($k)) {
					$count++;
					if ($count === $orderNo) {
						$failureLabel = $v;
						break;
					}
				}
			}
		}
		if (!$failureLabel) {
			if ($theRule && isset($this->conf['evalErrors.'][$theField . '.'][$theRule])) {
				$failureLabel = $this->conf['evalErrors.'][$theField . '.'][$theRule];
			} else {
				if ($theRule) {
					$failureLabel = LocalizationUtility::translate('evalErrors_' . $theRule . '_' . $theField, $this->extensionName);
					$failureLabel = $failureLabel ?: LocalizationUtility::translate('evalErrors_' . $theRule, $this->extensionName);
				}
				if (!$failureLabel) {
					$failureLabel = LocalizationUtility::translate($label, $this->extensionName);
				}
			}
		}
		if ($param) {
			$failureLabel = sprintf($failureLabel, $param);
		}
		return $failureLabel;
	}

	/**
	 * Applies validation rules specified in TS setup
	 *
	 * @param array $dataArray: the incoming data array
	 * @param string $cmdKey: the command being processed
	 * @return void on return, the parameters failure will contain the list of fields which were not ok
	 */
	public function evalValues(array &$dataArray, array &$origArray, $markerObj, $cmdKey) {
		$failureArray = array();
		$failureMsg = array();
		$markerArray = array();
		$displayFieldArray = GeneralUtility::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], true);
		if (CaptchaManager::useCaptcha($cmdKey, $this->conf, $this->extensionKey)) {
			$displayFieldArray = array_merge($displayFieldArray, array('captcha_response'));
		}
		// Check required fields, set failure if missing.
		$requiredArray = $this->getRequiredFieldsArray($cmdKey);
		foreach ($requiredArray as $theField) {
			$isMissing = empty($dataArray[$theField]);
			if ($isMissing) {
				$failureArray[] = $theField;
				$this->missing[$theField] = true;
			}
		}

		$pid = (int) $dataArray['pid'];

		// Evaluate: This evaluates for more advanced things than "required" does.
		// But it returns the same error code, so you must let the required-message, if further evaluation has failed!
		$bRecordExists = false;
		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			$cmd = $this->parameters->getCmd();
			if ($cmd === 'edit' || $cmdKey === 'edit') {
				if ($pid) {
					// This may be tricked if the input has the pid-field set but the edit-field list does NOT allow the pid to be edited. Then the pid may be false.
					$recordTestPid = $pid;
				} else {
					$tempRecArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $dataArray['uid']);
					$recordTestPid = (int) $tempRecArr['pid'];
				}
				$bRecordExists = ($recordTestPid != 0);
			} else {
				$thePid = $this->parameters->getPid();
				$recordTestPid = $thePid ? $thePid : MathUtility::convertToPositiveInteger($pid);
			}
			$countArray = array();
			$countArray['hook'] = array();
			$countArray['preg'] = array();

			foreach ($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$this->evalErrors[$theField] = array();
				$failureMsg[$theField] = array();
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
					// Unset the incoming value is empty and unsetEmpty is specified
				if (array_search('unsetEmpty', $listOfCommands) !== false) {
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
						// Enable parameters after each command enclosed in brackets [..]
						$cmdParts = preg_split('/\[|\]/', $cmd);
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'uniqueGlobal':
							case 'uniqueDeletedGlobal':
							case 'uniqueLocal':
							case 'uniqueDeletedLocal':
								$where = $theField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($dataArray[$theField], $this->theTable);
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueGlobal') {
									$where .= $GLOBALS['TSFE']->sys_page->deleteClause($this->theTable);
								}
								if ($theCmd == 'uniqueLocal' || $theCmd == 'uniqueDeletedLocal') {
									$where .= ' AND pid IN (' . $recordTestPid.')';
								}

								$DBrows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,' . $theField, $this->theTable, $where, '', '', '1');
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
										$this->inError[$theField] = true;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] = $this->getFailureText($theField, 'uniqueLocal', 'evalErrors_existed_already');
									}
								}
							break;
							case 'twice':
								$fieldValue = strval($dataArray[$theField]);
								$fieldAgainValue = strval($dataArray[$theField . '_again']);
								if (strcmp($fieldValue, $fieldAgainValue)) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_same_twice');
								}
							break;
							case 'email':
								if (!is_array($dataArray[$theField]) && trim($dataArray[$theField]) && !GeneralUtility::validEmail($dataArray[$theField])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_valid_email');
								}
							break;
							case 'required':
								if (empty($dataArray[$theField]) && $dataArray[$theField] !== '0') {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_required');
								}
							break;
							case 'atLeast':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) < $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_atleast_characters'), $chars);
								}
							break;
							case 'atMost':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) > $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_atmost_characters'), $chars);
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

									if (!$pid_list || !GeneralUtility::inList($pid_list, $dataArray[$theField])) {
										$failureArray[] = $theField;
										$this->inError[$theField] = true;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] = sprintf($this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_list'), $pid_list);
									}
								}
							break;
							case 'upload':
								if ($dataArray[$theField] && is_array($GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config'])) {
									$colSettings = $GLOBALS['TCA'][$this->theTable]['columns'][$theField];
									$colConfig = $colSettings['config'];
									if ($colConfig['type'] === 'group' && $colConfig['internal_type'] === 'file') {
										$uploadPath = $colConfig['uploadfolder'];
										$allowedExtArray = GeneralUtility::trimExplode(',', $colConfig['allowed'], true);
										$maxSize = $colConfig['max_size'];
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
												if ($bAllowedFilename && (!count($allowedExtArray) || in_array($fileExtension, $allowedExtArray))) {
													if (@is_file($fullfilename)) {
														if (!$maxSize || (filesize(PATH_site.$uploadPath.'/'.$filename) < ($maxSize * 1024))) {
															$newFileNameArray[] = $filename;
														} else {
															$this->evalErrors[$theField][] = $theCmd;
															$failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'max_size', 'evalErrors_size_too_large'), $maxSize);
															$failureArray[] = $theField;
															$this->inError[$theField] = true;
															if (@is_file(PATH_site.$uploadPath . '/' . $filename)) {
																@unlink(PATH_site.$uploadPath . '/' . $filename);
															}
														}
													} else {
														$writePermissionError = isset($_FILES) && isset($_FILES['FE']['tmp_name'][$this->theTable][$theField][$k]);
														$this->evalErrors[$theField][] = $theCmd;
														$failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'isfile', ($writePermissionError ? 'evalErrors_write_permission' : 'evalErrors_file_upload')), $filename);
														$failureArray[] = $theField;
													}
												} else {
													$this->evalErrors[$theField][] = $theCmd;
													$failureMsg[$theField][] = sprintf($this->getFailureText($theField, 'allowed', 'evalErrors_file_extension'), $fileExtension);
													$failureArray[] = $theField;
													$this->inError[$theField] = true;
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
										$urlParts === false
										|| !GeneralUtility::isValidUrl($dataArray[$theField])
										|| ($urlParts['scheme'] != 'http' && $urlParts['scheme'] != 'https')
										|| $urlParts['user']
										|| $urlParts['pass']
									) {
										$failureArray[] = $theField;
										$this->inError[$theField] = true;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_url');
									}
								}
							break;
							case 'date':
								if (
									!is_array($dataArray[$theField])
									&& $dataArray[$theField]
									&& !$this->evalDate($dataArray[$theField], $this->conf['dateFormat'])
								) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_date');
								}
							break;
							case 'preg':
								if (
									!is_array($dataArray[$theField])
									&& !empty($dataArray[$theField])
									&& $dataArray[$theField] !== '0'
								) {
									if (isset($countArray['preg'][$theCmd])) {
										$countArray['preg'][$theCmd]++;
									} else {
										$countArray['preg'][$theCmd] = 1;
									}
									$pattern = str_replace('preg[', '', $cmd);
									$pattern = substr($pattern, 0, strlen($pattern) - 1);
									$matches = array();
									$test = preg_match($pattern, $dataArray[$theField], $matches);

									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_' . $theCmd, $countArray['preg'][$theCmd], $cmd);
								}
							break;
							case 'hook':
							default:
								if (isset($countArray['hook'][$theCmd])) {
									$countArray['hook'][$theCmd]++;
								} else {
									$countArray['hook'][$theCmd] = 1;
								}
								$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
								if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha'])) {
										$hookClassArray = array_merge($hookClassArray, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha']);
								}
								foreach ($hookClassArray as $classRef) {
									$hookObj = GeneralUtility::makeInstance($classRef);
									if (is_object($hookObj) && method_exists($hookObj, 'evalValues')) {
										$errorField = $hookObj->evalValues($this->theTable, $dataArray, $theField, $cmdKey, $cmdParts);
										if ($errorField !== '') {
											$failureArray[] = $errorField;
											$this->evalErrors[$theField][] = $theCmd;
											$this->inError[$theField] = true;
											$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_' . $theCmd, $countArray['hook'][$theCmd], $cmd);
										}
									}
								}
							break;
						}
					}
				}

				if (in_array($theField, $displayFieldArray) || in_array($theField, $failureArray)) {
					if (!empty($failureMsg[$theField])) {
						if ($markerArray['###EVAL_ERROR_saved###']) {
							$markerArray['###EVAL_ERROR_saved###'] .= '<br />';
						}
						$errorMsg = implode($failureMsg[$theField], '<br />');
						$markerArray['###EVAL_ERROR_saved###'] .= $errorMsg;
					} else {
						$errorMsg = '';
					}
					$markerArray['###EVAL_ERROR_FIELD_' . $theField . '###'] = ($errorMsg != '' ? $errorMsg : '<!--no error-->');
				}
			}
		}

		if (empty($markerArray['###EVAL_ERROR_saved###'])) {
			$markerArray['###EVAL_ERROR_saved###'] = '';
		}

		if ($this->staticInfoObj !== null && $this->missing['zone']) {
			// empty zone if there is no zone for the provided country
			$zoneArray = $staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);
			if (empty($zoneArray)) {
				unset($this->missing['zone']);
				$k = array_search('zone', $failureArray);
				unset($failureArray[$k]);
			}
		}

		$failure = implode($failureArray, ',');
		$this->setFailure($failure);
		$markerObj->addEvalValuesMarkers($markerArray);
		return $this->evalErrors;
	}

	/**
	 * Transforms fields into certain things...
	 *
	 * @param array $dataArray: the incoming data array
	 * @param array $origArray: the original data array
	 * @param string $cmdKey: the comman key being processed
	 * @return void all parsing done directly on input array $dataArray
	 */
	public function parseValues(array &$dataArray, array $origArray, $cmdKey)
	{
		if (is_array($this->conf['parseValues.'])) {
			foreach ($this->conf['parseValues.'] as $theField => $theValue) {
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				if (in_array('setEmptyIfAbsent', $listOfCommands)) {
					$this->setEmptyIfAbsent($theField, $dataArray);
				}
				$internalType = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config']['internal_type'];
				if (isset($dataArray[$theField]) || isset($origArray[$theField]) || $internalType === 'file') {
					foreach ($listOfCommands as $cmd) {
						// Enable parameters after each command enclosed in brackets [..].
						$cmdParts = preg_split('/\[|\]/', $cmd);
						$theCmd = trim($cmdParts[0]);
						$bValueAssigned = true;
						if ($theField === 'password' && !isset($dataArray[$theField])) {
							$bValueAssigned = false;
						}
						$dataValue = (isset($dataArray[$theField]) ? $dataArray[$theField] : $origArray[$theField]);
						switch ($theCmd) {
							case 'int':
								$dataValue = (int) $dataValue;
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
										$fieldDataArray = GeneralUtility::trimExplode(',', $dataValue, true);
									}
								}
								$dataValue = $this->processFiles($theField, $fieldDataArray, $cmdKey);
								break;
							case 'multiple':
								$fieldDataArray = array();
								if (!empty($dataArray[$theField])) {
									if (is_array($dataArray[$theField])) {
										$fieldDataArray = $dataArray[$theField];
									} else if (is_string($dataArray[$theField])) {
										$fieldDataArray = GeneralUtility::trimExplode(',', $dataArray[$theField], true);
									}
								}
								$dataValue = $fieldDataArray;
								break;
							case 'checkArray':
								if (is_array($dataValue)) {
									$newDataValue = 0;
									foreach ($dataValue as $kk => $vv) {
										$kk = MathUtility::forceIntegerInRange($kk, 0);
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
								$otherFields = GeneralUtility::trimExplode(';', $cmdParts[1], true);
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
									if ($urlParts !== false) {
										if (!$urlParts['scheme']) {
											$urlParts['scheme'] = 'http';
											$dataValue = $urlParts['scheme'] . '://' . $dataValue;
										}
										if (GeneralUtility::isValidUrl($dataValue)) {
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
								if ($dataValue && $this->evalDate($dataValue, $this->conf['dateFormat'])) {
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
									$bValueAssigned = false;
								}
								break;
							default:
								$bValueAssigned = false;
								break;
						}
						if ($bValueAssigned) {
							$dataArray[$theField] = $dataValue;
						}
					}
				}
			}
		}
	}

	/**
	 * Checks for valid filenames
	 *
	 * @param string  $filename: the name of the file
	 * @return bool true, if the filename is allowed
	 */
	protected function checkFilename($filename)
	{
		$fI = pathinfo($filename);
		$fileExtension = strtolower($fI['extension']);
		return !(strpos($fileExtension, 'php') !== false) && !(strpos($fileExtension, 'htaccess') !== false) && !(strpos($filename, '..') !== false);
	}

	/**
	 * Processes uploaded files
	 *
	 * @param string $theField: the name of the field
	 * @param array $fieldData: field value
	 * @param string $cmdKe: the command key being processed
	 * @return array file names
	 */
	protected function processFiles($theField, array $fieldData, $cmdKey)
	{
		if (is_array($GLOBALS['TCA'][$this->theTable]['columns'][$theField])) {
			$uploadPath = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config']['uploadfolder'];
		}
		$fileNameArray = array();
		if ($uploadPath) {
			if (count($fieldData)) {
				foreach ($fieldData as $file) {
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
			if (is_array($_FILES['FE']['name'][$this->theTable][$theField])) {
				foreach($_FILES['FE']['name'][$this->theTable][$theField] as $i => $filename) {
					if (
						$filename &&
						$this->checkFilename($filename) &&
						$this->evalFileError($_FILES['FE']['error'][$this->theTable][$theField][$i])
					) {
						$fI = pathinfo($filename);
						if (GeneralUtility::verifyFilenameAgainstDenyPattern($fI['name'])) {
							$tmpFilename = basename($filename, '.' . $fI['extension']) . '_' . GeneralUtility::shortmd5(uniqid($filename)) . '.' . $fI['extension'];
							$cleanFilename = $this->fileFunc->cleanFileName($tmpFilename);
							$theDestFile = $this->fileFunc->getUniqueName($cleanFilename, PATH_site . $uploadPath . '/');
							$result = GeneralUtility::upload_copy_move($_FILES['FE']['tmp_name'][$this->theTable][$theField][$i], $theDestFile);
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
	 * @param array $dataArray: the incoming data array
	 * @param array $origArray: the original data array
	 * @return void  sets $this->saved
	 */
	public function save(array $dataArray, array $origArray, $token, array &$newRow, $cmd, $cmdKey, $pid)
	{
		$rc = 0;
		switch ($cmdKey) {
			case 'edit':
			case 'password':
				$theUid = (int) $dataArray['uid'];
				$rc = $theUid;
				$aCAuth = Authentication::aCAuth($this->parameters->getAuthCode(), $origArray, $this->conf, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']);
				// Fetch the original record to check permissions
				if ($this->conf['edit'] && ($GLOBALS['TSFE']->loginUser || $aCAuth)) {
					// Must be logged in in order to edit  (OR be validated by email)
					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true)));
					$newFieldArray = array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList())));
					$fieldArray = GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true);
					// Do not reset the name if we have no new value
					if (!in_array('name', $fieldArray) && !in_array('first_name', $fieldArray) && !in_array('last_name', $fieldArray)) {
						$newFieldArray = array_diff($newFieldArray, array('name'));
					}
					// Do not reset the username if we have no new value
					if (!in_array('username', $fieldArray) && $dataArray['username'] == '') {
						$newFieldArray = array_diff($newFieldArray, array('username'));
					}

					if ($aCAuth || $this->cObj->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						$outGoingData = $this->parseOutgoingData($cmdKey, $pid, $dataArray, $origArray);
 						if ($this->theTable === 'fe_users' && !empty($dataArray['password'])) {
 							// Do not set the outgoing password if the incoming password was unset
							$outGoingData['password'] = SessionData::readPasswordForStorage($this->extensionKey);
 						}
						$newFieldList = implode (',', $newFieldArray);
						if (isset($GLOBALS['TCA'][$this->theTable]['ctrl']['token'])) {
							// Save token in record
							$outGoingData['token'] = $token;
							// Could be set conditional to adminReview or user confirm
							$newFieldList .= ',token';
						}
						$res = $this->cObj->DBgetUpdate($this->theTable, $theUid, $outGoingData, $newFieldList, true);
						$this->updateMMRelations($dataArray);
						$this->setSaved(true);

						$newRow = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $theUid);
						$newRow = $this->parseIncomingData($newRow);
						$this->modifyRow($newRow, true);

						// Call all afterSaveEdit hooks after the record has been edited and saved
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'])) {
							foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'] as $classRef) {
								$hookObj = GeneralUtility::makeInstance($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveEdit')) {
									$hookObj->registrationProcess_afterSaveEdit(
										$this->theTable,
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
					$newFieldList = implode(',', array_intersect(explode(',', $this->getFieldList()), GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true)));
					$newFieldList = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->getAdminFieldList()))));
					$parsedArray = $this->parseOutgoingData($cmdKey, $pid, $dataArray, $origArray);
					if ($this->theTable === 'fe_users') {
						$parsedArray['password'] = SessionData::readPasswordForStorage($this->extensionKey);
					}
					if (isset($GLOBALS['TCA'][$this->theTable]['ctrl']['token'])) {
						$parsedArray['token'] = $token;
						$newFieldList  .= ',token';
					}
					$res = $this->cObj->DBgetInsert($this->theTable, $pid, $parsedArray, $newFieldList, true);
					$newId = $GLOBALS['TYPO3_DB']->sql_insert_id();
					$rc = $newId;
					// Enable users to own themselves.
					if ($this->theTable === 'fe_users' && $this->conf['fe_userOwnSelf']) {
						$extraList = '';
						$tmpDataArray = array();
						if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id']) {
							$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id'];
							$dataArray[$field] = $newId;
							$tmpDataArray[$field] = $newId;
							$extraList .= ',' . $field;
						}
						if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id']) {
							$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id'];
							if (is_array($dataArray['usergroup'])) {
								list($tmpDataArray[$field]) = $dataArray['usergroup'];
							} else {
								$tmpArray = explode(',', $dataArray['usergroup']);
								list($tmpDataArray[$field]) = $tmpArray;
							}
							$tmpDataArray[$field] = intval($tmpDataArray[$field]);
							$extraList .= ',' . $field;
						}
						if (!empty($tmpDataArray)) {
							$res = $this->cObj->DBgetUpdate($this->theTable, $newId, $tmpDataArray, $extraList, true);
						}
					}
					$dataArray['uid'] = $newId;
					$this->updateMMRelations($dataArray);
					$this->setSaved(true);

					$newRow = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $newId);
					if (is_array($newRow)) {
						$newRow = $this->parseIncomingData($newRow);
						$this->modifyRow($newRow, true);
						// Call all afterSaveCreate hooks after the record has been created and saved
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'])) {
							foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'] as $classRef) {
								$hookObj = GeneralUtility::makeInstance($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveCreate')) {
									$hookObj->registrationProcess_afterSaveCreate(
										$this->theTable,
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
						$newRow = array();
						$this->setError('###TEMPLATE_NO_PERMISSIONS###');
						$this->setSaved(false);
						$rc = 0;
					}
				}
			break;
		}
		return $rc;
	}

	/**
	 * Update a record
	 * @param string $table The table name, should be in $GLOBALS['TCA']
	 * @param integer $uid The UID of the record from $table which we are going to update
	 * @param array $dataArr The data array where key/value pairs are fieldnames/values for the record to update.
	 * @param string $fieldList Comma list of fieldnames which are allowed to be updated. Only values from the data record for fields in this list will be updated!!
	 * @param boolean $doExec If set, the query is executed. IT'S HIGHLY RECOMMENDED TO USE THIS FLAG to execute the query directly!!!
	 * @return string The query, ready to execute unless $doExec was true in which case the return value is false.
	 * @return void
	 */
	public function updateRecord($uid, array $dataArr, $fieldlist)
	{
		return $this->cObj->DBgetUpdate($this->theTable, $uid, $dataArr, $fieldlist, true);
	}

	/**
	 * Delete a record
	 * @param integer $uid The UID of the record from $table which we are going to delete
	 * @param boolean $doExec If set, the query is executed. IT'S HIGHLY RECOMMENDED TO USE THIS FLAG to execute the query directly!!!
	 * @return string The query, ready to execute unless $doExec was true in which case the return value is false.
	 * @return void
	 */
	public function deleteRecordByUid($uid)
	{
		return $this->cObj->DBgetDelete($this->theTable, $uid, true);
	}

	/**
	 * Processes a record deletion request
	 *
	 * @return void (sets $this->saved)
	 */
	public function deleteRecord(array &$origArray, array &$dataArray)
	{
		if ($this->conf['delete']) {
			// If deleting is enabled
			$aCAuth = Authentication::aCAuth($this->parameters->getAuthCode(), $origArray, $this->conf, $this->conf['setfixed.']['DELETE.']['_FIELDLIST']);
			if ($GLOBALS['TSFE']->loginUser || $aCAuth) {
				// Must be logged in OR be authenticated by the aC code in order to delete
				// If the recUid selects a record.... (no check here)
				if (is_array($origArray)) {
					if ($aCAuth || $this->cObj->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						// Delete the record and display form, if access granted.
						// Call all beforeSaveDelete hooks BEFORE the record is deleted
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'])) {
							foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'] as $classRef) {
								$hookObj = GeneralUtility::makeInstance($classRef);
								if (method_exists($hookObj, 'registrationProcess_beforeSaveDelete')) {
									$hookObj->registrationProcess_beforeSaveDelete($origArray, $this);
								}
							}
						}
						if (!$GLOBALS['TCA'][$this->theTable]['ctrl']['delete'] || $this->conf['forceFileDelete']) {
							// If the record is being fully deleted... then remove the images or files attached.
							$this->deleteFilesFromRecord($this->getRecUid());
						}
						$res = $this->cObj->DBgetDelete($this->theTable, $this->getRecUid(), true);
						$this->deleteMMRelations($this->getRecUid(), $origArray);
						$dataArray = $origArray;
						$this->setSaved(true);
					} else {
						$this->setError('###TEMPLATE_NO_PERMISSIONS###');
					}
				}
			}
		}
	}

	/**
	 * Delete the files associated with a deleted record
	 *
	 * @param string $uid: record id
	 * @return void
	 */
	public function deleteFilesFromRecord($uid)
	{
		$rec = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $uid);
		$updateFields = array();
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $field => $fieldConf) {
			if ($fieldConf['config']['type'] === 'group' && $fieldConf['config']['internal_type'] === 'file') {
				$updateFields[$field] = '';
				$res = $this->cObj->DBgetUpdate($this->theTable, $uid, $updateFields, $field, true);
				unset($updateFields[$field]);
				$delFileArr = explode(',', $rec[$field]);
				foreach ($delFileArr as $n) {
					if ($n) {
						$fpath = PATH_site . $fieldConf['config']['uploadfolder'] . '/' . $n;
						if (@is_file($fpath)) {
							@unlink($fpath);
						}
					}
				}
			}
		}
	}

	/**
	 * Check if the value is a correct date in format yyyy-mm-dd
	 */
	public function fetchDate($value, $dateFormat) {

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
	public function evalDate($value, $dateFormat) {
		if( !$value) {
			return false;
		}
		$dateArray = $this->fetchDate($value, $dateFormat);

		if(is_numeric($dateArray['y']) && is_numeric($dateArray['m']) && is_numeric($dateArray['d'])) {
			$rc = checkdate($dateArray['m'], $dateArray['d'], $dateArray['y']);
		} else {
			$rc = false;
		}
		return $rc;
	}

	/**
	 * Update MM relations
	 *
	 * @param array $row
	 * @return void
	 */
	public function updateMMRelations(array $row)
	{
		$fieldsList = array_keys($row);
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] === 'select' && $colSettings['config']['MM']) {
				$valuesArray = $row[$colName];
				if (isset($valuesArray) && is_array($valuesArray)) {
					$res =
						$GLOBALS['TYPO3_DB']->exec_DELETEquery(
							$colSettings['config']['MM'],
							'uid_local=' . intval($row['uid'])
						);
					$insertFields = array();
					$insertFields['uid_local'] = intval($row['uid']);
					$insertFields['tablenames'] = '';
					$insertFields['sorting'] = 0;
					foreach($valuesArray as $theValue) {
						$insertFields['uid_foreign'] = intval($theValue);
						$insertFields['sorting']++;
						$res =
							$GLOBALS['TYPO3_DB']->exec_INSERTquery(
								$colSettings['config']['MM'],
								$insertFields
							);
					}
				}
			}
		}
	}

	/**
	 * Delete MM relations
	 *
	 * @return void
	 */
	public function deleteMMRelations($uid, array $row = array())
	{
		$fieldsList = array_keys($row);
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
				$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($colSettings['config']['MM'], 'uid_local=' . intval($uid));
			}
		}
	}

	/**
	 * Updates the input array from preview
	 *
	 * @param array $inputArr: new values
	 * @return array updated array
	 */
	public function modifyDataArrForFormUpdate(array $inputArr, $cmdKey)
	{
		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			foreach ($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				foreach ($listOfCommands as $k => $cmd) {
					// Parameters after each command are enclosed in brackets [..]
					$cmdParts = preg_split('/\[|\]/', $cmd);
					$theCmd = trim($cmdParts[0]);
					switch ($theCmd) {
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
			foreach ($this->conf['parseValues.'] as $theField => $theValue) {
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				foreach ($listOfCommands as $k => $cmd) {
					// Parameters after each command are enclosed in brackets [..]
					$cmdParts = preg_split('/\[|\]/', $cmd);
					$theCmd = trim($cmdParts[0]);
					switch ($theCmd) {
						case 'multiple':
						if (isset($inputArr[$theField])) {
							unset($inputArr[$theField]);
						}
						break;
						case 'checkArray':
						if ($inputArr[$theField] && !$this->parameters->isPreview()) {
							for ($a = 0; $a <= 50; $a++) {
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
		foreach ($inputArr as $field => $value) {
			if (is_array($value)) {
				$value = implode(',', $value);
			}
			$inputArr[$field] = $value;
		}
		SecuredData::secureInput($inputArr);
		return $inputArr;
	}

	/**
	 * Moves first, middle and last name into name
	 *
	 * @param array $dataArray: incoming array
	 * @param string $cmdKey: the command key
	 * @return void  done directly on $dataArray passed by reference
	 */
	public function setName(array &$dataArray, $cmdKey)
	{
		if (
			in_array('name', explode(',', $this->getFieldList()))
			&& !in_array('name', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true))
			&& in_array('first_name', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true))
			&& in_array('last_name', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true))
		) {
			// Honour Address List (tt_address) configuration settings
			$nameFormat = '';
			if ($this->theTable === 'tt_address' && ExtensionManagementUtility::isLoaded('tt_address')) {
				$settings = \TYPO3\TtAddress\Utility\SettingsUtility::getSettings();
				$nameFormat = $settings->getBackwardsCompatFormat();
			}
			if (!empty($nameFormat)) {
				$dataArray['name'] = sprintf($nameFormat, $dataArray['first_name'], $dataArray['middle_name'], $dataArray['last_name']);
			} else {
				$dataArray['name'] = trim(trim($dataArray['first_name'])
					. ((in_array('middle_name', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true)) && trim($dataArray['middle_name']) != '') ? ' ' . trim($dataArray['middle_name']) : '' )
					. ' ' . trim($dataArray['last_name']));
			}
		}
	}

	/**
	 * Moves email into username if useEmailAsUsername is set
	 *
	 * @param array $dataArray: the data array
	 * @param string $cmdKey: the command being processed
	 * @return void (done directly on array $dataArray)
	 */
	public function setUsername(array &$dataArray, $cmdKey)
	{
		if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $this->theTable === 'fe_users' && GeneralUtility::inList($this->getFieldList(), 'username') && empty($this->evalErrors['email'])) {
			$dataArray['username'] = trim($dataArray['email']);
		}
	}

	/**
	 * Sets the password
	 *
	 * @param array $dataArray: the data array
	 * @param string $cmdKey: the command being processed
	 * @return void (done directly on array $dataArray)
	 */
	public function setPassword(array &$dataArray, $cmdKey)
	{
		if ($this->theTable === 'fe_users') {
			StorageSecurity::initializeAutoLoginPassword($dataArray);
			// We generate an interim password in the case of an invitation
			if ($cmdKey === 'invite') {
				SessionData::generatePassword($this->extensionKey, $dataArray);
			}
			// If inviting or if auto login will be required on confirmation, we store an encrypted version of the password
			if ($cmdKey === 'invite' || ($cmdKey === 'create' && $this->conf['enableAutoLoginOnConfirmation'] && !$this->conf['enableAutoLoginOnCreate'])) {
				StorageSecurity::encryptPasswordForAutoLogin($dataArray);
			}
		}
	}

	/**
	 * Transforms incoming timestamps into dates
	 *
	 * @return array parsedArray
	 */
	public function parseIncomingData(array $origArray, $bUnsetZero = true)
	{
		$parsedArray = $origArray;
		if (is_array($this->conf['parseFromDBValues.'])) {
			foreach($this->conf['parseFromDBValues.'] as $theField => $theValue) {
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				if (is_array($listOfCommands)) {
					foreach ($listOfCommands as $k2 => $cmd) {
						// Enable parameters after each command enclosed in brackets [..]
						$cmdParts = preg_split('/\[|\]/', $cmd);
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'date':
							case 'adodb_date':
								if ($origArray[$theField]) {
									$parsedArray[$theField] = date($this->conf['dateFormat'], $origArray[$theField]);
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
	}

	/**
	 * Processes data before entering the database
	 * 1. Transforms outgoing dates into timestamps
	 * 2. Modifies the select fields into the count if mm tables are used.
	 * 3. Deletes de-referenced files
	 *
	 * @return parsedArray
	 */
	protected function parseOutgoingData($cmdKey, $pid, array $dataArray, array $origArray)
	{
		$parsedArray = $dataArray;

		if (is_array($this->conf['parseToDBValues.'])) {
			foreach ($this->conf['parseToDBValues.'] as $theField => $theValue) {
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				foreach($listOfCommands as $k2 => $cmd) {
					// Enable parameters after each command enclosed in brackets [..]
					$cmdParts = preg_split('/\[|\]/', $cmd);
					$theCmd = trim($cmdParts[0]);
					if (($theCmd == 'date' || $theCmd == 'adodb_date') && $dataArray[$theField]) {
						if (strlen($dataArray[$theField]) == 8) {
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
								$parsedArray[$theField] = mktime(0, 0, 0, $dateArray['m'], $dateArray['d'], $dateArray['y']);
							}
							break;
						case 'deleteUnreferencedFiles':
							$fieldConfig = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config'];
							if (is_array($fieldConfig) && $fieldConfig['type'] === 'group' && $fieldConfig['internal_type'] === 'file' && $fieldConfig['uploadfolder']) {
								$uploadPath = $fieldConfig['uploadfolder'];
								$origFiles = array();
								if (is_array($origArray[$theField])) {
									$origFiles = $origArray[$theField];
								} else if ($origArray[$theField]) {
									$origFiles = GeneralUtility::trimExplode(',', $origArray[$theField], true);
								}
								$updatedFiles = array();
								if (is_array($dataArray[$theField])) {
									$updatedFiles = $dataArray[$theField];
								} else if ($dataArray[$theField]) {
									$updatedFiles = GeneralUtility::trimExplode(',', $dataArray[$theField], true);
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

		$fieldsList = array_keys($parsedArray);
		// Invoke the hooks for additional parsing
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (isset($parsedArray[$colName]) || isset($origArray[$colName])) {
				$foreignTable = $GLOBALS['TCA'][$this->theTable]['columns'][$colName]['config']['foreign_table'] ?: '';
				$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable][$colName]) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable][$colName] : array();
				foreach ($hookClassArray as $classRef) {
					$hookObject = GeneralUtility::makeInstance($classRef);
					if (is_object($hookObject) && method_exists($hookObject, 'parseOutgoingData')) {
						$hookObject->parseOutgoingData($this->theTable, $colName, $foreignTable, $cmdKey, $pid, $this->conf, $dataArray, $origArray, $parsedArray);
					}
				}
			}
		}
		// Update the MM relation count field
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (isset($parsedArray[$colName])) {
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
	}

	/**
	* Checks the error value from the upload $_FILES array.
	*
	* @param string  $error_code: the error code
	* @return boolean  true if ok
	*/
	public function evalFileError ($error_code) {
		$rc = false;
		if ($error_code == "0") {
			$rc = true;
			// File upload okay
		} elseif ($error_code == '1') {
			$rc = false; // filesize exceeds upload_max_filesize in php.ini
		} elseif ($error_code == '3') {
			return false; // The file was uploaded partially
		} elseif ($error_code == '4') {
			$rc = true;
			// No file was uploaded
		} else {
			$rc = true;
		}

		return $rc;
	}	// evalFileError


	public function getInError () {
		return $this->inError;
	}


	/**
	 * Sets the index $theField of the incoming data array to empty value depending on type of $theField
	 * as defined in the TCA for $theTable
	 *
	 * @param string $theTable: the name of the table
	 * @param string $theField: the name of the field
	 * @param array $dataArray: the incoming data array
	 * @return void
	 */
	protected function setEmptyIfAbsent($theField, array &$dataArray) {
		if (!isset($dataArray[$theField])) {
			$fieldConfig = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config'];
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

	/**
	 * Adds the fields coming from other tables via MM tables
	 *
	 * @param array $dataArray: the record array
	 * @return array the modified data array
	 */
	public function modifyTcaMMfields(array $dataArray, &$modArray)
	{
		$rcArray = $dataArray;
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			$colConfig = $colSettings['config'];
			switch ($colConfig['type']) {
				case 'select':
					if ($colConfig['MM'] && $colConfig['foreign_table']) {
						$where = 'uid_local = ' . $dataArray['uid'];
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid_foreign', $colConfig['MM'], $where);
						$valueArray = array();
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$valueArray[] = $row['uid_foreign'];
						}
						$rcArray[$colName] = implode(',', $valueArray);
						$modArray[$colName] = $rcArray[$colName];
					}
					break;
			}
		}
		return $rcArray;
	}

	/**
	 * Modifies the incoming data row
	 * Adds checkboxes which have been unset. This means that no field will be present for them.
	 * Fetches the former values of select boxes
	 *
	 * @param array $dataArray: the input data array will be changed
	 * @return void
	 */
	public function modifyRow(array &$dataArray, $bColumnIsCount = true)
	{
		$fieldsList = array_keys($dataArray);
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			$colConfig = $colSettings['config'];
			if (!$colConfig || !is_array($colConfig)) {
				continue;
			}
			if ($colConfig['maxitems'] > 1) {
				$bMultipleValues = true;
			} else {
				$bMultipleValues = false;
			}
			switch ($colConfig['type']) {
				case 'group':
					$bMultipleValues = true;
					break;
				case 'select':
					$value = $dataArray[$colName];
					// checkbox from which nothing has been selected
					if ($value == 'Array') {
						$dataArray[$colName] = $value = '';
					}
					if (in_array($colName, $fieldsList) && $colConfig['MM'] && isset($value)) {
						if ($value == '' || is_array($value)) {
							// the values from the mm table are already available as an array
						} else if ($bColumnIsCount) {
							$valuesArray = array();
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
								'uid_local,uid_foreign,sorting',
								$colConfig['MM'],
								'uid_local=' . intval($dataArray['uid']),
								'',
								'sorting'
							);
							while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								$valuesArray[] = $row['uid_foreign'];
							}
							$dataArray[$colName] = $valuesArray;
						} else {
							$dataArray[$colName] = GeneralUtility::trimExplode (',', $value, true);
						}
					}
					break;
				case 'check':
					if (is_array($colConfig['items'])) {
						$value = $dataArray[$colName];
						if(is_array($value)) {
							$dataArray[$colName] = 0;
							// Combine values to one hexidecimal number
							foreach ($value AS $dec) {
								$dataArray[$colName] |= (1 << $dec);
							}
						}
					} else if (isset($dataArray[$colName])) {
						if ($dataArray[$colName] != '0') {
							$dataArray[$colName] = '1';
						} else {
							$dataArray[$colName] = '0';
						}
					} else {
						$dataArray[$colName] = '0';
					}
					break;
				default:
					break;
			}
			if ($bMultipleValues) {
				$value = $dataArray[$colName];
				if (isset($value) && !is_array($value)) {
					$dataArray[$colName] = GeneralUtility::trimExplode (',', $value, true);
				}
			}
		}
		if ($this->staticInfoObj !== null && $dataArray['static_info_country']) {
			// empty zone if it does not fit to the provided country
			$zoneArray = $this->staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);
			if (!isset($zoneArray[$dataArray['zone']])) {
				$dataArray['zone'] = '';
			}
		}
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
}