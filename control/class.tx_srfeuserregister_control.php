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


define ('MODE_NORMAL', '0');
define ('MODE_PREVIEW', '1');

class tx_srfeuserregister_control {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $display;
	var $data;
	var $cmdKey;
	var $cmd;
	var $marker;
	var $cObj;
	var $setfixedEnabled;
	var $loginPID;
	var $site_url;
	var $prefixId;
	var $extKey;
	var $useMd5Password;
	var $auth;
	var $email;
	var $tca;
	var $backURL;
	var $mode = MODE_NORMAL;	// internal modes: MODE_NORMAL, MODE_PREVIEW
	var $thePid = 0;

	function init(&$pibase, &$conf, &$config, &$display, &$data, &$marker, &$auth, &$email, &$tca)	{
		global $TYPO3_CONF_VARS, $TSFE;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->display = &$display;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->cObj = &$pibase->cObj;
		$this->setfixedEnabled = $pibase->setfixedEnabled;
		$this->site_url = $pibase->site_url;
		$this->prefixId = $pibase->prefixId;
		$this->extKey = $pibase->extKey;
		$this->useMd5Password = $pibase->useMd5Password;
		$this->auth = &$auth;
		$this->email = &$email;
		$this->tca = &$tca;


		$this->loginPID = intval($this->conf['loginPID']) ? strval(intval($this->conf['loginPID'])) : $TSFE->id;

		$cmd = $this->data->feUserData['cmd'] ? $this->data->feUserData['cmd'] : $this->cObj->caseshift($this->cObj->data['select_key'],'lower');
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
			$cmd = $cmd ? $cmd : $this->cObj->caseshift($this->conf['defaultCODE'],'lower');
		}

		if ($cmd == 'edit' || $cmd == 'invite') {
			$this->setCmdKey($cmd);
		} else {
			$this->setCmdKey('create');
		}

		$this->setCmd($cmd);

			// Initialise password encryption
		if ($this->data->theTable == 'fe_users' && t3lib_extMgm::isLoaded('kb_md5fepw')) {
			require_once(t3lib_extMgm::extPath('kb_md5fepw').'class.tx_kbmd5fepw_funcs.php');
			$this->useMd5Password = TRUE;
			$this->conf['enableAutoLoginOnConfirmation'] = FALSE;
			$this->conf['enableAutoLoginOnCreate'] = FALSE;
		}

		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$this->thePid = intval($this->conf['pid']) ? strval(intval($this->conf['pid'])) : $TSFE->id;
	}

	function getCmd() {
		return $this->cmd;
	}

	function setCmd($cmd) {
		$this->cmd = $cmd;
	}

	function getCmdKey() {
		return $this->cmdKey;
	}

	function setCmdKey($cmdKey)	{
		$this->cmdKey = $cmdKey;
	}

	/**
	* All processing of the codes is done here
	*
	* @param string  command to execute
	* @param string message if an error has occurred
	* @return string  text to display
	*/ 
	function &doProcessing (&$error_message) {

		$cmd = $this->getCmd();

		// Ralf Hettinger: avoid data from edit forms being visible by back buttoning to client side cached pages
		// This only solves data being visible by back buttoning for edit forms.
		// It won't help against data being visible by back buttoning in create forms.
		$noLoginCommands = array('','create','invite','setfixed','infomail');
		if (!$GLOBALS['TSFE']->loginUser && !(in_array($cmd,$noLoginCommands))) {
			$cmd = '';
			$this->setCmd($cmd);
			$this->data->dataArr = array();
		}

			// Evaluate incoming data
		if (count($this->data->dataArr)) {
			$this->data->setName();
			$this->data->parseValues();
			$this->data->overrideValues();
			if ($this->data->feUserData['submit'] || $this->data->feUserData['doNotSave'] || $this->data->feUserData['linkToPID']) {
				$markerArray = $this->marker->getArray();
				// a button was clicked on
				$this->data->evalValues($markerArray);
				$this->marker->setArray($markerArray);
				if ($this->conf['evalFunc'] ) {
					$this->data->dataArr = $this->pibase->userProcess('evalFunc', $this->data->dataArr);
				}
			} else {
				//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// we are going to redisplay
				$markerArray = $this->marker->getArray();
				$this->data->evalValues($markerArray);
				$this->marker->setArray($markerArray);
				$failure = true;
			}
			$this->data->setUsername();
			if (!$failure && !$this->data->feUserData['preview'] && !$this->data->feUserData['doNotSave'] ) {
				$this->data->setPassword();
				$this->data->save();
			}
		} else {
			$markerArray = $this->marker->getArray();
			$this->data->defaultValues($markerArray); // If no incoming data, this will set the default values.
			$this->marker->setArray($markerArray);
			$this->data->feUserData['preview'] = 0; // No preview if data is not received
		}
		if ($this->data->failure) {
			$this->data->feUserData['preview'] = 0;
		}

		 // No preview flag if a evaluation failure has occured
		if ($this->data->feUserData['preview'])	{
			$this->marker->setPreviewLabel('_PREVIEW');
			$this->setMode (MODE_PREVIEW);
		}
		$this->backURL = rawurldecode($this->data->feUserData['backURL']);

			// If data is submitted, we take care of it here.
		if ($cmd == 'delete' && !$this->data->feUserData['preview'] && !$this->data->feUserData['doNotSave'] ) {
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
					if (!$this->setfixedEnabled) {
						$markerArray = $this->marker->getArray();
						$this->marker->addMd5LoginMarkers($markerArray);
						$this->marker->setArray($markerArray);
						if ($this->useMd5Password) {
							$this->data->currentArr['password'] = '';
						}
					}
				default:
					if ($this->setfixedEnabled) {
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
			$templateCode = $this->cObj->getSubpart($this->data->templateCode, '###TEMPLATE_'.$key.'###');
			$markerArray = $this->marker->getArray();
			$markerArray = $this->cObj->fillInMarkerArray($markerArray, $this->data->currentArr, '',TRUE, 'FIELD_', TRUE);
			$this->marker->addStaticInfoMarkers($markerArray, $this->data->currentArr);
			$this->tca->addTcaMarkers($markerArray, $this->data->currentArr, true);
			$this->marker->addLabelMarkers($markerArray, $this->data->currentArr);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);

			$markerArray = $this->marker->getArray(); // compile uses its own markerArray
				// Send email message(s)
			$this->email->compile($key, array($this->data->currentArr),
				$this->data->currentArr[$this->conf['email.']['field']],
				$markerArray,
				$this->getCmd(),
				$this->getCmdKey(),
				$this->data->templateCode,
				$this->conf['setfixed.']
			);

				// Link to on edit save
				// backURL may link back to referring process
			if ($this->data->theTable == 'fe_users' && 
				$cmd == 'edit' && 
				($this->backURL || ($this->conf['linkToPID'] && ($this->data->feUserData['linkToPID'] || !$this->conf['linkToPIDAddButton']))) ) {
				$destUrl = ($this->backURL ? $this->backURL : ($TSFE->absRefPrefix ? '' : $this->site_url).$this->cObj->getTypoLink_URL($this->conf['linkToPID'].','.$TSFE->type));
				header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
				exit;
			}
				// Auto-login on create
			if ($this->data->theTable == 'fe_users' && $cmd == 'create' && !$this->setfixedEnabled && $this->conf['enableAutoLoginOnCreate']) {
				$this->login ();
				if ($this->conf['autoLoginRedirect_url'])	{
					exit;
				}
			}
		} else if($this->data->error) {
				// If there was an error, we return the template-subpart with the error message
			$templateCode = $this->cObj->getSubpart($this->data->templateCode, $this->data->error);
			$markerArray = $this->marker->getArray();
			$this->marker->addLabelMarkers($markerArray, $this->data->dataArr);
			$this->marker->setArray($markerArray);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		} else {
				// Finally, if there has been no attempt to save. That is either preview or just displaying and empty or not correctly filled form:
			switch($cmd) {
				case 'setfixed':
					if ($this->conf['infomail']) {
						$this->setfixedEnabled = 1;
					}
					$markerArray = $this->marker->getArray();
					$content = $this->processSetFixed($markerArray);
					break;
				case 'infomail':
					if ($this->conf['infomail']) {
						$this->setfixedEnabled = 1;
					}
					$markerArray = $this->marker->getArray();
					$content = $this->email->sendInfo($markerArray, $this->getCmd(),
					$this->getCmdKey(), $this->data->templateCode);
					break;
				case 'delete':
					$content = $this->display->deleteScreen();
					break;
				case 'edit':
					$content = $this->display->editScreen($cmd, $this->getCmdKey());
					break;
				case 'invite':
				case 'create':
					$content = $this->display->createScreen($cmd);
					break;
				default:
					if ($this->data->theTable == 'fe_users' && $TSFE->loginUser) {
						$content = $this->display->createScreen($cmd);
					} else {
						$content = $this->display->editScreen($cmd, $this->getCmdKey());
					}
					break;
			}
		}
		return $content;
	}

	function getMode()	{
		return $this->mode;
	}

	function setMode($mode)	{
		$this->mode = $mode;
	}

	function login ()	{
		global $TSFE;

		$loginVars = array();
		$loginVars['user'] = $this->data->currentArr['username'];
		$loginVars['pass'] = $this->data->currentArr['password'];
		$loginVars['pid'] = $this->thePid;
		$loginVars['logintype'] = 'login';
		$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
		header('Location: '.t3lib_div::locationHeaderUrl(($TSFE->absRefPrefix ? '' : $this->site_url).$this->cObj->getTypoLink_URL($this->loginPID.','.$TSFE->type, $loginVars)));
	}

	/**
	* Generates a pibase-compliant typolink
	*
	* @param string  $tag: string to include within <a>-tags; if empty, only the url is returned
	* @param string  $id: page id (could of the form id,type )
	* @param array  $vars: extension variables to add to the url ($key, $value)
	* @param array  $unsetVars: extension variables (piVars to unset)
	* @param boolean  $usePiVars: if set, input vars and incoming piVars arrays are merge
	* @return string  generated link or url
	*/
	function getUrl($tag = '', $id, $vars = array(), $unsetVars = array(), $usePiVars = true) {
			
		$vars = (array) $vars;
		$unsetVars = (array) $unsetVars;
		if ($usePiVars) {
			$vars = array_merge($this->pibase->piVars, $vars); //vars override pivars
			while (list(, $key) = each($unsetVars)) {
				// unsetvars override anything
				unset($vars[$key]);
			}
		}
		while (list($key, $val) = each($vars)) {
			$piVars[$this->prefixId . '['. $key . ']'] = $val;
		}
		if ($tag) {
			$rc = $this->cObj->getTypoLink($tag, $id, $piVars);
		} else {
			$rc = $this->cObj->getTypoLink_URL($id, $piVars);
		}
		
		$rc = htmlspecialchars($rc);
		return $rc;
	}	// get_url


	/**
	* Process the front end user reply to the confirmation request
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return string  the template with substituted markers
	*/ 
	function processSetFixed(&$markContentArray) {
		global $TSFE;
		
		if ($this->setfixedEnabled) {
			$origArr = $TSFE->sys_page->getRawRecord($this->data->theTable, $this->data->recUid);
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
				if ($this->data->feUserData['sFK'] == 'DELETE' || $this->data->feUserData['sFK'] == 'REFUSE') {
					if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
						// If the record is fully deleted... then remove the image attached.
						$this->data->deleteFilesFromRecord($this->data->recUid);
					}
					$res = $this->cObj->DBgetDelete($this->data->theTable, $this->data->recUid, true);
					$this->data->deleteMMRelations($this->data->theTable, $this->data->recUid, $origArr);
				} else {
					if ($this->data->theTable == 'fe_users') {
						if ($this->conf['create.']['allowUserGroupSelection']) {
							$origArr['usergroup'] = implode(',', array_unique(array_merge(array_diff(t3lib_div::trimExplode(',', $origUsergroup, 1), t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'], 1)), t3lib_div::trimExplode(',', $setfixedUsergroup, 1))));
						} elseif ($this->data->feUserData['sFK'] == 'APPROVE' && $origUsergroup != $this->conf['create.']['overrideValues.']['usergroup']) {
							$origArr['usergroup'] = $origUsergroup;
						}
					}
						// Hook: first we initialize the hooks
					$hookObjectsArr = array();
					if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['confirmRegistrationClass'])) {
						foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['confirmRegistrationClass'] as $classRef) {
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
					$res = $this->cObj->DBgetUpdate($this->data->theTable, $this->data->recUid, $origArr, $newFieldList, true);
					$this->data->currentArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->data->theTable,$this->data->recUid);
					$modArray=array();
					$this->data->currentArr = $this->tca->modifyTcaMMfields($this->data->currentArr,$modArray);
					$origArr = array_merge ($origArr, $modArray);
					$this->pibase->userProcess_alt($this->conf['setfixed.']['userFunc_afterSave'],$this->conf['setfixed.']['userFunc_afterSave.'],array('rec'=>$this->data->currentArr, 'origRec'=>$origArr));

						// Hook: confirmRegistrationClass_postProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
							$hookObj->confirmRegistrationClass_postProcess($origArr, $this);
						}
					} 
				}

				// Outputting template
				if ($this->data->theTable == 'fe_users' && ($this->data->feUserData['sFK'] == 'APPROVE' || $this->data->feUserData['sFK'] == 'ENTER')) {
					$markerArray = $this->marker->getArray();
					$this->marker->addMd5LoginMarkers($markerArray);
					$this->marker->setArray($markerArray);
					if($this->useMd5Password) {
						$origArr['password'] = '';
					}
				}
				$setfixedSufffix = $this->data->feUserData['sFK'];
				if ($this->conf['enableAdminReview'] && $this->data->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
					$setfixedSufffix .= '_REVIEW';
				}
				$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX . 'OK_' . $setfixedSufffix . '###', $origArr);
				if (!$content) {
					$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX .'OK###', $origArr);
				}
				// Compiling email
				$this->data->dataArr = $origArr;
				$this->email->compile(
					SETFIXED_PREFIX.$setfixedSufffix,
					array($origArr),
					$origArr[$this->conf['email.']['field']],
					$markContentArray,
					$this->getCmd(),
					$this->getCmdKey(),
					$this->data->templateCode,
					$this->conf['setfixed.']
				);

				if ($this->data->theTable == 'fe_users') { 
						// If applicable, send admin a request to review the registration request
					if ($this->conf['enableAdminReview'] && $this->data->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
						$this->email->compile(
							SETFIXED_PREFIX.'REVIEW',
							array($origArr),
							$this->conf['email.']['admin'],
							$this->conf['setfixed.'],
							$this->getCmd(),
							$this->getCmdKey(),
							$this->data->templateCode
						);
					}

						// Auto-login on confirmation
					if ($this->conf['enableAutoLoginOnConfirmation'] && ($this->data->feUserData['sFK'] == 'APPROVE' || $this->data->feUserData['sFK'] == 'ENTER')) {
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
		$cmdKey = $this->getCmdKey();

		return ($this->conf[$cmdKey.'.']['preview'] && $this->data->feUserData['preview']);
	}	// isPreview



}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']);
}
?>
