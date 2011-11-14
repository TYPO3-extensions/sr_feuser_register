<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2011 Stanislas Rolland (stanislas.rolland@sjbr.ca)
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
	public $langObj;
	public $conf = array();
	public $display;
	public $data;
	public $marker;
	public $cObj;
	public $auth;
	public $email;
	public $tca;
	public $requiredArray; // List of required fields
	public $controlData;
	public $setfixedObj;
	public $noLoginCommands = array('create','invite','setfixed','infomail','login');


	public function init (&$langObj, &$cObj, &$controlData, &$display, &$marker, &$email, &$tca, &$setfixedObj)	{
		global $TSFE;

		$this->langObj = &$langObj;
		$confObj = &t3lib_div::getUserObj('&tx_srfeuserregister_conf');

		$this->conf = &$confObj->getConf();
		$this->display = &$display;
		$this->marker = &$marker;
		$this->cObj = &$cObj;
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
				$this->cObj->data['pi_flexform'] = t3lib_div::xml2array($this->cObj->data['pi_flexform']);
				$cmd = tx_div2007_alpha::getSetupOrFFvalue_fh002(
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


	public function init2 ($theTable, &$controlData, &$data, &$adminFieldList)	{
		global $TSFE;

		$this->data = &$data;

		$confObj = &t3lib_div::getUserObj('&tx_srfeuserregister_conf');
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
				(
					$cmd == ''
					|| $cmd == 'setfixed'
				)
				&& (
					($theTable != 'fe_users' || $theUid == $TSFE->fe_user->user['uid']) &&
					count($origArray)
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
		$tcaFieldArray = t3lib_div::trimExplode(',', $tableTCA['feInterface']['fe_admin_fieldList'], 1);
		$tcaFieldArray = array_unique($tcaFieldArray);
		$fieldlist = implode(',', $tcaFieldArray);
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


	public function getControlData ()	{
		return $this->controlData;
	}


	/**
	* All processing of the codes is done here
	*
	* @param string  command to execute
	* @param string message if an error has occurred
	* @return string  text to display
	*/
	public function &doProcessing (
		$theTable,
		$cmd,
		$cmdKey,
		$origArray,
		$dataArray,
		$templateCode,
		&$error_message
	) {
		global $TSFE;

		$this->controlData->setMode(MODE_NORMAL);

		// Commands with which the Data will not be saved by $this->data->save
		$noSaveCommands = array('infomail','login','delete');

		$uid = $this->data->getRecUid();
		$securedArray = array();

		// check for valid token
		if (!$this->controlData->isTokenValid() || $theTable == 'fe_users' && (!$TSFE->loginUser || ($uid > 0 && $TSFE->fe_user->user['uid'] != $uid)) && !in_array($cmd,$this->noLoginCommands)) {

			$cmd = '';
			$this->controlData->setCmd($cmd);
			$origArray = array();

			$this->data->setOrigArray($origArray);
			$this->data->resetDataArray();
			$finalDataArray = $dataArray;
		} else if ($this->data->bNewAvailable()) {
			$securedArray = $this->controlData->readUnsecuredArray();

			if (isset($securedArray) && is_array($securedArray))	{
				$finalDataArray = t3lib_div::array_merge_recursive_overrule($dataArray, $securedArray, FALSE);
			}
		} else {
			$finalDataArray = $dataArray;
		}

		$submitData = $this->controlData->getFeUserData('submit');

		if ($submitData != '')	{
			$bSubmit = TRUE;
			$this->controlData->setSubmit(TRUE);
		}

		$doNotSaveData = $this->controlData->getFeUserData('doNotSave');
		if ($doNotSaveData != '')	{
			$bDoNotSave = TRUE;
			$this->controlData->setDoNotSave(TRUE);
		}

		$markerArray = $this->marker->getArray();

			// Evaluate incoming data
		if (count($finalDataArray) && !in_array($cmd, $noSaveCommands)) {
			$this->data->setName($finalDataArray, $cmdKey);
			$this->data->parseValues($theTable, $finalDataArray, $origArray);
			$this->data->overrideValues($finalDataArray, $cmdKey);

			if ($bSubmit || $bDoNotSave || $this->controlData->getFeUserData('linkToPID')) {

				// a button was clicked on
				$this->data->evalValues(
					$theTable,
					$finalDataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$this->controlData->getRequiredArray()
				);

				if ($this->conf['evalFunc'] ) {
					$this->marker->setArray($markerArray);
					$finalDataArray = $this->userProcess('evalFunc', $finalDataArray);
					$markerArray = $this->marker->getArray();
				}
			} else {
				//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// we are going to redisplay
				$this->data->evalValues(
					$theTable,
					$finalDataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$this->controlData->getRequiredArray()
				);
				$this->marker->setArray($markerArray);
				$this->controlData->setFailure('submit');
			}
			$this->data->setUsername($theTable, $finalDataArray, $cmdKey);
			$this->data->setDataArray($finalDataArray);

			if ($this->controlData->getFailure() == '' && !$this->controlData->getFeUserData('preview') && !$bDoNotSave) {
// 				if ($this->controlData->getUseMd5Password() && !$this->conf[$cmdKey.'.']['generatePassword'])	{
// 					$origPassword = $this->controlData->readPassword();
// 				}
				$this->data->generatePassword($finalDataArray, $cmdKey);
				$genPassword = $this->data->getPassword($finalDataArray);

				if ($genPassword != '' && $genPassword != $this->controlData->getDummyPassword())	{
					$password = $genPassword;
					$this->controlData->writePassword($password);
					$securedArray = $this->controlData->readUnsecuredArray();
				}
				$prefixId = $this->controlData->getPrefixId();
				$extKey = $this->controlData->getExtKey();

				$theUid = $this->data->save(
					$theTable,
					$finalDataArray,
					$origArray,
					$newDataArray,
					$cmd,
					$cmdKey,
					$this->controlData->getPid(),
					$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess']
				);

				if ($newDataArray)	{
					$dataArray = $newDataArray;
				}
				if ($this->data->getSaved())	{
					$this->controlData->clearSessionData();
				}
			}
		} else {
			$this->marker->setNoError($cmdKey, $markerArray);
			$this->marker->setArray($markerArray);
			if ($cmd != 'delete')	{
				$this->controlData->setFeUserData(0, 'preview'); // No preview if data is not received and deleted
			}
		}
		if ($this->controlData->getFailure() != '') {
			$this->controlData->setFeUserData(0, 'preview');
		}

		 // No preview flag if a evaluation failure has occured
		if ($this->controlData->getFeUserData('preview'))	{
			$this->marker->setPreviewLabel('_PREVIEW');
			$this->controlData->setMode(MODE_PREVIEW);
		}
			// If data is submitted, we take care of it here.
		if ($cmd == 'delete' && !$this->controlData->getFeUserData('preview') && !$bDoNotSave) {

			// Delete record if delete command is set + the preview flag is NOT set.
			$this->data->deleteRecord($theTable, $origArray, $dataArray);
		}
		$errorContent = '';
		$bDeleteRegHash = FALSE;

			// Display forms
		if ($this->data->getSaved()) {

			$bCustomerConfirmsMode = FALSE;
			$bDefaultMode = FALSE;
			if (
				($cmd == '' || $cmd == 'create')
			) {
				$bDefaultMode = TRUE;
			}

			if (
				$bDefaultMode
				&& ($cmdKey != 'edit')
				&& $this->conf['enableAdminReview']
				&& ($this->conf['enableEmailConfirmation'] || $this->conf['infomail'])
			){
				$bCustomerConfirmsMode = TRUE;
			}
			$key = $this->display->getKeyAfterSave($cmd, $cmdKey, $bCustomerConfirmsMode);
			$errorContent = $this->display->afterSave(
				$theTable,
				$dataArray,
				$origArray,
				$securedArray,
				$cmd,
				$cmdKey,
				$key,
				$templateCode,
				$markerArray,
				$this->data->inError,
				$content
			);

			if ($errorContent == '') {
				$markerArray = $this->marker->getArray(); // uses its own markerArray

				if ($this->conf['enableAdminReview'] && $bDefaultMode && !$bCustomerConfirmsMode) {
					// send admin the confirmation email
					// the customer will not confirm in this mode
					$errorContent = $this->email->compile(
						SETFIXED_PREFIX . 'REVIEW',
						$theTable,
						array($dataArray),
						array($origArray),
						$securedArray,
						$this->conf['email.']['admin'],
						$markerArray,
						'setfixed',
						$cmdKey,
						$templateCode,
						$errorFieldArray,
						$this->conf['setfixed.']
					);
				} else if ($cmdKey == 'create' || $cmdKey == 'invite' || $this->conf['email.']['EDIT_SAVED']) {
					$emailField = $this->conf['email.']['field'];
					$recipient = (isset($finalDataArray) && is_array($finalDataArray) ? $finalDataArray[$emailField] : $origArray[$emailField]);
					// Send email message(s)
					$errorContent = $this->email->compile(
						$key,
						$theTable,
						array($dataArray),
						array($origArray),
						$securedArray,
						$recipient,
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode,
						$errorFieldArray,
						$this->conf['setfixed.']
					);
				}
			}

			if ($errorContent == '') {	// success case
				$origGetFeUserData = t3lib_div::_GET($this->controlData->getPrefixId());
				$bDeleteRegHash = TRUE;

					// Link to on edit save
					// backURL may link back to referring process
				if ($theTable == 'fe_users' &&
					$cmd == 'edit' &&
					($this->controlData->getBackURL() || ($this->conf['linkToPID'] && ($this->controlData->getFeUserData('linkToPID') || !$this->conf['linkToPIDAddButton']))) ) {
					$destUrl = ($this->controlData->getBackURL() ? $this->controlData->getBackURL() : $this->cObj->getTypoLink_URL($this->conf['linkToPID'] . ',' . $TSFE->type));

					header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
					exit;
				}

					// Auto-login on create
				if ($theTable == 'fe_users' && $cmd == 'create' && !$this->controlData->getSetfixedEnabled() && $this->conf['enableAutoLoginOnCreate']) {
					$this->login($finalDataArray);

					if ($this->conf['autoLoginRedirect_url']) {
						exit;
					}
				}
			} else { // error case
				$content = $errorContent;
			}
		} else if ($this->data->getError()) {

				// If there was an error, we return the template-subpart with the error message
			$templateCode = $this->cObj->getSubpart($templateCode, $this->data->getError());
			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$finalDataArray,
				$this->data->getOrigArray(),
				$securedArray,
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
			$token = $this->controlData->readToken();

			if ($cmd == '' && $this->controlData->getFeUserData('preview')) {
				$cmd = $cmdKey;
			}

			switch($cmd) {
				case 'setfixed':
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
// 					$templateCode = $this->data->getTemplateCode();
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
						$finalDataArray,
						$origArray,
						$securedArray,
						$this,
						$this->data,
						$feuData,
						$token
					);
					break;
				case 'infomail':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd, $token);
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
					if (is_array($origArray))	{
						$origArray = $this->data->parseIncomingData($origArray, FALSE);
					}
					$content = $this->email->sendInfo(
						$theTable,
						$origArray,
						$securedArray,
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode
					);
					break;
				case 'delete':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd, $token);
					$content = $this->display->deleteScreen(
						$markerArray,
						$theTable,
						$finalDataArray,
						$origArray,
						$securedArray,
						$token
					);
					break;
				case 'edit':
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd, $token);
					$content = $this->display->editScreen(
						$markerArray,
						$theTable,
						$finalDataArray,
						$origArray,
						$securedArray,
						$cmd,
						$cmdKey,
						$this->controlData->getMode(),
						$this->data->inError,
						$token
					);
					break;
				case 'invite':
				case 'create':
					$this->marker->addGeneralHiddenFieldsMarkers(
						$markerArray,
						$cmd,
						$token
					);
					if ($this->data->bNewAvailable())	{
						$securedArray = $this->controlData->readSecuredArray();
					} else {
						$securedArray = array();
					}

					$content = $this->display->createScreen(
						$markerArray,
						$cmd,
						$cmdKey,
						$this->controlData->getMode(),
						$theTable,
						$finalDataArray,
						$origArray,
						$securedArray,
						$this->data->getFieldList(),
						$this->data->inError,
						$token
					);
					break;
				case 'login':
					// nothing. The login parameters are processed by TYPO3 Core
					break;
				default:
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, $cmd, $token);
					$content = $this->display->createScreen(
						$markerArray,
						$cmd,
						$cmdKey,
						$this->controlData->getMode(),
						$theTable,
						$finalDataArray,
						$origArray,
						$securedArray,
						$this->data->getFieldList(),
						$this->data->inError,
						$token
					);
					break;
			}

			if (
				($cmd != 'setfixed' || $cmdKey != 'edit')
				&& !$errorContent
				&& !$this->controlData->getFeUserData('preview')
			)	{
				$bDeleteRegHash = TRUE;
			}
		}

		if (
			$bDeleteRegHash
			&& $this->controlData->getValidRegHash()
		) {
			$regHash = $this->controlData->getRegHash();
			$this->controlData->deleteShortUrl($regHash);
		}
		return $content;
	}


	public function login ($row)	{
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

		$redirect_url = $this->controlData->readRedirectUrl();

		if ($redirect_url == '' && $this->conf['autoLoginRedirect_url'])	{
			$redirect_url = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
		}
		if ($redirect_url != '')	{
			$loginVars['redirect_url'] = $redirect_url;
		}
		$relUrl = $this->cObj->getTypoLink_URL(
			$this->controlData->getPID('login') . ',' . $TSFE->type,
			$loginVars
		);

		$absUrl = $this->controlData->getSiteUrl() . $relUrl;

		$this->controlData->clearSessionData(FALSE);
		header('Location: '.t3lib_div::locationHeaderUrl($absUrl));
	}


	/**
	* Invokes a user process
	*
	* @param array  $mConfKey: the configuration array of the user process
	* @param array  $passVar: the array of variables to be passed to the user process
	* @return array  the updated array of passed variables
	*/
	public function userProcess ($mConfKey, &$passVar) {
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
	public function userProcess_alt ($confVal, $confArr, $passVar) {
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