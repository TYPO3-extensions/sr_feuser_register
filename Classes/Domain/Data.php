<?php
namespace SJBR\SrFeuserRegister\Domain;

/*
 *  Copyright notice
 *
 *  (c) 2007-2018 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use SJBR\SrFeuserRegister\View\AbstractView;
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
	 * The usergroup hook object
	 *
	 */
	protected $userGroupObj = null;

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
		if (ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$this->staticInfoObj = GeneralUtility::makeInstance('SJBR\\StaticInfoTables\\PiBaseApi');
			if ($this->staticInfoObj->needsInit()) {
				$this->staticInfoObj->init();
			}
		}
		// Usergroup hook object
		if ($this->theTable === 'fe_users') {
			$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable]['usergroup']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable]['usergroup'] : array();
			foreach ($hookClassArray as $classRef) {
				$this->userGroupObj = GeneralUtility::makeInstance($classRef);
				if (is_object($this->userGroupObj)) {
					break;
				}
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
		if ($this->parameters->isTokenValid()) {
			$fe = GeneralUtility::_POST('FE');
			if (isset($fe) && is_array($fe)) {
				$feDataArray = $fe[$this->theTable];
				SecuredData::secureInput($feDataArray, false);
				$this->modifyRow($feDataArray, false);
				SessionData::securePassword($this->extensionKey, $feDataArray);
				unset($feDataArray['password_again']);
				$this->setDataArray($feDataArray);
			}
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
	 * @param string $param: parameter for the error message
	 * @param string $extensionName: name of the extension
	 * @return string the error message to be displayed
	 */
	protected function getFailureText($theField, $theRule, $label, $param = '', $extensionName)
	{
		$failureLabel = '';
		if ($theRule) {
			$failureLabel = LocalizationUtility::translate('evalErrors_' . $theRule . '_' . $theField, $extensionName);
			$failureLabel = $failureLabel ?: LocalizationUtility::translate('evalErrors_' . $theRule, $extensionName);
		}
		if (!$failureLabel) {
			$failureLabel = LocalizationUtility::translate($label, $extensionName);
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
	 * @param string $mode: the current mode (normal or preview)	 
	 * @return void on return, the parameters failure will contain the list of fields which were not ok
	 */
	public function evalValues(array &$dataArray, array $origArray, $markerObj, $cmdKey, $mode = AbstractView::MODE_NORMAL) {
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
			$value = $dataArray[$theField];
			if ($theField === 'usergroup' && is_object($this->userGroupObj) && is_array($value)) {
				$value = $this->userGroupObj->restrictToSelectableValues($value, $this->conf, $cmdKey);
			}
			$isMissing = empty($value) && !($theField === 'gender' && $value == '0');
			if ($isMissing) {
				$failureArray[] = $theField;
				$this->missing[$theField] = true;
			}
		}

		$pid = $dataArray['pid'];

		// Evaluate: This evaluates for more advanced things than "required" does.
		// But it returns the same error code, so you must let the required-message, if further evaluation has failed!
		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			$cmd = $this->parameters->getCmd();
			if ($cmd === 'edit' || $cmdKey === 'edit') {
				if ((int)$pid) {
					// This may be tricked if the input has the pid-field set but the edit-field list does NOT allow the pid to be edited. Then the pid may be false.
					$recordTestPid = (int)$pid;
				} else {
					$tempRecArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $dataArray['uid']);
					$recordTestPid = (int) $tempRecArr['pid'];
				}
			} else {
				$thePid = $this->parameters->getPid();
				$recordTestPid = $thePid ? $thePid : $pid;
			}
			foreach ($this->conf[$cmdKey.'.']['evalValues.'] as $theField => $theValue) {
				$this->evalErrors[$theField] = array();
				$failureMsg[$theField] = array();
				$listOfCommands = GeneralUtility::trimExplode(',', $theValue, true);
				// Unset the incoming value is empty and unsetEmpty is specified
				if (array_search('unsetEmpty', $listOfCommands) !== false) {
					if (isset($dataArray[$theField]) && empty($dataArray[$theField]) && trim($dataArray[$theField]) !== '0') {
						unset($dataArray[$theField]);
					}
					if (isset($dataArray[$theField . '_again']) && empty($dataArray[$theField . '_again']) && trim($dataArray[$theField . '_again']) !== '0') {
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
								if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
									$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
										->getQueryBuilderForTable($this->theTable);
									$queryBuilder
											->getRestrictions()
											->removeAll();
									if ($theCmd === 'uniqueLocal' || $theCmd === 'uniqueGlobal') {
										$queryBuilder
											->getRestrictions()
											->add(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction::class));
									}
									$queryBuilder
										->select('uid', $theField)
										->from($this->theTable)
										->where(
											$queryBuilder->expr()->eq($theField, $queryBuilder->createNamedParameter($dataArray[$theField]), \PDO::PARAM_STR)
										)
										->setMaxResults(1);
									if ($dataArray['uid']) {
										$queryBuilder
											->andWhere(
												$queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter((int)$dataArray['uid']), \PDO::PARAM_INT)
											);
									}
									if ($theCmd === 'uniqueLocal' || $theCmd === 'uniqueDeletedLocal') {
										$queryBuilder
											->andWhere(
												$queryBuilder->expr()->in('pid', GeneralUtility::intExplode(',', $recordTestPid, true))
											);
									}
									$DBrows = $queryBuilder
										->execute()
										->fetchAll();
								} else {
									// TYPO3 CMS 7 LTS
									$where = $theField . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($dataArray[$theField], $this->theTable);
									if ($dataArray['uid']) {
										$where .= ' AND uid != ' . (int)$dataArray['uid'];
									}
									if ($theCmd === 'uniqueLocal' || $theCmd === 'uniqueGlobal') {
										$where .= $GLOBALS['TSFE']->sys_page->deleteClause($this->theTable);
									}
									if ($theCmd === 'uniqueLocal' || $theCmd === 'uniqueDeletedLocal') {
										$where .= ' AND pid IN (' . $recordTestPid.')';
									}
									$DBrows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,' . $theField, $this->theTable, $where, '', '', '1');
								}
								if (
									!is_array($dataArray[$theField]) &&
									trim($dataArray[$theField]) != '' &&
									isset($DBrows) &&
									is_array($DBrows) &&
									isset($DBrows[0]) &&
									is_array($DBrows[0])
								) {
									// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_existed_already', '', $this->extensionName);
								}
							break;
							case 'twice':
								$fieldValue = strval($dataArray[$theField]);
								$fieldAgainValue = strval($dataArray[$theField . '_again']);
								if (strcmp($fieldValue, $fieldAgainValue)) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_same_twice', '', $this->extensionName);
								}
							break;
							case 'email':
								if (!is_array($dataArray[$theField]) && trim($dataArray[$theField]) && !GeneralUtility::validEmail($dataArray[$theField])) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_valid_email', '', $this->extensionName);
								}
							break;
							case 'required':
								$value = $dataArray[$theField];
								if ($theField === 'usergroup' && is_object($this->userGroupObj) && is_array($value)) {
									$value = $this->userGroupObj->restrictToSelectableValues($value, $this->conf, $cmdKey);
								}
								if (empty($value) && $dataArray[$theField] !== '0') {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_required', '', $this->extensionName);
								}
							break;
							case 'atLeast':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) < $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_atleast_characters', $chars, $this->extensionName);
								}
							break;
							case 'atMost':
								$chars = intval($cmdParts[1]);
								if (!is_array($dataArray[$theField]) && strlen($dataArray[$theField]) > $chars) {
									$failureArray[] = $theField;
									$this->inError[$theField] = true;
									$this->evalErrors[$theField][] = $theCmd;
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_atmost_characters', $chars, $this->extensionName);
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
										$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_list', $pid_list, $this->extensionName);
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
										$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_url', '', $this->extensionName);
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
									$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_unvalid_date', '', $this->extensionName);
								}
							break;
							case 'preg':
								if (!is_array($dataArray[$theField]) && !empty($dataArray[$theField]) && $dataArray[$theField] !== '0') {
									$pattern = str_replace('preg[', '', $cmd);
									$pattern = substr($pattern, 0, strlen($pattern) - 1);
									$matches = array();
									$test = preg_match($pattern, $dataArray[$theField], $matches);
									if (count($matches) === 0) {
										$failureArray[] = $theField;
										$this->inError[$theField] = true;
										$this->evalErrors[$theField][] = $theCmd;
										$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_' . $theCmd, $cmd, $this->extensionName);
									}
								}
								break;
							case 'hook':
							default:
								$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
								// The captcha cannot be checked twice
								if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha']) && ($mode == AbstractView::MODE_PREVIEW || !$this->conf[$cmdKey . '.']['preview'])) {
										$hookClassArray = array_merge($hookClassArray, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha']);
								}
								foreach ($hookClassArray as $classRef) {
									$hookObj = GeneralUtility::makeInstance($classRef);
									if (is_object($hookObj) && method_exists($hookObj, 'evalValues')) {
										$errorField = $hookObj->evalValues($this->theTable, $dataArray, $theField, $cmdKey, $cmdParts, $this->extensionName);
										if (is_array($errorField)) {
											if (!empty($errorField)) {
												$failureArray[] = $theField;
												$this->evalErrors[$theField][] = $theCmd;
												$this->inError[$theField] = true;
												$failureMsg[$theField] = array_merge($failureMsg[$theField], $errorField);
											}
										} else if ($errorField !== '') {
											$failureArray[] = $errorField;
											$this->evalErrors[$theField][] = $theCmd;
											$this->inError[$theField] = true;
											$failureMsg[$theField][] = $this->getFailureText($theField, $theCmd, 'evalErrors_' . $theCmd, $cmd, $this->extensionName);
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
			$zoneArray = $this->staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);
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
				$fieldConfig = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config'];
				if (isset($dataArray[$theField]) || isset($origArray[$theField]) || $fieldConfig['internal_type'] === 'file' || $fieldConfig['foreign_table'] === 'sys_file_reference') {
					foreach ($listOfCommands as $cmd) {
						// Enable parameters after each command enclosed in brackets [..].
						$cmdParts = preg_split('/\[|\]/', $cmd);
						$theCmd = trim($cmdParts[0]);
						$bValueAssigned = true;
						if (($theField === 'password' || $theField === 'password_again') && !isset($dataArray[$theField])) {
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
								$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
								foreach ($hookClassArray as $classRef) {
									$hookObj = GeneralUtility::makeInstance($classRef);
									if (is_object($hookObj) && method_exists($hookObj, 'parseValues')) {
										$dataValue = $hookObj->parseValues($this->theTable, $dataArray, $dataValue, $theField, $cmdKey, $cmdParts);
									}
								}
								break;
							case 'multiple':
								$fieldDataArray = array();
								if (!empty($dataValue)) {
									if (is_array($dataValue)) {
										$fieldDataArray = $dataValue;
									} else if (is_string($dataValue)) {
										$fieldDataArray = GeneralUtility::trimExplode(',', $dataValue, true);
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
				$theUid = (int) $origArray['uid'];
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

					if ($aCAuth || $this->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						$outGoingData = $this->parseOutgoingData($cmdKey, $pid, $dataArray, $origArray);
						// Do not set the outgoing password if the incoming password was unset
 						if ($this->theTable === 'fe_users' && !empty($dataArray['password'])) {
							$outGoingData['password'] = SessionData::readPasswordForStorage($this->extensionKey);
 						}
						$newFieldList = implode (',', $newFieldArray);
						if (isset($GLOBALS['TCA'][$this->theTable]['ctrl']['token'])) {
							// Save token in record
							$outGoingData['token'] = $token;
							// Could be set conditional to adminReview or user confirm
							$newFieldList .= ',token';
						}
						$res = $this->updateRecord($theUid, $outGoingData, $newFieldList);
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
					// Allow to override values for fields that are not on the form
					$newFieldArray = array_merge(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true), array_keys($this->conf[$cmdKey . '.']['overrideValues.']));
					$newFieldArray = array_intersect(explode(',', $this->getFieldList()), $newFieldArray);
					$newFieldArray = array_unique(array_merge($newFieldArray, explode(',', $this->getAdminFieldList())));
					$newFieldList = implode(',', $newFieldArray);
					$parsedArray = $this->parseOutgoingData($cmdKey, $pid, $dataArray, $origArray);
					if ($this->theTable === 'fe_users') {
						$parsedArray['password'] = SessionData::readPasswordForStorage($this->extensionKey);
					}
					if (isset($GLOBALS['TCA'][$this->theTable]['ctrl']['token'])) {
						$parsedArray['token'] = $token;
						$newFieldList  .= ',token';
					}
					$newId = $this->insertRecord((int)$pid, $parsedArray, $newFieldList);
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
							$res = $this->updateRecord($newId, $tmpDataArray, $extraList);
						}
					}
					$dataArray['uid'] = $newId;
					$this->updateMMRelations($dataArray);
					$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
					foreach ($hookClassArray as $classRef) {
						$hookObj = GeneralUtility::makeInstance($classRef);
						if (is_object($hookObj) && method_exists($hookObj, 'afterSave')) {
							$dataArray = $hookObj->afterSave($this->theTable, $cmdKey, $dataArray);
						}
					}
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
     * Checks if a frontend user is allowed to edit a certain record
     *
     * @param string $table The table name, found in $GLOBALS['TCA']
     * @param array $row The record data array for the record in question
     * @param array $feUserRow The array of the fe_user which is evaluated, typ. $GLOBALS['TSFE']->fe_user->user
     * @param string $allowedGroups Commalist of the only fe_groups uids which may edit the record. If not set, then the usergroup field of the fe_user is used.
     * @param bool|int $feEditSelf TRUE, if the fe_user may edit his own fe_user record.
     * @return bool
     */
    public function DBmayFEUserEdit($table, $row, $feUserRow, $allowedGroups = '', $feEditSelf = 0)
    {
        if ($allowedGroups) {
        	$groupList = implode(
        		',',
        		array_intersect(
        			GeneralUtility::trimExplode(',', $feUserRow['usergroup'], true),
        			GeneralUtility::trimExplode(',', $allowedGroups, true)
                )
            );
        } else {
            $groupList = $feUserRow['usergroup'];
        }
        $ok = 0;
        // Points to the field that allows further editing from frontend if not set. If set the record is locked.
        if (!$GLOBALS['TCA'][$table]['ctrl']['fe_admin_lock'] || !$row[$GLOBALS['TCA'][$table]['ctrl']['fe_admin_lock']]) {
            // Points to the field (int) that holds the fe_users-id of the creator fe_user
            if ($GLOBALS['TCA'][$table]['ctrl']['fe_cruser_id']) {
                $rowFEUser = (int)$row[$GLOBALS['TCA'][$table]['ctrl']['fe_cruser_id']];
                if ($rowFEUser && $rowFEUser === (int)$feUserRow['uid']) {
                    $ok = 1;
                }
            }
            // If $feEditSelf is set, fe_users may always edit them selves...
            if ($feEditSelf && $table === 'fe_users' && (int)$feUserRow['uid'] === (int)$row['uid']) {
                $ok = 1;
            }
            // Points to the field (int) that holds the fe_group-id of the creator fe_user's first group
            if ($GLOBALS['TCA'][$table]['ctrl']['fe_crgroup_id']) {
                $rowFEUser = (int)$row[$GLOBALS['TCA'][$table]['ctrl']['fe_crgroup_id']];
                if ($rowFEUser) {
                    if (GeneralUtility::inList($groupList, $rowFEUser)) {
                        $ok = 1;
                    }
                }
            }
        }
        return $ok;
    }

	/**
	 * Insert a record
	 * @param int $pid The PID value for the record to insert
	 * @param array $dataArr The data array where key/value pairs are fieldnames/values for the record to insert
	 * @param string $fieldList Comma list of fieldnames which are allowed to be set. Only values from the data record for fields in this list will be set!!
	 * @return string The uid of the inserted record
	 * @return void
	 */
	protected function insertRecord($pid, $dataArr, $fieldList)
	{
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			$extraList = 'pid';
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp']) {
				$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp'];
				$dataArr[$field] = $GLOBALS['EXEC_TIME'];
				$extraList .= ',' . $field;
			}
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['crdate']) {
				$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['crdate'];
				$dataArr[$field] = $GLOBALS['EXEC_TIME'];
				$extraList .= ',' . $field;
			}
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['cruser_id']) {
				$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['cruser_id'];
				$dataArr[$field] = 0;
				$extraList .= ',' . $field;
			}
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id']) {
				$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id'];
				$dataArr[$field] = (int)$this->cObj->getTypoScriptFrontendController()->fe_user->user['uid'];
				$extraList .= ',' . $field;
			}
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id']) {
				$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id'];
				list($dataArr[$field]) = explode(',', $this->cObj->getTypoScriptFrontendController()->fe_user->user['usergroup']);
				$dataArr[$field] = (int)$dataArr[$field];
				$extraList .= ',' . $field;
			}
			unset($dataArr['uid']);
			if ($pid >= 0) {
				$dataArr['pid'] = $pid;
			}
			$fieldList = implode(',', GeneralUtility::trimExplode(',', $fieldList . ',' . $extraList, true));
			$insertFields = [];
			foreach ($dataArr as $f => $v) {
				if (GeneralUtility::inList($fieldList, $f)) {
					$insertFields[$f] = $v;
				}
			}
			$connection = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)->getConnectionForTable($this->theTable);
			$queryBuilder = $connection->createQueryBuilder()
				->insert($this->theTable)
				->values($insertFields)
				->execute();
			return $connection->lastInsertId($this->theTable);
		} else {
			// TYPO3 CMS 7 LTS
			$this->cObj->DBgetInsert($this->theTable, (int)$pid, $dataArr, $fieldList, true);
			return $GLOBALS['TYPO3_DB']->sql_insert_id();
		}
	}

	/**
	 * Update a record
	 *
	 * @param integer $uid The UID of the record from $table which we are going to update
	 * @param array $dataArr The data array where key/value pairs are fieldnames/values for the record to update.
	 * @param string $fieldList Comma list of fieldnames which are allowed to be updated. Only values from the data record for fields in this list will be updated!!
	 * @return bool false
	 */
	public function updateRecord($uid, array $dataArr, $fieldList)
	{
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			if ($uid) {
				$fields = GeneralUtility::trimExplode(',', $fieldList, true);
				$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)->getQueryBuilderForTable($this->theTable);
				$queryBuilder
					->update($this->theTable)
					->where(
						$queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$uid, \PDO::PARAM_INT))
					);
				foreach ($dataArr as $field => $value) {
					if (in_array($field, $fields)) {
						$queryBuilder->set($field, $value);
					}
				}
				if ($GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp']) {
					$queryBuilder->set($GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp'], (int)$GLOBALS['EXEC_TIME']);
				}
				$queryBuilder->execute();
			}
			return false;
		} else {
			// TYPO3 CMS 7 LTS
			return $this->cObj->DBgetUpdate($this->theTable, $uid, $dataArr, $fieldList, true);
		}
	}

	/**
	 * Delete a record
	 *
	 * @param integer $uid The UID of the record from $table which we are going to delete
	 * @return string false.
	 * @return void
	 */
	public function deleteRecordByUid($uid)
	{
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			$uid = (int)$uid;
			if (!$uid) {
				return false;
			}
			$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
			if ($GLOBALS['TCA'][$this->theTable]['ctrl']['delete']) {
				$queryBuilder = $connectionPool->getQueryBuilderForTable($this->theTable);
				$queryBuilder
					->update($this->theTable)
					->where(
						$queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
					)
					->set($GLOBALS['TCA'][$this->theTable]['ctrl']['delete'], 1);
				if ($GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp']) {
					$queryBuilder->set($GLOBALS['TCA'][$this->theTable]['ctrl']['tstamp'], (int)$GLOBALS['EXEC_TIME']);
				}
				$queryBuilder->execute();
            } else {
            	$connectionPool->getConnectionForTable($this->theTable)
					->delete(
						$this->theTable,
						['uid' => $uid]
					);
			}
			return false;
		} else {
			// TYPO3 CMS 7 LTS
			return $this->cObj->DBgetDelete($this->theTable, $uid, true);
		}
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
					if ($aCAuth || $this->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
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
						$res = $this->deleteRecordByUid($this->getRecUid());
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
				$res = $this->updateRecord($uid, $updateFields, $field);
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
		$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
		foreach ($hookClassArray as $classRef) {
			$hookObj = GeneralUtility::makeInstance($classRef);
			if (is_object($hookObj) && method_exists($hookObj, 'deleteFileReferences')) {
				$dataArray = $hookObj->deleteFileReferences($this->theTable, $uid);
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
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
		}
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] === 'select' && $colSettings['config']['MM']) {
				$valuesArray = $row[$colName];
				$side = isset($colSettings['config']['MM_opposite_field']) ? 'foreign' : 'local';
				$oppositeSide = ($side === 'foreign') ?  'local' : 'foreign';
				if (isset($valuesArray) && is_array($valuesArray)) {
					if (is_object($connectionPool)) {
						$connection = $connectionPool->getConnectionForTable($colSettings['config']['MM']);
						$connection->delete(
							$colSettings['config']['MM'],
							['uid_' . $side => (int)$row['uid']]
						);
						if (isset($colSettings['config']['MM_match_fields'])) {
							$tablenames = $colSettings['config']['MM_match_fields']['tablenames'];
							$fieldname = $colSettings['config']['MM_match_fields']['fieldname'];
						}
						$insertFields = [
							'uid_' . $side => (int)$row['uid'],
							'sorting' . ($side === 'foreign' ? '_' . $side : '') => 0
						];
						if (isset($tablenames)) {
							$insertFields['tablenames'] = $tablenames ?: '';
						}
						if (isset($fieldname)) {
							$insertFields['fieldname'] = $fieldname ?: '';
						}
						foreach ($valuesArray as $theValue) {
							$insertFields['uid_' . $oppositeSide] = (int)$theValue;
							$insertFields['sorting' . ($side === 'foreign' ? '_' . $side : '')]++;
							$connection->insert(
								$colSettings['config']['MM'],
								$insertFields
							);
						}
					} else {
						// TYPO3 CMS 7 LTS
						$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery(
							$colSettings['config']['MM'],
							'uid_' . $side . '=' . (int)$row['uid']
						);
						if (isset($colSettings['config']['MM_match_fields'])) {
							$tablenames = $colSettings['config']['MM_match_fields']['tablenames'];
							$fieldname = $colSettings['config']['MM_match_fields']['fieldname'];
						}
						$insertFields = [
							'uid_' . $side => (int)$row['uid'],
							'sorting' . ($side === 'foreign' ? '_' . $side : '') => 0
						];
						if (isset($tablenames)) {
							$insertFields['tablenames'] = $tablenames ?: '';
						}
						if (isset($fieldname)) {
							$insertFields['fieldname'] = $fieldname ?: '';
						}
						foreach ($valuesArray as $theValue) {
							$insertFields['uid_' . $oppositeSide] = (int)$theValue;
							$insertFields['sorting' . ($side === 'foreign' ? '_' . $side : '')]++;
							$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
								$colSettings['config']['MM'],
								$insertFields
							);
						}
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
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
		}
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (in_array($colName, $fieldsList) && $colSettings['config']['type'] === 'select' && $colSettings['config']['MM']) {
				$side = isset($colSettings['config']['MM_opposite_field']) ? 'foreign' : 'local';
				if (is_object($connectionPool)) {
					$connectionPool
						->getConnectionForTable($colSettings['config']['MM'])
						->delete(
							$colSettings['config']['MM'],
							['uid_' . $side => (int)$uid]
						);
				} else {
					// TYPO3 CMS 7 LTS
					$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery(
						$colSettings['config']['MM'],
						'uid_' . $side . '=' . (int)$uid
					);
				}
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

	public function getInError()
	{
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
		if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
			$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
		}
		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			$colConfig = $colSettings['config'];
			switch ($colConfig['type']) {
				case 'select':
					if ($colConfig['MM'] && $colConfig['foreign_table']) {
						$side = isset($colConfig['MM_opposite_field']) ? 'foreign' : 'local';
						$oppositeSide = ($side === 'foreign') ?  'local' : 'foreign';
						$valueArray = [];
						if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
							$queryBuilder = $connectionPool->getQueryBuilderForTable($colConfig['MM']);
							$query = $queryBuilder
								->select('uid_' . $oppositeSide)
								->from($colConfig['MM'])
								->where(
									$queryBuilder->expr()->eq('uid_' . $side, $queryBuilder->createNamedParameter((int)$dataArray['uid'], \PDO::PARAM_INT))
								)
								->execute();
							while ($row = $query->fetch()) {
								$valueArray[] = $row['uid_' . $oppositeSide];
							}
						} else {
							// TYPO3 CMS 7 LTS
							$where = 'uid_' . $side . ' = ' . (int)$dataArray['uid'];
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid_' . $oppositeSide, $colConfig['MM'], $where);
							while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								$valueArray[] = $row['uid_' . $oppositeSide];
							}
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
				case 'inline':
					if ($colConfig['foreign_table'] === 'sys_file_reference' && isset($dataArray[$colName]) && !is_array($dataArray[$colName])) {
						$dataArray[$colName] = unserialize($dataArray[$colName]);
					}
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
							$side = isset($colConfig['MM_opposite_field']) ? 'foreign' : 'local';
							$oppositeSide = ($side === 'foreign') ?  'local' : 'foreign';
							$valuesArray = [];
							if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
								$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
									->getQueryBuilderForTable($colConfig['MM']);
								$queryBuilder->getRestrictions()->removeAll();
								$query = $queryBuilder
									->select('uid_local','uid_foreign', 'sorting' . ($side === 'foreign' ? '_' . $side : ''))
									->from($colConfig['MM'])
									->where(
										$queryBuilder->expr()->eq('uid_' . $side, $queryBuilder->createNamedParameter((int)$dataArray['uid'], \PDO::PARAM_INT))
									)
									->orderBy('sorting' . ($side === 'foreign' ? '_' . $side : ''))
									->execute();
								while ($row = $query->fetch()) {
									$valuesArray[] = $row['uid_' . $oppositeSide];
								}
							} else {
								// TYPO3 CMS 7 LTS
								$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
									'uid_local,uid_foreign,sorting' . ($side === 'foreign' ? '_' . $side : ''),
									$colConfig['MM'],
									'uid_' . $side . '=' . (int)$dataArray['uid'],
									'',
									'sorting' . ($side === 'foreign' ? '_' . $side : '')
								);
								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$valuesArray[] = $row['uid_' . $oppositeSide];
								}
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