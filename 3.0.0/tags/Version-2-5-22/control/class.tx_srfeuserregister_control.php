<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca)>
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
 * display functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2007@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_control {
	var $langObj;
	var $conf = array();
	var $display;
	var $data;
	var $marker;
	var $cObj;
	var $auth;
	var $email;
	var $tca;
	var $requiredArray; // List of required fields
	var $controlData;
	var $setfixedObj;
	var $noLoginCommands = array('','create','invite','setfixed','infomail','login');


	function init (&$langObj, &$controlData, &$display, &$marker, &$email, &$tca, &$setfixedObj)	{
		global $TSFE;

		$this->langObj = &$langObj;
		$confObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_conf');

		$this->conf = &$confObj->getConf();
		$this->display = &$display;
		$this->marker = &$marker;
		$this->cObj = &$langObj->cObj;
		$this->email = &$email;
		$this->tca = &$tca;
		$this->controlData = &$controlData;
		$this->setfixedObj = &$setfixedObj;

		$extKey = $this->controlData->getExtKey();
		$cmd = $this->controlData->getCmd();

		if ($cmd=='')	{

			if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['useFlexforms']) {
					// Static Methods for Extensions for flexform functions
				require_once(PATH_BE_div2007.'class.tx_div2007_alpha.php');
					// check the flexform
				$this->langObj->pi_initPIflexForm();
				$cmd = tx_div2007_alpha::getSetupOrFFvalue_fh001(
					$this->langObj,
					'',
					'',
					$this->conf['defaultCODE'],
					$this->cObj->data['pi_flexform'],
					'display_mode',
					$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['useFlexforms']
				);
			} else {
				if (!$cmd)	{
					$cmd = $this->cObj->data['select_key'];
				}
				$cmd = ($cmd ? $cmd : $this->conf['defaultCODE']);
			}
			$cmd = $this->cObj->caseshift($cmd,'lower');
		}
		$this->controlData->setCmd($cmd);
	}

	function init2 ($theTable, &$controlData, &$data, &$adminFieldList)	{
		global $TSFE;

		$this->data = &$data;

		$confObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_conf');
		$tablesObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');
		$origArray = array();
		$extKey = $controlData->getExtKey();
		$cmd = $controlData->getCmd();
		$dataArray = $this->data->getDataArray();
		$feUserdata = $this->controlData->getFeUserData();

		$theUid = ($dataArray['uid'] ? $dataArray['uid'] : ($feUserdata['rU'] ? $feUserdata['rU'] : (!in_array($cmd,$this->noLoginCommands) ? $TSFE->fe_user->user['uid'] : 0 )));

		if ($theUid)	{
			$this->data->setRecUid($theUid);
			$newOrigArray = $TSFE->sys_page->getRawRecord($theTable, $theUid);

			if (isset($newOrigArray) && is_array($newOrigArray))	{
				$this->tca->modifyRow($newOrigArray, TRUE);
				$origArray = $newOrigArray;
			}
		}

		if ($cmd == 'edit' || $cmd == 'invite') {
			$cmdKey = $cmd;
		} else {
			if (
				!$cmd &&
				(
					($this->controlData->getTable() == 'fe_users' && $TSFE->loginUser) ||
					(count($origArray))
				)
			)	{
				$cmdKey = 'edit';
			} else {
				$cmdKey = 'create';
			}
		}
		$controlData->setCmdKey($cmdKey);

		if (!$theUid)	{
			if (!count($dataArray))	{
				$dataArray = $this->data->defaultValues($cmdKey);
				$this->data->setDataArray($dataArray);
			}
		}
		$this->data->setOrigArray($origArray);

			// Setting the list of fields allowed for editing and creation.
		$tableTCA = &$this->tca->getTCA();
		$fieldlist = implode(',', t3lib_div::trimExplode(',', $tableTCA['feInterface']['fe_admin_fieldList'], 1));
		$this->data->setFieldList($fieldlist);

		if (trim($this->conf['addAdminFieldList'])) {
			$adminFieldList .= ',' . trim($this->conf['addAdminFieldList']);
		}
		$adminFieldList = implode(',', array_intersect( explode(',', $fieldlist), t3lib_div::trimExplode(',', $adminFieldList, 1)));
		$this->data->setAdminFieldList($adminFieldList);

			// Fetching the template file
		$this->data->setTemplateCode($this->cObj->fileResource($this->conf['templateFile']));

		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('module_sys_dmail_category')));
			$this->conf[$cmdKey.'.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['required'], 1), array('module_sys_dmail_category')));
		}

		$fieldConfArray = array('fields', 'required');
		foreach ($fieldConfArray as $k => $v)	{
			// make it ready for t3lib_div::inList which does not yet allow blanks
			$this->conf[$cmdKey.'.'][$v] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.'][$v])));
		}

		$theTable = $controlData->getTable();
		if ($theTable == 'fe_users') {
			$this->conf[$cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'] . ',username', 1)));
			$this->conf[$cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['required'] . ',username', 1)));
			if ($this->conf[$cmdKey.'.']['generateUsername']) {
				$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('username')));
			}

			if ($this->conf[$cmdKey.'.']['generatePassword'] && $cmdKey != 'edit') {
				$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('password')));
			}

			if ($this->conf[$cmdKey.'.']['useEmailAsUsername']) {
				$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('username')));
				if ($cmdKey == 'create' || $cmdKey == 'invite') {
					$this->conf[$cmdKey.'.']['fields'] = implode(',', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'] . ',email', 1));
					$this->conf[$cmdKey.'.']['required'] = implode(',', t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['required'] . ',email', 1));
				}
				if ($cmdKey == 'edit' && $controlData->getSetfixedEnabled()) {
					$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('email')));
				}
			}
			$userGroupObj = &$addressObj->getFieldObj ('usergroup');
			if (is_object($userGroupObj))	{
				$userGroupObj->modifyConf($this->conf, $cmdKey);
			}

			if ($cmdKey == 'invite') {
				if ($controlData->getUseMd5Password()) {
					$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('password')));
					if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
						unset($this->conf[$cmdKey.'.']['evalValues.']['password']);
					}
				}
				if ($this->conf['enableAdminReview']) {
					if ($controlData->getSetfixedEnabled() && is_array($this->conf['setfixed.']['ACCEPT.']) && is_array($this->conf['setfixed.']['APPROVE.'])) {
						$this->conf['setfixed.']['APPROVE.'] = $this->conf['setfixed.']['ACCEPT.'];
					}
				}
			}
		}

		if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
			if ($this->conf[$cmdKey.'.']['generatePassword'] && $cmdKey != 'edit') {
				unset($this->conf[$cmdKey.'.']['evalValues.']['password']);
			}
			if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] || ($this->conf[$cmdKey.'.']['generateUsername'] && $cmdKey != 'edit')) {
				unset($this->conf[$cmdKey.'.']['evalValues.']['username']);
			}
		}
		$confObj->setConf($this->conf);

			// Setting requiredArr to the fields in "required" fields list intersected with the total field list in order to remove invalid fields.
		$requiredArray = array_intersect(
			t3lib_div::trimExplode(',',
			$this->conf[$cmdKey.'.']['required'], 1),
			t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)
		);
		$controlData->setRequiredArray($requiredArray);
	}

	function getControlData ()	{
		return $this->controlData;
	}

	/**
	* All processing of the codes is done here
	*
	* @param string  command to execute
	* @param string message if an error has occurred
	* @return string  text to display
	*/
	function &doProcessing (&$error_message) {
		global $TSFE;

		$cmd = $this->controlData->getCmd();
		$cmdKey = $this->controlData->getCmdKey();
		$theTable = $this->controlData->getTable();
		$this->controlData->setMode (MODE_NORMAL);

		// Commands with which the Data will not be saved by $this->data->save
		$noSaveCommands = array('infomail','login','delete');

		// Ralf Hettinger: avoid data from edit forms being visible by back buttoning to client side cached pages
		// This only solves data being visible by back buttoning for edit forms.
		// It won't help against data being visible by back buttoning in create forms.
		$origArray = $this->data->getOrigArray();
		$dataArray = $this->data->getDataArray();
		$uid = $this->data->getRecUid();

		if ($theTable == 'fe_users' && (!$TSFE->loginUser || ($uid > 0 && $TSFE->fe_user->user['uid'] != $uid)) && !in_array($cmd,$this->noLoginCommands)) {

			$cmd = '';
			$this->controlData->setCmd($cmd);
			$origArray = array();
			$this->data->setOrigArray($origArray);
			$this->data->resetDataArray();
		}
		$markerArray = $this->marker->getArray();

			// Evaluate incoming data
		if (count($dataArray) && !in_array($cmd, $noSaveCommands)) {
			$this->data->setName($dataArray, $cmdKey);
			$this->data->parseValues($theTable, $dataArray,$origArray);
			$this->data->overrideValues($dataArray, $cmdKey);
			$submitData = $this->controlData->getFeUserData('submit');

			if ($submitData != '')	{
				$bSubmit = TRUE;
				$this->controlData->setSubmit(TRUE);
			}

			if ($bSubmit || $this->controlData->getFeUserData('doNotSave') || $this->controlData->getFeUserData('linkToPID')) {

				// a button was clicked on
				$this->data->evalValues(
					$theTable,
					$dataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$this->controlData->getRequiredArray()
				);

				if ($this->conf['evalFunc'] ) {
					$this->marker->setArray($markerArray);
					$dataArray = $this->userProcess('evalFunc', $dataArray);
					$markerArray = $this->marker->getArray();
				}
			} else {
				//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// we are going to redisplay
				$this->data->evalValues(
					$theTable,
					$dataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$this->controlData->getRequiredArray()
				);
				$this->marker->setArray($markerArray);
				$this->controlData->setFailure('submit');
			}
			$this->data->setUsername($theTable, $dataArray, $cmdKey);
			$this->data->setDataArray($dataArray);

			if ($this->controlData->getFailure()=='' && !$this->controlData->getFeUserData('preview') && !$this->controlData->getFeUserData('doNotSave') ) {
				$this->data->setPassword($dataArray, $cmdKey);
				$prefixId = $this->controlData->getPrefixId();
				$extKey = $this->controlData->getExtKey();
				$theUid = $this->data->save($theTable, $dataArray, $origArray, $cmd, $cmdKey, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess']);
				$dataArray['uid'] = $theUid;
			}
		} else {
			$this->marker->setNoError($cmdKey, $markerArray);
			$this->marker->setArray($markerArray);
			if ($cmd != 'delete')	{
				$this->controlData->setFeUserData(0, 'preview'); // No preview if data is not received and deleted
			}
		}
		if ($this->controlData->getFailure()!='') {
			$this->controlData->setFeUserData(0, 'preview');
		}

		 // No preview flag if a evaluation failure has occured
		if ($this->controlData->getFeUserData('preview'))	{
			$this->marker->setPreviewLabel('_PREVIEW');
			$this->controlData->setMode (MODE_PREVIEW);
		}
			// If data is submitted, we take care of it here.
		if ($cmd == 'delete' && !$this->controlData->getFeUserData('preview') && !$this->controlData->getFeUserData('doNotSave') ) {

			// Delete record if delete command is sent + the preview flag is NOT set.
			$this->data->deleteRecord($theTable, $origArray, $dataArray);
		}

			// Display forms
		if ($this->data->saved) {
				// Displaying the page here that says, the record has been saved. You're able to include the saved values by markers.
// 			$markerArray = $this->marker->getArray();
			$bCustomerConfirmsMode = FALSE;
			$bDefaultMode = FALSE;
			switch($cmd) {
				case 'delete':
					$key = 'DELETE'.SAVED_SUFFIX;
					break;
				case 'edit':
					$key = 'EDIT'.SAVED_SUFFIX;
					break;
				case 'invite':
					$key = SETFIXED_PREFIX.'INVITE';
					break;
				case 'create':
				default:
					$bDefaultMode = TRUE;
					if ($cmdKey == 'edit')	{
						$key = 'EDIT'.SAVED_SUFFIX;
					} else if ($this->controlData->getSetfixedEnabled()) {
						$key = SETFIXED_PREFIX.'CREATE';
						if ($this->conf['enableAdminReview'])	{

							if ($this->conf['enableEmailConfirmation'] || $this->conf['infomail'])	{
								$key .= '_REVIEW';
								$bCustomerConfirmsMode = TRUE;
							}
						}
					} else {
						$key = 'CREATE'.SAVED_SUFFIX;
					}
					break;
			}
				// Display confirmation message
			$subpartMarker = '###TEMPLATE_'.$key.'###';
			$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(), $subpartMarker);

			if ($templateCode)	{
				$markerArray = $this->marker->fillInMarkerArray($markerArray, $this->data->getCurrentArray(), '',TRUE, 'FIELD_', TRUE);
				$this->marker->addStaticInfoMarkers($markerArray, $this->data->getCurrentArray());
				$this->tca->addTcaMarkers($markerArray, $dataArray, $origArray, $cmd, $cmdKey, $theTable, true);
				$this->marker->addLabelMarkers(
					$markerArray,
					$theTable,
					$dataArray,
					$origArray,
					array(),
					$this->controlData->getRequiredArray(),
					$this->data->getFieldList(),
					$this->tca->TCA['columns'],
					false
				);

				if ($cmdKey == 'create')	{
					$this->marker->addMd5LoginMarkers($markerArray, $dataArray, $this->controlData->getUseMd5Password());
				}
				$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
				$markerArray = $this->marker->getArray(); // uses its own markerArray
				$newDataArray = $TSFE->sys_page->getRawRecord($theTable, $theUid); // the new data array is necessary after saving because default fields might have been added.

				if (isset($changesArray) && is_array($changesArray))	{
					$newDataArray = array_merge($newDataArray, $changesArray);
				}

				if (isset($newDataArray) && is_array($newDataArray))	{
					$this->tca->modifyRow($newDataArray, TRUE);
				}

				if ($this->conf['enableAdminReview'] && $bDefaultMode && !$bCustomerConfirmsMode) {

					// send admin the confirmation email
					// the customer will not confirm in this mode
					$rc = $this->email->compile(
						SETFIXED_PREFIX.'REVIEW',
						$theTable,
						array($newDataArray),
						array($origArray),
						$this->conf['email.']['admin'],
						$markerArray,
						'setfixed',
						$cmdKey,
						$this->data->getTemplateCode(),
						$this->conf['setfixed.']
					);
				} else {
						// Send email message(s)
					$rc = $this->email->compile(
						$key,
						$theTable,
						array($newDataArray),
						array($origArray),
						$newDataArray[$this->conf['email.']['field']],
						$markerArray,
						$cmd,
						$cmdKey,
						$this->data->getTemplateCode(),
						$this->conf['setfixed.']
					);
				}
				if ($rc == '')	{	// success case
					$origGetFeUserData = t3lib_div::_GET($this->controlData->getPrefixId());

						// Link to on edit save
						// backURL may link back to referring process
					if ($theTable == 'fe_users' &&
						$cmd == 'edit' &&
						($this->controlData->getBackURL() || ($this->conf['linkToPID'] && ($this->controlData->getFeUserData('linkToPID') || !$this->conf['linkToPIDAddButton']))) ) {
						$destUrl = ($this->controlData->getBackURL() ? $this->controlData->getBackURL() : ($TSFE->absRefPrefix ? '' : $this->controlData->getSiteUrl()).$this->cObj->getTypoLink_URL($this->conf['linkToPID'].','.$TSFE->type));

						header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
						exit;
					}

						// Auto-login on create
					if ($theTable == 'fe_users' && $cmd == 'create' && !$this->controlData->getSetfixedEnabled() && $this->conf['enableAutoLoginOnCreate']) {
						$row = $this->data->getCurrentArray();
						$this->login($row);

						if ($this->conf['autoLoginRedirect_url'])	{
							exit;
						}
					}
				} else { // error case
					$content = $rc;
				}
			} else {
				$errorText = $this->langObj->getLL('internal_no_subtemplate');
				$content = sprintf($errorText, $subpartMarker);
			}
		} else if ($this->data->getError()) {

				// If there was an error, we return the template-subpart with the error message
			$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(), $this->data->getError());
			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$this->data->getDataArray(),
				$this->data->getOrigArray(),
				array(),
				$this->controlData->getRequiredArray(),
				$this->data->getFieldList(),
				$this->tca->TCA['columns'],
				FALSE
			);
			$this->marker->setArray($markerArray);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		} else {
				// Finally, if there has been no attempt to save. That is either preview or just displaying and empty or not correctly filled form:
			$this->marker->setArray($markerArray);
			switch($cmd) {
				case 'setfixed':
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
					$templateCode = $this->data->getTemplateCode();
					$feuData = $this->controlData->getFeUserData();
					if (is_array($origArray))	{
						$origArray = $this->data->parseIncomingData($origArray, FALSE);
					}

					$content = $this->setfixedObj->processSetFixed(
						$theTable,
						$uid,
						$cmdKey,
						$markerArray,
						$templateCode,
						$dataArray,
						$origArray,
						$this,
						$this->data,
						$feuData
					);
					break;
				case 'infomail':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd);
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
					if (is_array($origArray))	{
						$origArray = $this->data->parseIncomingData($origArray, FALSE);
					}
					$content = $this->email->sendInfo(
						$theTable,
						$origArray,
						$markerArray,
						$cmd,
						$cmdKey,
						$this->data->getTemplateCode()
					);
					break;
				case 'delete':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd);
					$content = $this->display->deleteScreen($markerArray, $theTable, $dataArray, $origArray);
					break;
				case 'edit':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd);
					$content = $this->display->editScreen($markerArray, $theTable, $dataArray, $origArray, $cmd, $cmdKey, $this->controlData->getMode());
					break;
				case 'invite':
				case 'create':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd);
					$content = $this->display->createScreen(
						$markerArray,
						$cmd,
						$cmdKey,
						$this->controlData->getMode(),
						$theTable,
						$dataArray,
						$origArray,
						$this->data->getFieldList()
					);
					break;
				case 'login':
					// nothing. The login parameters are processed by TYPO3 Core
					break;
				default:
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd);
					$content = $this->display->createScreen(
						$markerArray,
						$cmd,
						$cmdKey,
						$this->controlData->getMode(),
						$theTable,
						$dataArray,
						$origArray,
						$this->data->getFieldList()
					);
					break;
			}
			if (!$this->controlData->getFeUserData('preview'))	{
				$origGetFeUserData = t3lib_div::_GET($this->controlData->getPrefixId());

				if (isset($origGetFeUserData['regHash']))	{
					$regHash = $origGetFeUserData['regHash'];
					$this->controlData->deleteShortUrl($regHash);
				}
			}
		}
		return $content;
	}

	function login ($row)	{
		global $TSFE;

		$loginVars = array();
		$loginVars['user'] = $row['username'];
		$loginVars['pass'] = $row['password'];
		if ($this->controlData->getUseMd5Password())	{
			$loginVars['challenge'] =  $this->controlData->getFeUserData('cv');
			if ($loginVars['challenge'])	{
				$loginVars['pass'] = (string)md5($row['username'].':'.$row['password'].':'.$loginVars['challenge']);
 			}
		}
		$loginVars['pid'] = $this->controlData->getPid();
		$loginVars['logintype'] = 'login';
		if ($this->conf['autoLoginRedirect_url'])	{
			$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
		}
		$relUrl = $this->cObj->getTypoLink_URL($this->controlData->getPID('login').','.$TSFE->type, $loginVars);
		$absUrl = ($TSFE->absRefPrefix ? '' : $this->controlData->getSiteUrl()).$relUrl;
		header('Location: '.t3lib_div::locationHeaderUrl($absUrl));
	}

	/**
	* Invokes a user process
	*
	* @param array  $mConfKey: the configuration array of the user process
	* @param array  $passVar: the array of variables to be passed to the user process
	* @return array  the updated array of passed variables
	*/
	function userProcess ($mConfKey, &$passVar) {
		global $TSFE;

		if ($this->conf[$mConfKey]) {
			$funcConf = $this->conf[$mConfKey.'.'];
			$funcConf['parentObj'] = &$this;
			$passVar = $TSFE->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
		}
		return $passVar;
	}	// userProcess

	/**
	* Invokes a user process
	*
	* @param string  $confVal: the name of the process to be invoked
	* @param array  $mConfKey: the configuration array of the user process
	* @param array  $passVar: the array of variables to be passed to the user process
	* @return array  the updated array of passed variables
	*/
	function userProcess_alt ($confVal, $confArr, $passVar) {
		global $TSFE;

		if ($confVal) {
			$funcConf = $confArr;
			$funcConf['parentObj'] = &$this;
			$passVar = $TSFE->cObj->callUserFunction($confVal, $funcConf, $passVar);
		}
		return $passVar;
	}	// userProcess_alt
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']);
}

?>
