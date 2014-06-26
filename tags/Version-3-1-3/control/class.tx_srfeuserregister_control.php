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
 * display functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2007@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

class tx_srfeuserregister_control {
	public $langObj;
	public $confObj;
	public $display;
	public $data;
	public $marker;
	public $auth;
	public $email;
	public $tca;
	public $requiredArray; // List of required fields
	public $controlData;
	public $setfixedObj;
		// Commands that may be processed when no user is logged in
	public $noLoginCommands = array('create', 'invite', 'setfixed', 'infomail', 'login');


	public function init (
		$langObj,
		$cObj,
		$controlData,
		$display,
		$marker,
		$email,
		$tca,
		$setfixedObj,
		$urlObj,
		$conf,
		$pibaseObj
	) {
		
		$this->langObj = $langObj;
		$this->display = $display;
		$this->marker = $marker;
		$this->email = $email;
		$this->tca = $tca;
		$this->setfixedObj = $setfixedObj;
		$this->urlObj = $urlObj;
			// Retrieve the extension key
		$extKey = $controlData->getExtKey();
		// Get the command as set in piVars
		$cmd = $controlData->getCmd();
		// If not set, get the command from the flexform
		if (!isset($cmd) || $cmd === '') {
			// Check the flexform
			$pibaseObj->pi_initPIflexForm();
			$cmd = $pibaseObj->pi_getFFvalue($pibaseObj->cObj->data['pi_flexform'], 'display_mode', 'sDEF');
			if (!isset($cmd) || $cmd === '') {
				$cmd = $conf['defaultCODE'];
			}
			$cmd = $cObj->caseshift($cmd, 'lower');
		}
		$controlData->setCmd($cmd);
	}

	/* write the global $conf only here */
	public function init2 (
		$confObj,
		$theTable,
		$controlData,
		$data,
		&$adminFieldList
	) {
		$this->confObj = $confObj;
		$conf = $this->confObj->getConf();
		$this->data = $data;

		$tablesObj = t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');
		$origArray = array();
		$extKey = $controlData->getExtKey();
		$cmd = $controlData->getCmd();
		$dataArray = $this->data->getDataArray();
		$feUserdata = $controlData->getFeUserData();

		$theUid = ($dataArray['uid'] ? $dataArray['uid'] : ($feUserdata['rU'] ? $feUserdata['rU'] : (!in_array($cmd, $this->noLoginCommands) ? $GLOBALS['TSFE']->fe_user->user['uid'] : 0 )));

		if ($theUid) {
			$this->data->setRecUid($theUid);
			$newOrigArray = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $theUid);

			if (isset($newOrigArray) && is_array($newOrigArray)) {
				$this->tca->modifyRow($theTable, $newOrigArray, TRUE);
				$origArray = $newOrigArray;
			}
		}
			// Set the command key
		$cmdKey = '';
		if ($cmd == 'edit' || $cmd == 'invite' || $cmd == 'password' || $cmd == 'infomail') {
			$cmdKey = $cmd;
		} else {
			if (
				(
					$cmd == '' ||
					$cmd == 'setfixed'
				) &&
				(
					($theTable != 'fe_users' || $theUid == $GLOBALS['TSFE']->fe_user->user['uid']) &&
					count($origArray)
				)
			) {
				$cmdKey = 'edit';
			} else {
				$cmdKey = 'create';
			}
		}
		$controlData->setCmdKey($cmdKey);

		if (!$theUid) {
			if (!is_array($dataArray) || !count($dataArray)) {
				$dataArray = $this->data->defaultValues($cmdKey);
				$this->data->setDataArray($dataArray);
			}
		}
		$this->data->setOrigArray($origArray);

		// Setting the list of fields allowed for editing and creation
		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 6001000) {
			$this->tca->loadTcaAdditions();
		}
		$fieldlist = implode(',', array_diff(array_keys($GLOBALS['TCA'][$theTable]['columns']), array('felogin_forgotHash','felogin_redirectPid','lastlogin','lockToDomain','starttime','endtime','token','TSconfig')));
		$this->data->setFieldList($fieldlist);

		if (trim($conf['addAdminFieldList'])) {
			$adminFieldList .= ',' . trim($conf['addAdminFieldList']);
		}
		$adminFieldList = implode(',', array_intersect(explode(',', $fieldlist), t3lib_div::trimExplode(',', $adminFieldList, 1)));
		$this->data->setAdminFieldList($adminFieldList);

		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1), array('module_sys_dmail_category,module_sys_dmail_newsletter')));
			$conf[$cmdKey . '.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['required'], 1), array('module_sys_dmail_category, module_sys_dmail_newsletter')));
		}

		$fieldConfArray = array('fields', 'required');
		foreach ($fieldConfArray as $k => $v) {
			// make it ready for t3lib_div::inList which does not yet allow blanks
			$conf[$cmdKey . '.'][$v] = implode(',',  array_unique(t3lib_div::trimExplode(',', $conf[$cmdKey . '.'][$v])));
		}

		$theTable = $controlData->getTable();
		if ($theTable == 'fe_users') {
			// When not in edit mode, add username to lists of fields and required fields unless explicitly disabled
			if (empty($conf[$cmdKey.'.']['doNotEnforceUsername'])) {
				if ($cmdKey != 'edit' && $cmdKey != 'password') {
					$conf[$cmdKey . '.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'] . ',username', 1)));
					$conf[$cmdKey . '.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['required'] . ',username', 1)));
				}
			}
			// When in edit mode, remove password from required fields
			if ($cmdKey == 'edit') {
				$conf[$cmdKey . '.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['required'], 1), array('password')));
			}
			if ($conf[$cmdKey . '.']['generateUsername'] || $cmdKey == 'password') {
				$conf[$cmdKey . '.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1), array('username')));
			}

			if ($cmdKey == 'invite') {
				$conf[$cmdKey . '.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1), array('password')));
				$conf[$cmdKey . '.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['required'], 1), array('password')));
			}

			if ($conf[$cmdKey . '.']['useEmailAsUsername']) {
				$conf[$cmdKey . '.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1), array('username')));
				if ($cmdKey == 'create' || $cmdKey == 'invite') {
					$conf[$cmdKey . '.']['fields'] = implode(',', t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'] . ',email', 1));
					$conf[$cmdKey . '.']['required'] = implode(',', t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['required'] . ',email', 1));
				}
				if (
					($cmdKey == 'edit' || $cmdKey == 'password') &&
					$controlData->getSetfixedEnabled()
				) {
					$conf[$cmdKey . '.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1), array('email')));
				}
			}
			$userGroupObj = $addressObj->getFieldObj('usergroup');

			if (is_object($userGroupObj)) {
				$userGroupObj->modifyConf($conf, $cmdKey);
			}

			if ($cmdKey == 'invite') {
				if ($conf['enableAdminReview']) {
					if (
						$controlData->getSetfixedEnabled() &&
						is_array($conf['setfixed.']['ACCEPT.']) &&
						is_array($conf['setfixed.']['APPROVE.'])
					) {
						$conf['setfixed.']['APPROVE.'] = $conf['setfixed.']['ACCEPT.'];
					}
				}
			}
			if ($cmdKey == 'create') {
				if ($conf['enableAdminReview'] && !$conf['enableEmailConfirmation']) {
					$conf['create.']['defaultValues.']['disable'] = '1';
					$conf['create.']['overrideValues.']['disable'] = '1';
				}
			}
				// Infomail does not apply to fe_users
			$conf['infomail'] = 0;
		}
			// Honour Address List (tt_address) configuration setting
		if ($theTable == 'tt_address' && t3lib_extMgm::isLoaded('tt_address') && isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address'])) {
			$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address']);
			if (is_array($extConf) && $extConf['disableCombinedNameField'] == '1') {
				$conf[$cmdKey . '.']['fields'] = t3lib_div::rmFromList('name', $conf[$cmdKey . '.']['fields']);
			}
		}
		// Adjust some evaluation settings
		// TODO: Fix scope issue: unsetting $conf entry here has no effect
		if (is_array($conf[$cmdKey . '.']['evalValues.'])) {
			// Do not evaluate any password when inviting
			if ($cmdKey == 'invite') {
				unset($conf[$cmdKey . '.']['evalValues.']['password']);
			}
			// Do not evaluate the username if it is generated or if email is used
			if (
				$conf[$cmdKey . '.']['useEmailAsUsername'] ||
				($conf[$cmdKey . '.']['generateUsername'] && $cmdKey != 'edit' && $cmdKey != 'password')
			) {
				unset($conf[$cmdKey . '.']['evalValues.']['username']);
			}
		}
		$this->confObj->setConf($conf);

		// Setting requiredArr to the fields in "required" fields list intersected with the total field list in order to remove invalid fields.
		$requiredArray = array_intersect(
			t3lib_div::trimExplode(
				',',
				$conf[$cmdKey . '.']['required'],
				1
			),
			t3lib_div::trimExplode(
				',',
				$conf[$cmdKey . '.']['fields'],
				1
			)
		);
		$controlData->setRequiredArray($requiredArray);
	}

	/**
	* All processing of the codes is done here
	*
	* @param string  command to execute
	* @param string message if an error has occurred
	* @return string  text to display
	*/
	public function doProcessing (
		$cObj,
		$langObj,
		$controlData,
		$theTable,
		$cmd,
		$cmdKey,
		$origArray,
		$dataArray,
		$templateCode,
		&$error_message
	) {
		$conf = $this->confObj->getConf();

		$prefixId = $controlData->getPrefixId();
		$controlData->setMode(MODE_NORMAL);

			// Commands with which the data will not be saved by $this->data->save
		$noSaveCommands = array('infomail', 'login', 'delete');
		$uid = $this->data->getRecUid();

		$securedArray = array();
			// Check for valid token
		if (
			!$controlData->isTokenValid() ||
			(
				$theTable == 'fe_users' &&
				(
					!$GLOBALS['TSFE']->loginUser ||
					($uid > 0 && $GLOBALS['TSFE']->fe_user->user['uid'] != $uid)
				) &&
				!in_array($cmd, $this->noLoginCommands)
			)
		) {
			$controlData->setCmd($cmd);
			$origArray = array();
			$this->data->setOrigArray($origArray);
			$this->data->resetDataArray();
			$finalDataArray = $dataArray;
		} else if ($this->data->bNewAvailable()) {
			if ($theTable == 'fe_users') {
				$securedArray = $controlData->readSecuredArray();
			}
			if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 6002000) {
				$finalDataArray = t3lib_div::array_merge_recursive_overrule($dataArray, $securedArray);
			} else {
				$finalDataArray = $dataArray;
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($finalDataArray, $securedArray);
			}
		} else {
			$finalDataArray = $dataArray;
		}
		$submitData = $controlData->getFeUserData('submit');

		if ($submitData != '') {
			$bSubmit = TRUE;
			$controlData->setSubmit(TRUE);
		}

		$doNotSaveData = $controlData->getFeUserData('doNotSave');
		if ($doNotSaveData != '') {
			$bDoNotSave = TRUE;
			$controlData->setDoNotSave(TRUE);
			$controlData->clearSessionData();
		}

		$markerArray = $this->marker->getArray();

			// Evaluate incoming data
		if (is_array($finalDataArray) && count($finalDataArray) && !in_array($cmd, $noSaveCommands)) {
			$this->data->setName($finalDataArray, $cmdKey, $theTable);
			$this->data->parseValues($theTable, $finalDataArray, $origArray, $cmdKey);
			$this->data->overrideValues($finalDataArray, $cmdKey);

			if (
				$bSubmit ||
				$bDoNotSave ||
				$controlData->getFeUserData('linkToPID')
			) {
					// A button was clicked on
				$evalErrors = $this->data->evalValues(
					$theTable,
					$finalDataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$controlData->getRequiredArray()
				);
					// If the two password fields are not equal, clear session data
				if (
					is_array($evalErrors['password']) &&
					in_array('twice', $evalErrors['password'])
				) {
					$controlData->clearSessionData();
				}
				if ($conf['evalFunc'] && is_array($conf['evalFunc.'])) {
					$this->marker->setArray($markerArray);
					$funcConf = $conf['evalFunc.'];
					$funcConf['parentObj'] = $this;
					$userProcessedDataArray = $GLOBALS['TSFE']->cObj->callUserFunction($conf['evalFunc'], $funcConf, $finalDataArray);
					if (is_array($userProcessedDataArray)) {
						$finalDataArray = $userProcessedDataArray;
					}
					$markerArray = $this->marker->getArray();
				}
			} else {
					// This is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
					// You come here after a click on the text "Not a member yet? click here to register."
					// We are going to redisplay
				$evalErrors = $this->data->evalValues(
					$theTable,
					$finalDataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					$controlData->getRequiredArray()
				);

					// If the two password fields are not equal, clear session data
				if (
					is_array($evalErrors['password']) &&
					in_array('twice', $evalErrors['password'])
				) {
					$controlData->clearSessionData();
				}
				$this->marker->setArray($markerArray);
				$controlData->setFailure('submit'); // internal error simulation needed in order not to save in the next step
			}
			$this->data->setUsername($theTable, $finalDataArray, $cmdKey);
			$this->data->setDataArray($finalDataArray);

			if (
				$controlData->getFailure() == '' &&
				!$controlData->getFeUserData('preview') &&
				!$bDoNotSave
			) {
				$password = '';
				if ($theTable == 'fe_users') {
					$controlData->getStorageSecurity()->initializeAutoLoginPassword($finalDataArray);
						// We generate an interim password in the case of an invitation
					if ($cmdKey == 'invite') {
						$controlData->generatePassword($finalDataArray);
					}

						// If inviting or if auto-login will be required on confirmation, we store an encrypted version of the password
					if (
						$cmdKey == 'invite' ||
						$cmdKey == 'create' &&
							(
								$conf['enableAutoLoginOnConfirmation'] &&
								!$conf['enableAutoLoginOnCreate']
							)
					) {
						$controlData->getStorageSecurity()->encryptPasswordForAutoLogin($finalDataArray);
					}
					$password = $controlData->readPasswordForStorage();
				}
				$extKey = $controlData->getExtKey();
				$newDataArray = array();

				$theUid = $this->data->save(
					$theTable,
					$finalDataArray,
					$origArray,
					$controlData->readToken(),
					$newDataArray,
					$cmd,
					$cmdKey,
					$controlData->getPid(),
					$password,
					$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess']
				);

				if ($newDataArray) {
					$dataArray = $newDataArray;
					$dataArray['auto_login_key'] = $finalDataArray['auto_login_key'];
				}

				if ($this->data->getSaved()) {
					$controlData->clearSessionData();
				}
			}
		} else if ($cmd == 'infomail') {
			if ($bSubmit) {
				$fetch = $controlData->getFeUserData('fetch');
				$finalDataArray['email'] = $fetch;
				$evalErrors = $this->data->evalValues(
					$theTable,
					$finalDataArray,
					$origArray,
					$markerArray,
					$cmdKey,
					array()
				);
			}
			$controlData->setRequiredArray(array());
			$this->marker->setArray($markerArray);
			$controlData->setFeUserData(0, 'preview');
		} else {
			$this->marker->setNoError($cmdKey, $markerArray);
			$this->marker->setArray($markerArray);
			if ($cmd != 'delete') {
				$controlData->setFeUserData(0, 'preview'); // No preview if data is not received and deleted
			}
		}

		if ($controlData->getFailure() != '') {
			$controlData->setFeUserData(0, 'preview');
		}

			// No preview flag if a evaluation failure has occured
		if ($controlData->getFeUserData('preview')) {
			$this->marker->setPreviewLabel('_PREVIEW');
			$controlData->setMode(MODE_PREVIEW);
		}
			// If data is submitted, we take care of it here.
		if (
			$cmd == 'delete' &&
			!$controlData->getFeUserData('preview') &&
			!$bDoNotSave
		) {
			// Delete record if delete command is set + the preview flag is NOT set.
			if (empty($origArray)) {
				$origArray = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $uid);
				if (empty($origArray)) {
					$origArray = array();
				}
			}
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
				&& $conf['enableAdminReview']
				&& ($conf['enableEmailConfirmation'] || $conf['infomail'])
			) {
				$bCustomerConfirmsMode = TRUE;
			}
				// This is the case where the user or admin has to confirm
				// $conf['enableEmailConfirmation'] ||
				// ($this->theTable == 'fe_users' && $conf['enableAdminReview']) ||
				// $conf['setfixed']
			$bSetfixed = $controlData->getSetfixedEnabled();
				// This is the case where the user does not have to confirm, but has to wait for admin review
				// This applies only on create ($bDefaultMode) and to fe_users
				// $bCreateReview implies $bSetfixed
			$bCreateReview =
				($theTable == 'fe_users') &&
				!$conf['enableEmailConfirmation'] &&
				$conf['enableAdminReview'];
			$key =
				$this->display->getKeyAfterSave(
					$cmd,
					$cmdKey,
					$bCustomerConfirmsMode,
					$bSetfixed,
					$bCreateReview
				);

			$errorContent =
				$this->display->afterSave(
					$conf,
					$cObj,
					$langObj,
					$controlData,
					$this->tca,
					$this->marker,
					$this->data,
					$this->setfixedObj,
					$theTable,
					$prefixId,
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
				$errorCode = '';

				if ($conf['enableAdminReview'] && $bDefaultMode && !$bCustomerConfirmsMode) {
						// Send admin the confirmation email
						// The user will not confirm in this mode
					$errorContent = $this->email->compile(
						SETFIXED_PREFIX . 'REVIEW',
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$this->display,
						$this->setfixedObj,
						$theTable,
						$prefixId,
						array($dataArray),
						array($origArray),
						$securedArray,
						$conf['email.']['admin'],
						$markerArray,
						'setfixed',
						$cmdKey,
						$templateCode,
						$errorFieldArray,
						$conf['setfixed.'],
						$errorCode
					);
				} else if (
					$cmdKey == 'create' ||
					$cmdKey == 'invite' ||
					$conf['email.']['EDIT_SAVED'] ||
					$conf['email.']['DELETE_SAVED']
				) {
					$emailField = $conf['email.']['field'];
					$recipient =
						(
							isset($finalDataArray) &&
							is_array($finalDataArray) &&
							$finalDataArray[$emailField]
						) ?
						$finalDataArray[$emailField] :
						$origArray[$emailField];

					// Send email message(s)
					$errorContent = $this->email->compile(
						$key,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$this->display,
						$this->setfixedObj,
						$theTable,
						$prefixId,
						array($dataArray),
						array($origArray),
						$securedArray,
						$recipient,
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode,
						$errorFieldArray,
						$conf['setfixed.'],
						$errorCode
					);
				}

				if (is_array($errorCode)) {
					$errorText = $langObj->getLL($errorCode['0']);
					$errorContent = sprintf($errorText, $errorCode['1']);
				}
			}

			if ($errorContent == '') {	// success case
				$origGetFeUserData = t3lib_div::_GET($prefixId);
				$bDeleteRegHash = TRUE;

					// Link to on edit save
					// backURL may link back to referring process
				if ($theTable == 'fe_users' &&
					($cmd == 'edit' || $cmd == 'password') &&
					($controlData->getBackURL() || ($conf['linkToPID'] && ($controlData->getFeUserData('linkToPID') || !$conf['linkToPIDAddButton']))) ) {
					$destUrl = ($controlData->getBackURL() ? $controlData->getBackURL() : $cObj->getTypoLink_URL($conf['linkToPID'] . ',' . $GLOBALS['TSFE']->type));
					header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
					exit;
				}

					// Auto-login on create
				if (
					$theTable == 'fe_users' &&
					$cmd == 'create' &&
					!$controlData->getSetfixedEnabled() &&
					$conf['enableAutoLoginOnCreate']
				) {
					$loginSuccess =
						$this->login(
							$conf,
							$langObj,
							$controlData,
							$finalDataArray
						);

					if ($loginSuccess) {
							// Login was successful
						exit;
					} else {
							// Login failed... should not happen...
							// If it does, a login form will be displayed as if auto-login was not configured
						$content = '';
					}
				}
			} else { // error case
				$content = $errorContent;
			}
		} else if ($this->data->getError()) {
				// If there was an error, we return the template-subpart with the error message
			$templateCode = $cObj->getSubpart($templateCode, $this->data->getError());
			$this->marker->addLabelMarkers(
				$markerArray,
				$conf,
				$cObj,
				$controlData->getExtKey(),
				$theTable,
				$finalDataArray,
				$this->data->getOrigArray(),
				$securedArray,
				array(),
				$controlData->getRequiredArray(),
				$this->data->getFieldList(),
				$GLOBALS['TCA'][$theTable]['columns'],
				FALSE
			);
			$this->marker->setArray($markerArray);
			$content = $cObj->substituteMarkerArray($templateCode, $markerArray);
		} else {
				// Finally, there has been no attempt to save.
				// That is either preview or just displaying an empty or not correctly filled form
			$this->marker->setArray($markerArray);
			$token = $controlData->readToken();
			if ($cmd == '' && $controlData->getFeUserData('preview')) {
				$cmd = $cmdKey;
			}

			switch ($cmd) {
				case 'setfixed':
					if ($conf['infomail']) {
						$controlData->setSetfixedEnabled(1);
					}
					$feuData = $controlData->getFeUserData();
					if (is_array($origArray)) {
						$origArray = $this->data->parseIncomingData($origArray, FALSE);
					}

					$content = $this->setfixedObj->processSetFixed(
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$theTable,
						$prefixId,
						$uid,
						$cmdKey,
						$markerArray,
						$this->display,
						$this->email,
						$templateCode,
						$finalDataArray,
						$origArray,
						$securedArray,
						$this,
						$feuData,
						$token
					);
					break;
				case 'infomail':
					$this->marker->addGeneralHiddenFieldsMarkers(
						$markerArray,
						$cmd,
						$token
					);
					if ($conf['infomail']) {
						$controlData->setSetfixedEnabled(1);
					}
					if (is_array($origArray)) {
						$origArray = $this->data->parseIncomingData($origArray, FALSE);
					}
					$errorCode = '';
					$content = $this->email->sendInfo(
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$this->display,
						$this->setfixedObj,
						$theTable,
						$prefixId,
						$origArray,
						$securedArray,
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode,
						$controlData->getFailure(),
						$errorCode
					);

					if (is_array($errorCode)) {
						$content = $langObj->getLL($errorCode['0']);
					}
					break;
				case 'delete':
					$this->marker->addGeneralHiddenFieldsMarkers(
						$markerArray,
						$cmd,
						$token
					);
					$content = $this->display->deleteScreen(
						$markerArray,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$theTable,
						$finalDataArray,
						$origArray,
						$securedArray,
						$token
					);
					break;
				case 'edit':
				case 'password':
					$this->marker->addGeneralHiddenFieldsMarkers(
						$markerArray,
						$cmd,
						$token
					);
					$content = $this->display->editScreen(
						$markerArray,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$theTable,
						$prefixId,
						$finalDataArray,
						$origArray,
						$securedArray,
						$cmd,
						$cmdKey,
						$controlData->getMode(),
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
					$content = $this->display->createScreen(
						$markerArray,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$cmd,
						$cmdKey,
						$controlData->getMode(),
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
					$this->marker->addGeneralHiddenFieldsMarkers(
						$markerArray,
						$cmd,
						$token
					);
					$content = $this->display->createScreen(
						$markerArray,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$this->tca,
						$this->marker,
						$this->data,
						$cmd,
						$cmdKey,
						$controlData->getMode(),
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
				($cmd != 'setfixed' || $cmdKey != 'edit' || $cmdKey != 'password') &&
				!$errorContent &&
				!$controlData->getFeUserData('preview')
			) {
				$bDeleteRegHash = TRUE;
			}
		}

		if (
			$bDeleteRegHash &&
			$controlData->getValidRegHash()
		) {
			$regHash = $controlData->getRegHash();
			$controlData->deleteShortUrl($regHash);
		}

		return $content;
	}

	/**
	 * Perform user login and redirect to configured url, if any
	 *
	 * @param array	$row: incoming setfixed parameters
	 * @param boolen $redirect: whether to redirect after login or not
	 * @return boolean TRUE, if login was successful, FALSE otherwise
	 */
	public function login (
		$conf,
		$langObj,
		$controlData,
		array $row,
		$redirect = TRUE
	) {
		$result = TRUE;
			// Log the user in
		$loginData = array(
			'uname' => $row['username'],
			'uident' => $row['password'],
			'uident_text' => $row['password'],
			'status' => 'login',
		);

		// Check against configured pid (defaulting to current page)
		$GLOBALS['TSFE']->fe_user->checkPid = TRUE;
		$GLOBALS['TSFE']->fe_user->checkPid_value = $controlData->getPid();

		// Get authentication info array
		$authInfo = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
		// Get user info
		$user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($authInfo['db_user'], $loginData['uname']);
		if (is_array($user)) {
			// Get the appropriate authentication service
			$authServiceObj = t3lib_div::makeInstanceService('auth', 'authUserFE');
			// Check authentication
			if (is_object($authServiceObj)) {
				$ok = $authServiceObj->compareUident($user, $loginData);
				if ($ok) {
					// Login successfull: create user session
					$GLOBALS['TSFE']->fe_user->createUserSession($user);
					$GLOBALS['TSFE']->initUserGroups();
					$GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
					$GLOBALS['TSFE']->loginUser = 1;

					// Delete regHash
					if (
						$controlData->getValidRegHash()
					) {
						$regHash = $controlData->getRegHash();
						$controlData->deleteShortUrl($regHash);
					}

					if ($redirect) {
							// Redirect to configured page, if any
						$redirectUrl = $controlData->readRedirectUrl();
						if (!$redirectUrl) {
							$redirectUrl = trim($conf['autoLoginRedirect_url']);
						}
						if (!$redirectUrl) {
							if ($conf['loginPID']) {
								$redirectUrl = $this->urlObj->get('', $conf['loginPID']);
							} else {
								$redirectUrl = $controlData->getSiteUrl();
							}
						}
						header('Location: ' . t3lib_div::locationHeaderUrl($redirectUrl));
					}
				} else {
						// Login failed...
					$controlData->clearSessionData(FALSE);
					$result = FALSE;
				}
			} else {
					// Required authentication service not available
				$message = $langObj->getLL('internal_required_authentication_service_not_available');
				t3lib_div::sysLog($message, $controlData->getExtKey(), t3lib_div::SYSLOG_SEVERITY_ERROR);
				$controlData->clearSessionData(FALSE);
				$result = FALSE;
			}
		} else {
				// No enabled user of the given name
			$controlData->clearSessionData(FALSE);
			$result = FALSE;
		}

		return $result;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']);
}

?>