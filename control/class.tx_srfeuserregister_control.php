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
 * display functions
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


define('SAVED_SUFFIX', '_SAVED');
define('SETFIXED_PREFIX', 'SETFIXED_');


class tx_srfeuserregister_control {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $display;
	var $data;
	var $marker;
	var $cObj;
	var $extKey;
	var $auth;
	var $email;
	var $tca;
	var $backURL;
	var $mode = MODE_NORMAL;	// internal modes: MODE_NORMAL, MODE_PREVIEW
	var $requiredArray; // List of required fields
	var $controlData;

	function init(&$pibase, &$conf, &$config, &$controlData, &$display, &$data, &$marker, &$auth, &$email, &$tca)	{
		global $TYPO3_CONF_VARS, $TSFE;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->display = &$display;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->cObj = &$pibase->cObj;
		$this->extKey = $pibase->extKey;
		$this->auth = &$auth;
		$this->email = &$email;
		$this->tca = &$tca;
		$this->controlData = &$controlData;

		$theTable = $this->controlData->getTable();
		$cmd = $this->data->getFeUserData('cmd');

		if (!$cmd)	{
			$cmd = $this->cObj->data['select_key'];
		}
		if ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['useFlexforms'] && t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
				// FE BE library for flexform functions
			require_once(PATH_BE_fh_library.'lib/class.tx_fhlibrary_flexform.php');
				// check the flexform
			$this->pibase->pi_initPIflexForm();
			$cmd = tx_fhlibrary_flexform::getSetupOrFFvalue(
				$this->pibase,
				$cmd,
				'',
				$this->conf['defaultCode'],
				$this->cObj->data['pi_flexform'],
				'display_mode',
				$TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['useFlexforms']
			);
		} else {
			$cmd = ($cmd ? $cmd : $this->conf['defaultCODE']);
		}
		$cmd = $this->cObj->caseshift($cmd,'lower');

		if ($cmd == 'edit' || $cmd == 'invite') {
			$cmdKey = $cmd;
		} else {
			$cmdKey = 'create';
		}
		$this->controlData->setCmdKey($cmdKey);

		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('module_sys_dmail_category')));
			$this->conf[$cmdKey.'.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['required'], 1), array('module_sys_dmail_category')));
		}

		$fieldConfArray = array('fields', 'required');
		foreach ($fieldConfArray as $k => $v)	{
			// make it ready for t3lib_div::inList which does not yet allow blanks
			$this->conf[$cmdKey.'.'][$v] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.'][$v])));
		}

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
				if ($cmdKey == 'edit' && $this->conf['setfixed']) {
					$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('email')));
				}
			}
			if ($this->conf[$cmdKey.'.']['allowUserGroupSelection']) {
				$this->conf[$cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'] . ',usergroup', 1)));
				$this->conf[$cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['required'] . ',usergroup', 1)));
				if ($cmdKey == 'edit' && is_array($this->conf['setfixed.'])) {
					if ($this->conf['enableAdminReview'] && is_array($this->conf['setfixed.']['ACCEPT.'])) {
						$this->conf[$cmdKey.'.']['overrideValues.']['usergroup'] = $this->conf['setfixed.']['ACCEPT.']['usergroup'];
					} elseif ($this->conf['setfixed'] && is_array($this->conf['setfixed.']['APPROVE.'])) {
						$this->conf[$cmdKey.'.']['overrideValues.']['usergroup'] = $this->conf['setfixed.']['APPROVE.']['usergroup'];
					}
				}
			} else {
				$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('usergroup')));
			}
			if ($cmdKey == 'invite') {
				if ($this->useMd5Password) {
					$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1), array('password')));
					if (is_array($this->conf[$cmdKey.'.']['evalValues.'])) {
						unset($this->conf[$cmdKey.'.']['evalValues.']['password']);
					}
				}
				if ($this->conf['enableAdminReview']) {
					if ($this->controlData->getSetfixedEnabled() && is_array($this->conf['setfixed.']['ACCEPT.']) && is_array($this->conf['setfixed.']['APPROVE.'])) {
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
			if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $cmdKey == 'edit' && $this->conf['setfixed']) {
				unset($this->conf[$cmdKey.'.']['evalValues.']['email']);
			}
		}

			// Setting requiredArr to the fields in "required" fields list intersected with the total field list in order to remove invalid fields.
		$requiredArray = array_intersect(
			t3lib_div::trimExplode(',', 
			$this->conf[$cmdKey.'.']['required'], 1),
			t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1)
		);
		$this->controlData->setRequiredArray($requiredArray);
		$this->controlData->setCmd($cmd);

		if ($theTable == 'fe_users' && $TSFE->loginUser && $cmd != 'invite' && $cmd != 'setfixed') {
			$recUid = $TSFE->fe_user->user['uid'];
		} else {
			$recUid = $this->data->getFeUserData('rU');
			$recUid = intval($recUid);
		}
		$this->data->setRecUid ($recUid);
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
		$theTable = $this->controlData->getTable();

		// Ralf Hettinger: avoid data from edit forms being visible by back buttoning to client side cached pages
		// This only solves data being visible by back buttoning for edit forms.
		// It won't help against data being visible by back buttoning in create forms.
		$noLoginCommands = array('','create','invite','setfixed','infomail','login');
		if ($theTable == 'fe_users' && !$GLOBALS['TSFE']->loginUser && !(in_array($cmd,$noLoginCommands))) {
			$cmd = '';
			$this->controlData->setCmd($cmd);
			$this->data->resetDataArray();
		}

			// Evaluate incoming data
		if (count($this->data->getDataArray())) {
			$this->data->setName();
			$this->data->parseValues();
			$this->data->overrideValues();
			if ($this->data->getFeUserData('submit') || $this->data->getFeUserData('doNotSave') || $this->data->getFeUserData('linkToPID')) {
				$markerArray = $this->marker->getArray();
				// a button was clicked on
				$this->data->evalValues($markerArray);
				$this->marker->setArray($markerArray);
				if ($this->conf['evalFunc'] ) {
					$this->data->setDataArray( $this->pibase->userProcess('evalFunc', $this->data->getDataArray()));
				}
			} else {
				//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// we are going to redisplay
				$markerArray = $this->marker->getArray();
				$this->data->evalValues($markerArray);
				$this->marker->setArray($markerArray);
				$this->data->setFailure(true);
			}
			$this->data->setUsername();
			if (!$this->data->getFailure() && !$this->data->getFeUserData('preview') && !$this->data->feUserData['doNotSave'] ) {
				$this->data->setPassword();
				$this->data->save();
			}
		} else {
			$markerArray = $this->marker->getArray();
			$this->data->defaultValues($markerArray); // If no incoming data, this will set the default values.
			$this->marker->setArray($markerArray);
			if ($cmd != 'delete')	{
				$this->data->setFeUserData(0, 'preview'); // No preview if data is not received and deleted
			}
		}
		if ($this->data->getFailure()) {
			$this->data->setFeUserData(0, 'preview');
		}

		 // No preview flag if a evaluation failure has occured
		if ($this->data->getFeUserData('preview'))	{
			$this->marker->setPreviewLabel('_PREVIEW');
			$this->controlData->setMode (MODE_PREVIEW);
		}
		$this->backURL = rawurldecode($this->data->getFeUserData('backURL'));

			// If data is submitted, we take care of it here.
		if ($cmd == 'delete' && !$this->data->getFeUserData('preview') && !$this->data->getFeUserData('doNotSave') ) {
			// Delete record if delete command is sent + the preview flag is NOT set.
			$this->data->deleteRecord();
		}

			// Display forms
		if ($this->data->saved) {
				// Displaying the page here that says, the record has been saved. You're able to include the saved values by markers.

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
					if (!$this->controlData->getSetfixedEnabled()) {
						$markerArray = $this->marker->getArray();
						$this->marker->addMd5LoginMarkers($markerArray);
						$this->marker->setArray($markerArray);
						if ($this->useMd5Password) {
							$this->data->setCurrentArr('','password');
						}
					}
				default:
					if ($this->controlData->getSetfixedEnabled()) {
						$key = SETFIXED_PREFIX.'CREATE';
						if ($this->conf['enableAdminReview']) {
							$key .= '_REVIEW';
						}
					} else {
						$key = 'CREATE'.SAVED_SUFFIX;
					}
					break;
			}
				// Display confirmation message
			$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(), '###TEMPLATE_'.$key.'###');
			$markerArray = $this->marker->getArray();
			$markerArray = $this->cObj->fillInMarkerArray($markerArray, $this->data->getCurrentArr(), '',TRUE, 'FIELD_', TRUE);
			$this->marker->addStaticInfoMarkers($markerArray, $this->data->getCurrentArr());
			$this->tca->addTcaMarkers($markerArray, $this->data->getCurrentArr(), true);
			$this->marker->addLabelMarkers($markerArray, $this->data->getCurrentArr(), $this->controlData->getRequiredArray(), $this->data->getFieldList(), $this->tca->TCA['columns']);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
			$markerArray = $this->marker->getArray(); // compile uses its own markerArray

				// Send email message(s)
			$this->email->compile(
				$key,
				array($this->data->getCurrentArr()),
				$this->data->getCurrentArr($this->conf['email.']['field']),
				$markerArray,
				$cmd,
				$this->controlData->getCmdKey(),
				$this->data->getTemplateCode(),
				$this->conf['setfixed.']
			);

				// Link to on edit save
				// backURL may link back to referring process
			if ($theTable == 'fe_users' && 
				$cmd == 'edit' && 
				($this->backURL || ($this->conf['linkToPID'] && ($this->data->getFeUserData('linkToPID') || !$this->conf['linkToPIDAddButton']))) ) {
				$destUrl = ($this->backURL ? $this->backURL : ($TSFE->absRefPrefix ? '' : $this->controlData->getSiteUrl()).$this->cObj->getTypoLink_URL($this->conf['linkToPID'].','.$TSFE->type));
				header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
				exit;
			}
				// Auto-login on create
			if ($theTable == 'fe_users' && $cmd == 'create' && !$this->controlData->getSetfixedEnabled() && $this->conf['enableAutoLoginOnCreate']) {
				$this->login ();
				if ($this->conf['autoLoginRedirect_url'])	{
					exit;
				}
			}
		} else if ($this->data->error) {
				// If there was an error, we return the template-subpart with the error message
			$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(), $this->data->error);
			$markerArray = $this->marker->getArray();
			$this->marker->addLabelMarkers($markerArray, $this->data->getDataArray(), $this->controlData->getRequiredArray(), $this->data->getFieldList(), $this->tca->TCA['columns']);
			$this->marker->setArray($markerArray);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		} else {
				// Finally, if there has been no attempt to save. That is either preview or just displaying and empty or not correctly filled form:
			switch($cmd) {
				case 'setfixed':
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
					$markerArray = $this->marker->getArray();
					$content = $this->processSetFixed($markerArray);
					break;
				case 'infomail':
					if ($this->conf['infomail']) {
						$this->controlData->setSetfixedEnabled(1);
					}
					$markerArray = $this->marker->getArray();
					$content = $this->email->sendInfo($markerArray, $cmd,
						$this->controlData->getCmdKey(), $this->data->getTemplateCode());
					break;
				case 'delete':
					$content = $this->display->deleteScreen();
					break;
				case 'edit':
					$content = $this->display->editScreen($cmd, $this->controlData->getCmdKey(), $this->controlData->getMode());
					break;
				case 'invite':
				case 'create':
					$content = $this->display->createScreen($cmd, $this->controlData->getCmdKey(),  $this->controlData->getMode());
					break;
				case 'login':
					// nothing. The login parameters are processed by TYPO3 Core
					break;
				default:
					if ($theTable == 'fe_users' && $TSFE->loginUser) {
						$content = $this->display->createScreen($cmd, $this->controlData->getCmdKey(), $this->controlData->getMode());
					} else {
						$content = $this->display->editScreen($cmd, $this->controlData->getCmdKey(), $this->controlData->getMode());
					}
					break;
			}
		}

		return $content;
	}

	function login ()	{
		global $TSFE;

		$loginVars = array();
		$loginVars['user'] = $this->data->getCurrentArr('username');
		$loginVars['pass'] = $this->data->getCurrentArr('password');
		$loginVars['pid'] = $this->controlData->getPid();
		$loginVars['logintype'] = 'login';
		$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
		header('Location: '.t3lib_div::locationHeaderUrl(($TSFE->absRefPrefix ? '' : $this->controlData->getSiteUrl()).$this->cObj->getTypoLink_URL($this->controlData->getPID('login').','.$TSFE->type, $loginVars)));
	}


	/**
	* Process the front end user reply to the confirmation request
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return string  the template with substituted markers
	*/ 
	function processSetFixed(&$markContentArray) {
		global $TSFE, $TYPO3_CONF_VARS;;

		if ($this->controlData->getSetfixedEnabled()) {
			$theTable = $this->controlData->getTable();

			$origArr = $TSFE->sys_page->getRawRecord($theTable, $this->data->getRecUid());
			$origUsergroup = $origArr['usergroup'];
			$setfixedUsergroup = '';
			$fD = t3lib_div::_GP('fD', 1);
			$fieldArr = array();
			if (is_array($fD)) {
				reset($fD);
				while (list($field, $value) = each($fD)) {
					$origArr[$field] = rawurldecode($value);
					if($field == 'usergroup') {
						$setfixedUsergroup = rawurldecode($value);
					}
					$fieldArr[] = $field;
				}
			}
			$theCode = $this->auth->setfixedHash($origArr, $origArr['_FIELDLIST']);

			if (!strcmp($this->auth->authCode, $theCode)) {
				if ($this->data->getFeUserData('sFK') == 'DELETE' || $this->data->getFeUserData('sFK') == 'REFUSE') {
					if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
						// If the record is fully deleted... then remove the image attached.
						$this->data->deleteFilesFromRecord($this->data->getRecUid());
					}
					$res = $this->cObj->DBgetDelete($theTable, $this->data->getRecUid(), true);
					$this->data->deleteMMRelations($theTable, $this->data->getRecUid(), $origArr);
				} else {
					if ($theTable == 'fe_users') {
						if ($this->conf['create.']['allowUserGroupSelection']) {
							$origArr['usergroup'] = implode(',', array_unique(array_merge(array_diff(t3lib_div::trimExplode(',', $origUsergroup, 1), t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'], 1)), t3lib_div::trimExplode(',', $setfixedUsergroup, 1))));
						} elseif ($this->data->getFeUserData('sFK') == 'APPROVE' && $origUsergroup != $this->conf['create.']['overrideValues.']['usergroup']) {
							$origArr['usergroup'] = $origUsergroup;
						}
					}
						// Hook: first we initialize the hooks
					$hookObjectsArr = array();
					if (is_array ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->controlData->getPrefixId()]['confirmRegistrationClass'])) {
						foreach  ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey][$this->controlData->getPrefixId()]['confirmRegistrationClass'] as $classRef) {
							$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
						}
					}
						// Hook: confirmRegistrationClass_preProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_preProcess')) {
							$hookObj->confirmRegistrationClass_preProcess($origArr, $this);
						}
					}
					$newFieldList = implode(array_intersect(t3lib_div::trimExplode(',', $this->data->fieldList), t3lib_div::trimExplode(',', implode($fieldArr, ','), 1)), ',');
					$res = $this->cObj->DBgetUpdate($theTable, $this->data->getRecUid(), $origArr, $newFieldList, true);
					$this->data->setCurrentArr($GLOBALS['TSFE']->sys_page->getRawRecord($theTable,$this->data->getRecUid()));
					$modArray=array();
					$this->data->setCurrentArr ($this->tca->modifyTcaMMfields($this->data->getCurrentArr(),$modArray));
					$origArr = array_merge ($origArr, $modArray);
					$this->pibase->userProcess_alt($this->conf['setfixed.']['userFunc_afterSave'],$this->conf['setfixed.']['userFunc_afterSave.'],array('rec'=>$this->data->getCurrentArr(), 'origRec'=>$origArr));

						// Hook: confirmRegistrationClass_postProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
							$hookObj->confirmRegistrationClass_postProcess($origArr, $this);
						}
					} 
				}

				// Outputting template
				if ($theTable == 'fe_users' && ($this->data->getFeUserData('sFK') == 'APPROVE' || $this->data->getFeUserData('sFK') == 'ENTER')) {
					$markerArray = $this->marker->getArray();
					$this->marker->addMd5LoginMarkers($markerArray);
					$this->marker->setArray($markerArray);
					if($this->useMd5Password) {
						$origArr['password'] = '';
					}
				}
				$setfixedSufffix = $this->data->getFeUserData('sFK');
				if ($this->conf['enableAdminReview'] && $this->data->getFeUserData('sFK') == 'APPROVE' && !$origArr['by_invitation']) {
					$setfixedSufffix .= '_REVIEW';
				}
				$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX . 'OK_' . $setfixedSufffix . '###', $origArr);
				if (!$content) {
					$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX .'OK###', $origArr);
				}
				// Compiling email
				$this->data->setDataArray($origArr);

				$this->email->compile(
					SETFIXED_PREFIX.$setfixedSufffix,
					array($origArr),
					$origArr[$this->conf['email.']['field']],
					$markContentArray,
					$this->controlData->getCmd(),
					$this->controlData->getCmdKey(),
					$this->data->getTemplateCode(),
					$this->conf['setfixed.']
				);

				if ($theTable == 'fe_users') { 
						// If applicable, send admin a request to review the registration request
					if ($this->conf['enableAdminReview'] && $this->data->getFeUserData('sFK') == 'APPROVE' && !$origArr['by_invitation']) {

						$this->email->compile(
							SETFIXED_PREFIX.'REVIEW',
							array($origArr),
							$this->conf['email.']['admin'],
							$markContentArray,
							$this->controlData->getCmd(),
							$this->controlData->getCmdKey(),
							$this->data->getTemplateCode(),
							$this->conf['setfixed.']
						);
					}

						// Auto-login on confirmation
					if ($this->conf['enableAutoLoginOnConfirmation'] && ($this->data->getFeUserData('sFK') == 'APPROVE' || $this->data->getFeUserData('sFK') == 'ENTER')) {
						$this->login();
						exit;
					}
				}
			} else {
				$content = $this->display->getPlainTemplate('###TEMPLATE_SETFIXED_FAILED###');
			}
		}
		return $content;
	}	// processSetFixed


	/**
	* Checks if preview display is on.
	*
	* @return boolean  true if preview display is on
	*/
	function isPreview() {
		$rc = '';
		$cmdKey = $this->controlData->getCmdKey();

		$rc = ($this->conf[$cmdKey.'.']['preview'] && $this->data->getFeUserData('preview'));
		return $rc;
	}	// isPreview



}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']);
}
?>
