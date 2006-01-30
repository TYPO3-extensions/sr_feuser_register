<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skaarhoj (kasper@typo3.com)
*  (c) 2004, 2005, 2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
*
* Front End creating/editing/deleting records authenticated by fe_user login.
* A variant restricted to front end user self-registration and profile maintenance, with a number of enhancements (see the manual).
*
* @author Kasper Skaarhoj <kasper@typo3.com>
* @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
*
* TYPO3 CVS ID: $Id$
*
*/

require_once(PATH_tslib.'class.tslib_pibase.php');
	// To get the pid language overlay:
require_once(PATH_t3lib.'class.t3lib_page.php');
require_once(t3lib_extMgm::extPath('sr_static_info').'pi1/class.tx_srstaticinfo_pi1.php');
require_once(t3lib_extMgm::extPath('sr_feuser_register').'pi1/class.tx_srfeuserregister_pi1_t3lib_htmlmail.php');
require_once(t3lib_extMgm::extPath('sr_feuser_register').'pi1/class.tx_srfeuserregister_pi1_urlvalidator.php');
require_once(t3lib_extMgm::extPath('sr_feuser_register').'pi1/class.tx_srfeuserregister_pi1_adodb_time.php');
	// For use with images:
require_once (PATH_t3lib.'class.t3lib_basicfilefunc.php');
	// For translating items from other extensions
require_once (t3lib_extMgm::extPath('lang').'lang.php');

class tx_srfeuserregister_pi1 extends tslib_pibase {
		var $cObj;
		var $conf = array();
		var $site_url = '';
		var $theTable = 'fe_users';
		var $TCA = array();
		 
		var $feUserData = array();
		var $dataArr = array();
		var $currentArr = array();
		var $failureMsg = array();
		var $thePid = 0;
		var $thePidTitle;
		var $markerArray = array();
		var $templateCode = '';
		 
		var $registerPID;
		var $editPID;
		var $confirmPID;
		var $confirmType;
		var $loginPID;
		var $cmd;
		var $setfixedEnabled = 1;
		var $HTMLMailEnabled = 1;
		var $incomingData = false;
		var $previewLabel = '';
		var $backURL;
		var $recUid;
		var $failure = 0; // is set if data did not have the required fields set.
		var $missing = array(); // array of required missing fields
		var $inError = array(); // array of fields with eval errors other than absence
		var $error = '';
		var $saved = 0; // is set if data is saved
		var $nc = ''; // "&no_cache=1" if you want that parameter sent.
		var $additionalUpdateFields = '';
		var $emailMarkPrefix = 'EMAIL_TEMPLATE_';
		var $emailMarkAdminSuffix = '_ADMIN';
		var $savedSuffix = '_SAVED';
		var $setfixedPrefix = 'SETFIXED_';
		var $emailMarkHTMLSuffix = '_HTML';
		var $charset = 'iso-8859-1'; // charset to be used in emails and form conversions
		var $codeLength;
		var $cmdKey;
		var $fieldList; // List of fields from fe_admin_fieldList
		var $requiredArr; // List of required fields
		var $adminFieldList = 'name,disable,usergroup';
		var $fileFunc = ''; // Set to a basic_filefunc object for file uploads

		function initId() {
			$this->prefixId = 'tx_srfeuserregister_pi1';  // Same as class name
			$this->scriptRelPath = 'pi1/class.tx_srfeuserregister_pi1.php'; // Path to this script relative to the extension dir.
			$this->extKey = 'sr_feuser_register';  // The extension key.
			$this->theTable = 'fe_users';
			$this->adminFieldList = 'username,password,name,disable,usergroup,by_invitation';
		}
		 
		function main($content, $conf) {
				// plugin initialization
			$this->initId();
			$this->conf = $conf;
			$this->pi_loadLL();
			$this->pi_USER_INT_obj = 1;
			$this->pi_setPiVarDefaults();
			$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
			
				// get the table definition
			$GLOBALS['TSFE']->includeTCA();
			$GLOBALS['TCA'][$this->theTable]['columns']['image']['config']['uploadfolder'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['uploadFolder'] ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['uploadFolder'] :  $GLOBALS['TCA'][$this->theTable]['columns']['image']['config']['uploadfolder'];
			$this->TCA = $GLOBALS['TCA'][$this->theTable];
			
				// prepare for character set settings and conversions
			$this->typoVersion = t3lib_div::int_from_ver($GLOBALS['TYPO_VERSION']);
			if ($this->typoVersion >= 3006000 ) {
				if ($GLOBALS['TSFE']->metaCharset) {
					$this->charset = $GLOBALS['TSFE']->csConvObj->parse_charset($GLOBALS['TSFE']->metaCharset);
				}
			}
			
				// prepare for handling dates befor 1970
			$this->adodbTime = t3lib_div::makeInstance('tx_srfeuserregister_pi1_adodb_time');
			
				// set the pid's and the title language overlay
			$this->pidRecord = t3lib_div::makeInstance('t3lib_pageSelect');
			$this->pidRecord->init(0);
			$this->pidRecord->sys_language_uid = (trim($GLOBALS['TSFE']->config['config']['sys_language_uid'])) ? trim($GLOBALS['TSFE']->config['config']['sys_language_uid']) : 0;
			$this->thePid = intval($this->conf['pid']) ? strval(intval($this->conf['pid'])) : $GLOBALS['TSFE']->id;
			$row = $this->pidRecord->getPage($this->thePid);
			$this->thePidTitle = trim($this->conf['pidTitleOverride']) ? trim($this->conf['pidTitleOverride']) : $row['title'];
			$this->registerPID = intval($this->conf['registerPID']) ? strval(intval($this->conf['registerPID'])) : $GLOBALS['TSFE']->id;
			$this->editPID = intval($this->conf['editPID']) ? strval(intval($this->conf['editPID'])) : $GLOBALS['TSFE']->id;
			$this->infomailPID = intval($this->conf['infomailPID']) ? strval(intval($this->conf['infomailPID'])) : $this->registerPID;
			$this->confirmPID = intval($this->conf['confirmPID']) ? strval(intval($this->conf['confirmPID'])) : $this->registerPID;
			$this->confirmInvitationPID = intval($this->conf['confirmInvitationPID']) ? strval(intval($this->conf['confirmInvitationPID'])) : $this->confirmPID;			
			$this->confirmType = intval($this->conf['confirmType']) ? strval(intval($this->conf['confirmType'])) : $GLOBALS['TSFE']->type;
			if ($this->conf['confirmType'] == '0' ) {
				$this->confirmType = '0';
			};
			$this->loginPID = intval($this->conf['loginPID']) ? strval(intval($this->conf['loginPID'])) : $GLOBALS['TSFE']->id;
			 
				// Initialise static info library
			$this->staticInfo = t3lib_div::makeInstance('tx_srstaticinfo_pi1');
			$this->staticInfo->init();
			 
				// Initialise fileFunc object
			$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
			
				// Get post parameters
			if ($this->typoVersion >= 3006000 ) {
				$this->feUserData = t3lib_div::_GP($this->prefixId);
				$fe = t3lib_div::_GP('FE');
					// Establishing compatibility with Direct Mail extension
				$this->feUserData['rU'] = t3lib_div::_GP('rU') ? t3lib_div::_GP('rU') : $this->feUserData['rU'];
				$this->feUserData['aC'] = t3lib_div::_GP('aC') ? t3lib_div::_GP('aC') : $this->feUserData['aC'];
				$this->feUserData['cmd'] = t3lib_div::_GP('cmd') ? t3lib_div::_GP('cmd') : $this->feUserData['cmd'];
				$this->feUserData['sFK'] = t3lib_div::_GP('sFK') ? t3lib_div::_GP('sFK') : $this->feUserData['sFK'];
			} else {
				$this->feUserData = t3lib_div::slashArray(t3lib_div::GPvar($this->prefixId), 'strip');
				$fe = t3lib_div::GPvar('FE');
					// Establishing compatibility with Direct Mail extension
				$this->feUserData['rU'] = t3lib_div::GPvar('rU') ? t3lib_div::GPvar('rU') : $this->feUserData['rU'];
				$this->feUserData['aC'] = t3lib_div::GPvar('aC') ? t3lib_div::GPvar('aC') : $this->feUserData['aC'];
				$this->feUserData['cmd'] = t3lib_div::GPvar('cmd') ? t3lib_div::GPvar('cmd') : $this->feUserData['cmd'];
				$this->feUserData['sFK'] = t3lib_div::GPvar('sFK') ? t3lib_div::GPvar('sFK') : $this->feUserData['sFK'];
			};
			$this->dataArr = $fe[$this->theTable];
			$this->incomingData = is_array($this->dataArr);
			
			$this->backURL = rawurldecode($this->feUserData['backURL']);
			$this->recUid = intval($this->feUserData['rU']);
			$this->authCode = $this->feUserData['aC'];
			
				// Setting cmd and various switches
			if ( $this->theTable == 'fe_users' && $this->feUserData['cmd'] == 'login' ) {
				unset($this->feUserData['cmd']);
			}
			$this->cmd = $this->feUserData['cmd'] ? $this->feUserData['cmd'] : strtolower($this->cObj->data['select_key']);
			
			// <Franz Holzinger added flexform support>
			if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['useFlexforms'] && t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
			 		// FE BE library for flexform functions
				require_once(PATH_BE_fh_library.'lib/class.tx_fhlibrary_flexform.php');
					// check the flexform
				$this->pi_initPIflexForm();
				$this->cmd = tx_fhlibrary_flexform::getSetupOrFFvalue(
					$this, 
					$this->cmd, 
					'',
					$this->conf['defaultCode'], 
					$this->cObj->data['pi_flexform'], 
					'display_mode',
					$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['useFlexforms']
				);
			}else {
				$this->cmd = $this->cmd ? $this->cmd : strtolower($this->conf['defaultCODE']);
			}
			// <//Franz Holzinger added flexform support>
			if ($this->cmd == 'edit' || $this->cmd == 'invite') {
				$this->cmdKey = $this->cmd;
			} else {
				$this->cmdKey = 'create';
			}
			if ($this->conf['setfixed'] != 1) {
				$this->setfixedEnabled = 0;
			}
			if ($this->conf['email.'][HTMLMail] != 1) {
				$this->HTMLMailEnabled = 0;
			}
			
				// Initialise password encryption
			if ($this->theTable == 'fe_users' && t3lib_extMgm::isLoaded('kb_md5fepw')) {
				require_once(t3lib_extMgm::extPath('kb_md5fepw').'class.tx_kbmd5fepw_funcs.php');
				$this->useMd5Password = true;
				$this->conf['enableAutoLoginOnConfirmation'] = false;
			}
			
				// Setting the list of fields allowed for editing and creation.
			$this->fieldList = implode(',', t3lib_div::trimExplode(',', $GLOBALS['TCA'][$this->theTable]['feInterface']['fe_admin_fieldList'], 1));
			$this->adminFieldList = implode(',', array_intersect( explode(',', $this->fieldList), t3lib_div::trimExplode(',', $this->adminFieldList, 1)));
			if (trim($this->conf['addAdminFieldList'])) {
				$this->adminFieldList .= ',' . trim($this->conf['addAdminFieldList']);
			}
			
			if (!t3lib_extMgm::isLoaded('direct_mail')) {
				$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('module_sys_dmail_category,module_sys_dmail_html')));
				$this->conf[$this->cmdKey.'.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('module_sys_dmail_category,module_sys_dmail_html')));
			}
			
			if ($this->theTable == 'fe_users') {
				$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'] . ',username', 1)));
				$this->conf[$this->cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'] . ',username', 1)));
				if ($this->conf[$this->cmdKey.'.']['generateUsername']) {
					$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('username')));
				}
				if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername']) {
					$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('username')));
					if ($this->cmdKey == 'create' || $this->cmdKey == 'invite') {
						$this->conf[$this->cmdKey.'.']['fields'] = implode(',', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'] . ',email', 1));
						$this->conf[$this->cmdKey.'.']['required'] = implode(',', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'] . ',email', 1));
					}
					if ($this->cmdKey == 'edit' && $this->conf['setfixed']) {
						$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('email')));
					}
				}
				if ($this->conf[$this->cmdKey.'.']['allowUserGroupSelection']) {
					$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'] . ',usergroup', 1)));
					$this->conf[$this->cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'] . ',usergroup', 1)));
				} else {
					$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('usergroup')));
				}
				if ($this->cmdKey == 'invite') {
					if ($this->useMd5Password) {
						$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('password')));
						if (is_array($this->conf[$this->cmdKey.'.']['evalValues.'])) {
							unset($this->conf[$this->cmdKey.'.']['evalValues.']['password']);
						}
					}
					if ($this->conf['enableAdminReview']) {
						if ($this->setfixedEnabled && is_array($this->conf['setfixed.']['ACCEPT.']) && is_array($this->conf['setfixed.']['APPROVE.'])) {
							$this->conf['setfixed.']['APPROVE.'] = $this->conf['setfixed.']['ACCEPT.'];
						}
					}
				}
			}
				// Setting requiredArr to the fields in "required" fields list intersected with the total field list in order to remove invalid fields.
			$this->requiredArr = array_intersect(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'], 1),
				t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1));
				
				// Setting the authCode length
			$this->codeLength = intval($this->conf['authcodeFields.']['codeLength']) ? intval($this->conf['authcodeFields.']['codeLength']) : 8;

				// Setting the record uid if a frontend user is logged in and we are not trying to send an invitation
			if ($this->theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser && $this->cmd != 'invite') {
				$this->recUid = $GLOBALS['TSFE']->fe_user->user['uid'];
			}

				// Fetching the template file
			$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

				// Set globally substituted markers, fonts and colors.
			if ($this->conf['templateStyle'] != 'css-styled') {
				$splitMark = md5(microtime());
				list($this->markerArray['###GW1B###'], $this->markerArray['###GW1E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap1.']));
				list($this->markerArray['###GW2B###'], $this->markerArray['###GW2E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap2.']));
				list($this->markerArray['###GW3B###'], $this->markerArray['###GW3E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap3.']));
				$this->markerArray['###GC1###'] = $this->cObj->stdWrap($this->conf['color1'], $this->conf['color1.']);
				$this->markerArray['###GC2###'] = $this->cObj->stdWrap($this->conf['color2'], $this->conf['color2.']);
				$this->markerArray['###GC3###'] = $this->cObj->stdWrap($this->conf['color3'], $this->conf['color3.']);
			}
			$this->markerArray['###CHARSET###'] = $this->charset;
			$this->markerArray['###PREFIXID###'] = $this->prefixId;

				// Setting URL, HIDDENFIELDS and signature markers
			$this->markerArray = $this->addURLMarkers($this->markerArray);

				// Setting CSS style markers if required
			if ($this->HTMLMailEnabled) {
				$this->markerArray = $this->addCSSStyleMarkers($this->markerArray);
			}

				// If data is submitted, we take care of it here.
			if ($this->cmd == 'delete' && !$this->feUserData['preview'] && !$this->feUserData['doNotSave'] ) {
				// Delete record if delete command is sent + the preview flag is NOT set.
				$this->deleteRecord();
			}
				// Evaluate incoming data
			if ($this->incomingData) {
				$this->setName();
				$this->parseValues();
				$this->overrideValues();
				if ($this->feUserData['submit'] || $this->feUserData['doNotSave'] || $this->feUserData['linkToPID']) {
					// a button was clicked on
					$this->evalValues();
					if ($this->conf['evalFunc'] ) {
						$this->dataArr = $this->userProcess('evalFunc', $this->dataArr);
					}
				} else {
					//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
					// we are going to redisplay
					$this->evalValues();
					$this->failure = 1;
				}
				$this->setUsername();
				if (!$this->failure && !$this->feUserData['preview'] && !$this->feUserData['doNotSave'] ) {
					$this->setPassword();
					$this->save();
				}
			} else {
				$this->defaultValues(); // If no incoming data, this will set the default values.
				$this->feUserData['preview'] = 0; // No preview if data is not received
			}
			if ($this->failure ) {
				$this->feUserData['preview'] = 0;
			} // No preview flag if a evaluation failure has occured
			$this->previewLabel = ($this->feUserData['preview']) ? '_PREVIEW' : ''; // Setting preview template label suffix.

				// Display forms
			if ($this->saved) {
					// Displaying the page here that says, the record has been saved. You're able to include the saved values by markers.
				switch($this->cmd) {
					case 'delete':
						$key = 'DELETE'.$this->savedSuffix;
						break;
					case 'edit':
						$key = 'EDIT'.$this->savedSuffix;
						break;
					case 'invite':
						$key = $this->setfixedPrefix.'INVITE';
						break;
					case 'create':
						if (!$this->setfixedEnabled) {
							$this->markerArray = $this->addMd5LoginMarkers($this->markerArray);
							if ($this->useMd5Password) {
								$this->currentArr['password'] = '';
							}
						}
					default:
						if ($this->setfixedEnabled) {
							$key = $this->setfixedPrefix.'CREATE';
							if ($this->conf['enableAdminReview']) {
								$key .= '_REVIEW';
							}
						} else {
							$key = 'CREATE'.$this->savedSuffix;
						}
						break;
				}
					// Display confirmation message
				$templateCode = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_'.$key.'###');
				$markerArray = $this->cObj->fillInMarkerArray($this->markerArray, $this->currentArr);
				$markerArray = $this->addStaticInfoMarkers($markerArray, $this->currentArr);
				$markerArray = $this->addTcaMarkers($markerArray, $this->currentArr, true);
				$markerArray = $this->addLabelMarkers($markerArray, $this->currentArr);
				$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);

					// Send email message(s)
				$this->compileMail($key, array($this->currentArr), $this->currentArr[$this->conf['email.']['field']], $this->conf['setfixed.']);

					// Link to on edit save
					// backURL may link back to referring process
				if ($this->theTable == 'fe_users' && 
					$this->cmd == 'edit' && 
 					($this->backURL || ($this->conf['linkToPID'] && ($this->feUserData['linkToPID'] || !$this->conf['linkToPIDAddButton']))) ) {
 					$destUrl = ($this->backURL ? $this->backURL : $this->site_url.$this->cObj->getTypoLink_URL($this->conf['linkToPID'].','.$GLOBALS['TSFE']->type));
 					header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
					exit;
				}
			} elseif ($this->error) {
					// If there was an error, we return the template-subpart with the error message
				$templateCode = $this->cObj->getSubpart($this->templateCode, $this->error);
				$this->setCObjects($templateCode);
				$content = $this->cObj->substituteMarkerArray($templateCode, $this->markerArray);
			} else {
					// Finally, if there has been no attempt to save. That is either preview or just displaying and empty or not correctly filled form:
				switch($this->cmd) {
					case 'setfixed':
						if ($this->conf['infomail']) {
							$this->setfixedEnabled = 1;
						}
						$content = $this->procesSetFixed();
						break;
					case 'infomail':
						if ($this->conf['infomail']) {
							$this->setfixedEnabled = 1;
						}
						$content = $this->sendInfoMail();
						break;
					case 'delete':
						$content = $this->displayDeleteScreen();
						break;
					case 'edit':
						$content = $this->displayEditScreen();
						break;
					case 'invite':
					case 'create':
						$content = $this->displayCreateScreen($this->cmd);
						break;
					default:
						if ($this->theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) {
							$content = $this->displayCreateScreen($this->cmd);
						} else {
							$content = $this->displayEditScreen();
						}
						break;
				}
			}
			return $this->pi_wrapInBaseClass($content);
		}
		/**
		* Applies validation rules specified in TS setup
		*
		* @return void  on return, $this->failure is the list of fields which were not ok
		*/
		function evalValues() {
			// Check required, set failure if not ok.
			reset($this->requiredArr);
			$tempArr = array();
			while (list(, $theField) = each($this->requiredArr)) {
				if (!trim($this->dataArr[$theField])) {
					$tempArr[] = $theField;
					$this->missing[$theField] = true;
				}
			}
			// Evaluate: This evaluates for more advanced things than "required" does. But it returns the same error code, so you must let the required-message tell, if further evaluation has failed!
			$recExist = 0;
			if (is_array($this->conf[$this->cmdKey.'.']['evalValues.'])) {
				switch($this->cmd) {
					case 'edit':
						if (isset($this->dataArr['pid'])) {
								// This may be tricked if the input has the pid-field set but the edit-field list does NOT allow the pid to be edited. Then the pid may be false.
							$recordTestPid = intval($this->dataArr['pid']);
						} else {
							$tempRecArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $this->dataArr[uid]);
							$recordTestPid = intval($tempRecArr['pid']);
						}
						$recExist = 1;
						break;
					default:
						$recordTestPid = $this->thePid ? $this->thePid :
						t3lib_div::intval_positive($this->dataArr['pid']);
						break;
				}
				if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] || ($this->conf[$this->cmdKey.'.']['generateUsername'] && $this->cmdKey != 'edit')) {
					unset($this->conf[$this->cmdKey.'.']['evalValues.']['username']);
				}
				if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] && $this->cmdKey == 'edit' && $this->conf['setfixed']) {
						unset($this->conf[$this->cmdKey.'.']['evalValues.']['email']);
				}
				
				reset($this->conf[$this->cmdKey.'.']['evalValues.']);
				while (list($theField, $theValue) = each($this->conf[$this->cmdKey.'.']['evalValues.'])) {
					$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
					while (list(, $cmd) = each($listOfCommands)) {
						$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'uniqueGlobal':
							$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->theTable, $theField, $this->dataArr[$theField], 'LIMIT 1');
							if (trim($this->dataArr[$theField]) && $DBrows) {
								if (!$recExist || $DBrows[0]['uid'] != $this->dataArr['uid']) {
									// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
									$tempArr[] = $theField;
									$this->inError[$theField] = true;
									$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'The value existed already. Enter a new value.');
								}
							}
							break;
							case 'uniqueLocal':
							$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->theTable, $theField, $this->dataArr[$theField], "AND pid IN (".$recordTestPid.') LIMIT 1');
							if (trim($this->dataArr[$theField]) && $DBrows) {
								if (!$recExist || $DBrows[0]['uid'] != $this->dataArr['uid']) {
									// Only issue an error if the record is not existing (if new...) and if the record with the false value selected was not our self.
									$tempArr[] = $theField;
									$this->inError[$theField] = true;
									$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'The value existed already. Enter a new value.');
								}
							}
							break;
							case 'twice':
							if (strcmp($this->dataArr[$theField], $this->dataArr[$theField.'_again'])) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'You must enter the same value twice.');
							}
							break;
							case 'email':
							if (trim($this->dataArr[$theField]) && !$this->cObj->checkEmail($this->dataArr[$theField])) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'You must enter a valid email address.');
							}
							break;
							case 'required':
							if (!trim($this->dataArr[$theField])) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'You must enter a value!');
							}
							break;
							case 'atLeast':
							$chars = intval($cmdParts[1]);
							if (strlen($this->dataArr[$theField]) < $chars) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = sprintf($this->getFailure($theField, $theCmd, 'You must enter at least %s characters!'), $chars);
							}
							break;
							case 'atMost':
							$chars = intval($cmdParts[1]);
							if (strlen($this->dataArr[$theField]) > $chars) {
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = sprintf($this->getFailure($theField, $theCmd, 'You must enter at most %s characters!'), $chars);
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
								if (!$pid_list || !t3lib_div::inList($pid_list, $this->dataArr[$theField])) {
									$tempArr[] = $theField;
									$this->inError[$theField] = true;
									$this->failureMsg[$theField][] = sprintf($this->getFailure($theField, $theCmd, 'The value was not a valid value from this list: %s'), $pid_list);
								}
							}
							break;
							case 'unsetEmpty':
							if (!$this->dataArr[$theField]) {
								$hash = array_flip($tempArr);
								unset($hash[$theField]);
								$tempArr = array_keys($hash);
								unset($this->inError[$theField]);
								unset($this->failureMsg[$theField]);
								unset($this->dataArr[$theField]); // This should prevent the field from entering the database.
							}
							break;
							case 'upload':
							if ($this->dataArr[$theField] && is_array($this->TCA['columns'][$theField]['config']) ) {
								if ($this->TCA['columns'][$theField]['config']['type'] == 'group' && $this->TCA['columns'][$theField]['config']['internal_type'] == 'file') {
									$uploadPath = $this->TCA['columns'][$theField]['config']['uploadfolder'];
									$allowedExtArray = t3lib_div::trimExplode(',', $this->TCA['columns'][$theField]['config']['allowed'], 1);
									$maxSize = $this->TCA['columns'][$theField]['config']['max_size'];
									$fileNameList = explode(',', $this->dataArr[$theField]);
									$newFileNameList = array();
									reset($fileNameList);
									while (list(, $filename) = each($fileNameList)) {
										$fI = pathinfo($filename);
										if (!count($allowedExtArray) || in_array(strtolower($fI['extension']), $allowedExtArray)) {
											if (@is_file(PATH_site.$uploadPath.'/'.$filename)) {
												if (!$maxSize || (filesize(PATH_site.$uploadPath.'/'.$filename) < ($maxSize * 1024))) {
													$newFileNameList[] = $filename;
												} else {
													$this->failureMsg[$theField][] = sprintf($this->getFailure($theField, 'max_size', 'The file is larger than %s KB.'), $maxSize);
													$tempArr[] = $theField;
													$this->inError[$theField] = true;
													if(@is_file(PATH_site.$uploadPath.'/'.$filename)) @unlink(PATH_site.$uploadPath.'/'.$filename);
												}
											}
										} else {
											$this->failureMsg[$theField][] = sprintf($this->getFailure($theField, 'allowed', 'The file extension %s is not allowed.'), $fI['extension']);
											$tempArr[] = $theField;
											$this->inError[$theField] = true;
											if (@is_file(PATH_site.$uploadPath.'/'.$filename)) { @unlink(PATH_site.$uploadPath.'/'.$filename); }
										}
									}
									$this->dataArr[$theField] = implode(',', $newFileNameList);
								}
							}
							break;
							case 'wwwURL':
							if ($this->dataArr[$theField]) {
								$wwwURLOptions = array (
								'AssumeProtocol' => 'http' ,
									'AllowBracks' => TRUE ,
									'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
									'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
									'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
									);
								$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($this->dataArr[$theField], $wwwURLOptions);
								if ($wwwURLResult['Result'] != 'EW_OK' ) {
									$tempArr[] = $theField;
									$this->inError[$theField] = true;
									$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'Please enter a valid Internet site address.');
								}
							}
							break;
							case 'date':
							if ($this->dataArr[$theField] && !$this->evalDate($this->dataArr[$theField]) ){
								$tempArr[] = $theField;
								$this->inError[$theField] = true;
								$this->failureMsg[$theField][] = $this->getFailure($theField, $theCmd, 'Please enter a valid date.');
							}
							break;
						}
					}
					$this->markerArray['###EVAL_ERROR_FIELD_'.$theField.'###'] = is_array($this->failureMsg[$theField]) ? implode($this->failureMsg[$theField], '<br />'): '<!--no error-->';
				}
			}
			$this->failure = implode($tempArr, ',');
		}
		/**
		* Gets the error message to be displayed
		*
		* @param string  $theField: the name of the field being validated
		* @param string  $theCmd: the name of the validation rule being evaluated
		* @param string  $label: a default error message provided by the invoking function
		* @return string  the error message to be displayed
		*/
		function getFailure($theField, $theCmd, $label) {
			$failureLabel = $this->pi_getLL('evalErrors_'.$theCmd.'_'.$theField);
			$failureLabel = $failureLabel ? $failureLabel : $this->pi_getLL('evalErrors_'.$theCmd);
			$failureLabel = $failureLabel ? $failureLabel : (isset($this->conf['evalErrors.'][$theField.'.'][$theCmd]) ? $this->conf['evalErrors.'][$theField.'.'][$theCmd] : $label);
			return $failureLabel;
		}
		/**
		* Invokes a user process
		*
		* @param array  $mConfKey: the configuration array of the user process
		* @param array  $passVar: the array of variables to be passed to the user process
		* @return array  the updated array of passed variables
		*/
		function userProcess($mConfKey, $passVar) {
			if ($this->conf[$mConfKey]) {
				$funcConf = $this->conf[$mConfKey.'.'];
				$funcConf['parentObj'] = &$this;
				$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
			}
			return $passVar;
		}
		/**
		* Invokes a user process
		*
		* @param string  $confVal: the name of the process to be invoked
		* @param array  $mConfKey: the configuration array of the user process
		* @param array  $passVar: the array of variables to be passed to the user process
		* @return array  the updated array of passed variables
		*/
		function userProcess_alt($confVal, $confArr, $passVar) {
			if ($confVal) {
				$funcConf = $confArr;
				$funcConf['parentObj'] = &$this;
				$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($confVal, $funcConf, $passVar);
			}
			return $passVar;
		}
		/**
		* Transforms fields into certain things...

		*
		* @return void  all parsing done directly on input array $this->dataArr
		*/
		function parseValues() {
			// <Ries van Twisk added support for multiple checkboxes>
			foreach ($this->dataArr AS $key => $value) {
				// If it's an array and the type is check, then we combine the selected items to a binary value
				if (($this->TCA['columns'][$key]['config']['type'] == 'check') && is_array($this->TCA['columns'][$key]['config']['items'])) {
					if(is_array($value)) {
						$this->dataArr[$key] = 0;
						foreach ($value AS $dec) {  // Combine values to one hexidecimal number
							$this->dataArr[$key] |= (1 << $dec);
						}
					}
				}
			}
			// </Ries van Twisk added support for multiple checkboxes>
			if (is_array($this->conf['parseValues.'])) {
				reset($this->conf['parseValues.']);
				while (list($theField, $theValue) = each($this->conf['parseValues.'])) {
					$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
					while (list(, $cmd) = each($listOfCommands)) {
						$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'int':
							$this->dataArr[$theField] = intval($this->dataArr[$theField]);
							break;
							case 'lower':
							case 'upper':
							$this->dataArr[$theField] = $this->cObj->caseshift($this->dataArr[$theField], $theCmd);
							break;
							case 'nospace':
							$this->dataArr[$theField] = str_replace(' ', '', $this->dataArr[$theField]);
							break;
							case 'alpha':
							$this->dataArr[$theField] = ereg_replace('[^a-zA-Z]', '', $this->dataArr[$theField]);
							break;
							case 'num':
							$this->dataArr[$theField] = ereg_replace('[^0-9]', '', $this->dataArr[$theField]);
							break;
							case 'alphanum':
							$this->dataArr[$theField] = ereg_replace('[^a-zA-Z0-9]', '', $this->dataArr[$theField]);
							break;
							case 'alphanum_x':
							$this->dataArr[$theField] = ereg_replace('[^a-zA-Z0-9_-]', '', $this->dataArr[$theField]);
							break;
							case 'trim':
							$this->dataArr[$theField] = trim($this->dataArr[$theField]);
							break;
							case 'random':
							$this->dataArr[$theField] = substr(md5(uniqid(microtime(), 1)), 0, intval($cmdParts[1]));
							break;
							case 'files':
							if (is_string($this->dataArr[$theField]) && $this->dataArr[$theField]) {
								$this->dataArr[$theField] = explode(',', $this->dataArr[$theField]);
							}
							$this->processFiles($theField);
							break;
							case 'setEmptyIfAbsent':
							if (!isset($this->dataArr[$theField])) {
								$this->dataArr[$theField] = '';
							}
							break;
							case 'multiple':
							if (is_array($this->dataArr[$theField])) {
								$this->dataArr[$theField] = implode(',', $this->dataArr[$theField]);
							}
							break;
							case 'checkArray':
							if (is_array($this->dataArr[$theField])) {
								reset($this->dataArr[$theField]);
								$val = 0;
								while (list($kk, $vv) = each($this->dataArr[$theField])) {
									$kk = t3lib_div::intInRange($kk, 0);
									if ($kk <= 30) {
										if ($vv) {
											$val|= pow(2, $kk);
										}
									}
								}
								$this->dataArr[$theField] = $val;
							}
							break;
							case 'uniqueHashInt':
							$otherFields = t3lib_div::trimExplode(';', $cmdParts[1], 1);
							$hashArray = array();
							while (list(, $fN) = each($otherFields)) {
								$vv = $this->dataArr[$fN];
								$vv = ereg_replace('[[:space:]]', '', $vv);
								$vv = ereg_replace('[^[:alnum:]]', '', $vv);
								$vv = strtolower($vv);
								$hashArray[] = $vv;
							}
							$this->dataArr[$theField] = hexdec(substr(md5(serialize($hashArray)), 0, 8));
							break;
							case 'wwwURL':
							if ($this->dataArr[$theField]) {
								$wwwURLOptions = array (
								'AssumeProtocol' => 'http' ,
									'AllowBracks' => TRUE ,
									'AllowedProtocols' => array(0 => 'http', 1 => 'https', ) ,
									'Require' => array('Protocol' => FALSE , 'User' => FALSE , 'Password' => FALSE , 'Server' => TRUE , 'Resource' => FALSE , 'TLD' => TRUE , 'Port' => FALSE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
									'Forbid' => array('Protocol' => FALSE , 'User' => TRUE , 'Password' => TRUE , 'Server' => FALSE , 'Resource' => FALSE , 'TLD' => FALSE , 'Port' => TRUE , 'QueryString' => FALSE , 'Anchor' => FALSE , ) ,
									);
								$wwwURLResult = tx_srfeuserregister_pi1_urlvalidator::_ValURL($this->dataArr[$theField], $wwwURLOptions);
								if ($wwwURLResult['Result'] = 'EW_OK' ) {
									$this->dataArr[$theField] = $wwwURLResult['Value'];
								}
							}
							break;
							case 'date':
							if($this->dataArr[$theField] && $this->evalDate($this->dataArr[$theField]) && strlen($this->dataArr[$theField]) == 8) { 
									$this->dataArr[$theField] = substr($this->dataArr[$theField],0,4).'-'.substr($this->dataArr[$theField],4,2).'-'.substr($this->dataArr[$theField],6,2);
							}
							break;
						}
					}
				}
			}
		}
		/**
		* Processes uploaded files
		*
		* @param string  $theField: the name of the field
		* @return void
		*/
		function processFiles($theField) {
			if (is_array($this->TCA['columns'][$theField])) {
				$uploadPath = $this->TCA['columns'][$theField]['config']['uploadfolder'];
			}
			$fileNameList = array();
			if (is_array($this->dataArr[$theField]) && count($this->dataArr[$theField])) {
				while (list($i, $file) = each($this->dataArr[$theField])) {
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
			if ($uploadPath && is_array($_FILES['FE']['name'][$this->theTable][$theField]) && $this->evalFileError($_FILES['FE']['error'])) {
				reset($_FILES['FE']['name'][$this->theTable][$theField]);
				while (list($i, $filename) = each($_FILES['FE']['name'][$this->theTable][$theField])) {
					if ($filename) {
						$fI = pathinfo($filename);
						if (t3lib_div::verifyFilenameAgainstDenyPattern($fI['name'])) {
							$tmpFilename = (($GLOBALS['TSFE']->loginUser)?($GLOBALS['TSFE']->fe_user->user['username'].'_'):'').basename($filename, '.'.$fI['extension']).'_'.t3lib_div::shortmd5(uniqid($filename)).'.'.$fI['extension'];
							$theDestFile = $this->fileFunc->getUniqueName($this->fileFunc->cleanFileName($tmpFilename), PATH_site.$uploadPath.'/');
							t3lib_div::upload_copy_move($_FILES['FE']['tmp_name'][$this->theTable][$theField][$i], $theDestFile);
							$fI2 = pathinfo($theDestFile);
							$fileNameList[] = $fI2['basename'];
						}
					}
				}
			}
			$this->dataArr[$theField] = (count($fileNameList))?implode(',', $fileNameList):'';
		}

		/**
		* Overrides field values as specified by TS setup
		*
		* @return void  all overriding done directly on array $this->dataArr
		*/
		function overrideValues() {
			// Addition of overriding values
			if (is_array($this->conf[$this->cmdKey.'.']['overrideValues.'])) {
				reset($this->conf[$this->cmdKey.'.']['overrideValues.']);
				while (list($theField, $theValue) = each($this->conf[$this->cmdKey.'.']['overrideValues.'])) {
					if( $theField == 'usergroup' && $this->theTable == 'fe_users' && $this->conf[$this->cmdKey.'.']['allowUserGroupSelection']) {
						$this->dataArr[$theField] = implode(',', array_merge(array_diff(t3lib_div::trimExplode(',', $this->dataArr[$theField], 1), t3lib_div::trimExplode(',', $theValue, 1)), t3lib_div::trimExplode(',', $theValue, 1)));
					} else {
						$this->dataArr[$theField] = $theValue;
					}
				}
			}
		}

		/**
		* Sets default field values as specified by TS setup
		*
		* @return void  all initialization done directly on array $this->dataArr
		*/
		function defaultValues() {
			// Addition of default values
			if (is_array($this->conf[$this->cmdKey.'.']['defaultValues.'])) {
				reset($this->conf[$this->cmdKey.'.']['defaultValues.']);
				while (list($theField, $theValue) = each($this->conf[$this->cmdKey.'.']['defaultValues.'])) {
					$this->dataArr[$theField] = $theValue;
				}
			}
			if (is_array($this->conf[$this->cmdKey.'.']['evalValues.'])) {
				reset($this->conf[$this->cmdKey.'.']['evalValues.']);
				while (list($theField, $theValue) = each($this->conf[$this->cmdKey.'.']['evalValues.'])) {
					$this->markerArray['###EVAL_ERROR_FIELD_'.$theField.'###'] = '<!--no error-->';
				}
			}
		}
		/**
		* Moves first and last name into name
		*
		* @return void  done directly on array $this->dataArr
		*/
		function setName() {
			if (in_array('name', explode(',', $this->fieldList)) && !in_array('name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1))
				&& in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1)) && in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1))  ) {
				$this->dataArr['name'] = trim(trim($this->dataArr['first_name']).' '.trim($this->dataArr['last_name']));
			}
		}
		
		/**
		 * Moves email into username if useEmailAsUsername is set
		 *
		 * @return void  done directly on array $this->dataArr
		 */
		function setUsername() {
			if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] && $this->theTable == "fe_users" && t3lib_div::inList($this->fieldList, 'username') && !$this->failureMsg['email']) {
				$this->dataArr['username'] = trim($this->dataArr['email']);
			}
		}
		
		/**
		 * Assigns a value to the password if this is an invitation and password encryption with kb_md5fepw is enabled
		 *
		 * @return void  done directly on array $this->dataArr
		 */
		function setPassword() {
			if ($this->cmdKey == 'invite' && $this->useMd5Password) {
				$this->dataArr['password'] = tx_kbmd5fepw_funcs::generatePassword(intval($GLOBALS['TSFE']->config['plugin.']['tx_newloginbox_pi1.']['defaultPasswordLength']) ? intval($GLOBALS['TSFE']->config['plugin.']['tx_newloginbox_pi1.']['defaultPasswordLength']) : 5);
			}
		}
		
		/**
		* Saves the data into the database
		*
		* @return void  sets $this->saved
		*/
		function save() {
			if ($this->typoVersion >= 3006000) global $TYPO3_DB;
			switch($this->cmd) {
				case 'edit':
				$theUid = $this->dataArr['uid'];
				$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $theUid);
					// Fetch the original record to check permissions
				if ($this->conf['edit'] && ($GLOBALS['TSFE']->loginUser || $this->aCAuth($origArr))) {
						// Must be logged in in order to edit  (OR be validated by email)
					$newFieldList = implode(',', array_intersect(explode(',', $this->fieldList), t3lib_div::trimExplode(',', $this->conf['edit.']['fields'], 1)));
					$newFieldList = implode(',', array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->adminFieldList))));
						// Do not reset the name if we have no new value
					if(!in_array('name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1)) && !in_array('first_name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1)) && !in_array('last_name', t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1))) {
						$newFieldList  = implode(',', array_diff(explode(',', $newFieldList), array('name')));
					}
					if ($this->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($this->theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						if ($this->typoVersion >= 3006000) {
							$res = $this->cObj->DBgetUpdate($this->theTable, $theUid, $this->parseOutgoingData($this->dataArr), $newFieldList, true);
						} else {
							$query = $this->cObj->DBgetUpdate($this->theTable, $theUid, $this->parseOutgoingData($this->dataArr), $newFieldList);
							mysql(TYPO3_db, $query);
							echo mysql_error();
						}
						$this->updateMMRelations($this->dataArr);
						$this->saved = 1;

							// Post-edit processing: call user functions and hooks
						$this->currentArr = $this->parseIncomingData( $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $theUid));
						$this->userProcess_alt($this->conf['edit.']['userFunc_afterSave'], $this->conf['edit.']['userFunc_afterSave.'], array('rec' => $this->currentArr, 'origRec' => $origArr));

							// <Ries van Twisk added registrationProcess hooks>
							// Call all afterSaveEdit hooks after the record has been edited and saved
						if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
							foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
								$hookObj= &t3lib_div::getUserObj($classRef);
								if (method_exists($hookObj, 'registrationProcess_afterSaveEdit')) {
									$hookObj->registrationProcess_afterSaveEdit($this->currentArr, $this);
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
				if (is_array($this->conf[$this->cmdKey.'.'])) {
					$newFieldList = implode(array_intersect(explode(',', $this->fieldList), t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1)), ',');
					$newFieldList  = implode( array_unique( array_merge (explode(',', $newFieldList), explode(',', $this->adminFieldList))), ',');
					$parsedArray = array();
					$parsedArray = $this->parseOutgoingData($this->dataArr);
					if ($this->cmdKey == 'invite' && $this->useMd5Password) {
						$parsedArray['password'] = md5($this->dataArr['password']);
					}
					if ($this->typoVersion >= 3006000) {
						$res = $this->cObj->DBgetInsert($this->theTable, $this->thePid, $parsedArray, $newFieldList, true);
						$newId = $TYPO3_DB->sql_insert_id();
					} else {
						$query = $this->cObj->DBgetInsert($this->theTable, $this->thePid, $parsedArray, $newFieldList);
						mysql(TYPO3_db, $query);
						echo mysql_error();
						$newId = mysql_insert_id();
					}
						// Enable users to own them self.
					if ($this->theTable == "fe_users" && $this->conf['fe_userOwnSelf']) {
						$extraList = '';
						$dataArr = array();
						if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id']) {
							$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_cruser_id'];
							$dataArr[$field] = $newId;
							$extraList .= ','.$field;
						}
						if ($GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id']) {
							$field = $GLOBALS['TCA'][$this->theTable]['ctrl']['fe_crgroup_id'];
							list($dataArr[$field]) = explode(',', $this->dataArr['usergroup']);
							$dataArr[$field] = intval($dataArr[$field]);
							$extraList .= ','.$field;
						}
						if (count($dataArr)) {
							if ($this->typoVersion >= 3006000) {
								$res = $this->cObj->DBgetUpdate($this->theTable, $newId, $dataArr, $extraList, true);
							} else {
								$query = $this->cObj->DBgetUpdate($this->theTable, $newId, $dataArr, $extraList);
								mysql(TYPO3_db, $query);
								echo mysql_error();
							}
						} 
					}
					$this->dataArr['uid'] = $newId;
					$this->updateMMRelations($this->dataArr);
					$this->saved = 1;

						// Post-create processing: call user functions and hooks
					$this->currentArr = $this->parseIncomingData( $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $newId));
					$this->userProcess_alt($this->conf['create.']['userFunc_afterSave'], $this->conf['create.']['userFunc_afterSave.'], array('rec' => $this->currentArr));

						// <Ries van Twisk added registrationProcess hooks>
						// Call all afterSaveCreate hooks after the record has been created and saved
					if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
						foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
							$hookObj= &t3lib_div::getUserObj($classRef);
							if (method_exists($hookObj, 'registrationProcess_afterSaveCreate')) {
								$hookObj->registrationProcess_afterSaveCreate($this->currentArr, $this);
							}
						}
					}
						// </Ries van Twisk added registrationProcess hooks>
					if ($this->cmdKey == 'invite' && $this->useMd5Password) {
						$this->currentArr['password'] = $this->dataArr['password'];
					}
				}
				break;
			}
		}
		
		/**
		 * Removes required and error sub-parts when there are no errors
		 *
		 * Works like this:
		 * - Insert subparts like this ###SUB_REQUIRED_FIELD_".$theField."### that tells that the field is required, if it's not correctly filled in.
		 * - These subparts are all removed, except if the field is listed in $failure string!
		 * - Subparts like ###SUB_ERROR_FIELD_".$theField."### are also removed if there is no error on the field
		 * - Remove also the parts of non-included fields, using a similar scheme!
		 *
		 * @param string  $templateCode: the content of the HTML template
		 * @param string  $failure: the list of fiels with errors
		 * @return string  the template with susbstituted parts
		 */
		function removeRequired($templateCode, $failure = '') {
			$includedFields = t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1);
			if ($this->feUserData['preview'] && !in_array('username', $includedFields)) {
				$includedFields[] = 'username';
			}
			reset($this->requiredArr);
			$infoFields = explode(',', $this->fieldList);

			while (list(, $fName) = each($infoFields) ) {
				if (in_array(trim($fName), $this->requiredArr) ) {
					if (!t3lib_div::inList($failure, $fName)) {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$fName.'###', '');
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$fName.'###', '');
					} else if (!$this->inError[$fName]) {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$fName.'###', '');
					}
				} else {
					if (!in_array(trim($fName), $includedFields) ) {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_'.$fName.'###', '');
					} else {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$fName.'###', '');
						if (!t3lib_div::inList($failure, $fName)) {
							$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$fName.'###', '');
						}
						if (is_array($this->conf['parseValues.']) && strstr($this->conf['parseValues.'][$fName],'checkArray')) {
							$listOfCommands = t3lib_div::trimExplode(',', $this->conf['parseValues.'][$fName], 1);
								while (list(, $cmd) = each($listOfCommands)) {
									$cmdParts = split('\[|\]', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
									$theCmd = trim($cmdParts[0]);
									switch($theCmd) {
										case 'checkArray':
											$positions = t3lib_div::trimExplode(';', $cmdParts[1]);
											for($i=0; $i<10; $i++) {
												if(!in_array($i, $positions)) {
													$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_'.$fName.'_'.$i.'###', '');
												}
											}
										break;
									}
								}
						}
					}
				}
			}
			return $templateCode;
		}
		/**
		* Initializes a template, filling values for data and labels
		*
		* @param string  $key: the template key
		* @param array  $r: the data array, if any
		* @return string  the template with substituted parts and markers
		*/
		function getPlainTemplate($key, $r = '') {
			$templateCode = $this->cObj->getSubpart($this->templateCode, $key);
			$markerArray = is_array($r) ? $this->cObj->fillInMarkerArray($this->markerArray, $r) : $this->markerArray;
			$markerArray = $this->addStaticInfoMarkers($markerArray, $r);
			$markerArray = $this->addTcaMarkers($markerArray, $r, true);
			$markerArray = $this->addLabelMarkers($markerArray, $r);
			$templateCode = $this->removeStaticInfoSubparts($templateCode, $markerArray);
			return $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		}
		/**
		* Displays the record update form
		*
		* @param array  $origArr: the array coming from the database
		* @return string  the template with substituted markers
		*/
		function displayEditForm($origArr) {
			$currentArr = is_array($this->dataArr) ? $this->dataArr+$origArr: $origArr;
			if(is_array($this->dataArr)) {
				foreach ($currentArr AS $key => $value) {
					// If the type is check, ...
					if (($this->TCA['columns'][$key]['config']['type'] == 'check') && is_array($this->TCA['columns'][$key]['config']['items'])) {
						if(isset($this->dataArr[$key]) && !$this->dataArr[$key]) {
							$currentArr[$key] = 0;
						}
					}
				}
			}
			$templateCode = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_EDIT".$this->previewLabel.'###');
			if (!$this->conf['linkToPID'] || !$this->conf['linkToPIDAddButton'] || !($this->previewLabel || !$this->conf['edit.']['preview'])) {
				$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_LINKTOPID_ADD_BUTTON###', '');
			}
			if ($this->typoVersion >= 3006000) {
				$failure = t3lib_div::_GP('noWarnings') ? '': $this->failure;
			} else {
				$failure = t3lib_div::GPvar('noWarnings') ? '': $this->failure;
			}
			if (!$failure) {
				$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
			}
			$templateCode = $this->removeRequired($templateCode, $failure);
			$markerArray = $this->cObj->fillInMarkerArray($this->markerArray, $currentArr);
			$markerArray = $this->addStaticInfoMarkers($markerArray, $currentArr);
			$markerArray = $this->addTcaMarkers($markerArray, $currentArr, true);
			$markerArray = $this->addTcaMarkers($markerArray, $currentArr);
			$markerArray = $this->addLabelMarkers($markerArray, $currentArr);
			$markerArray = $this->addFileUploadMarkers('image', $markerArray, $currentArr);
			$templateCode = $this->removeStaticInfoSubparts($templateCode, $markerArray);
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.'][uid]" value="'.$currentArr['uid'].'" />';
			if ($this->theTable != 'fe_users') {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->authCode($origArr).'" />';
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[cmd]" value="edit" />';
			} elseif ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] && $this->conf['templateStyle'] != 'css-styled') {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.'][username]" value="'.$currentArr['username'].'" />';
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.'][email]" value="'.$currentArr['email'].'" />';
			}
			$markerArray = $this->addHiddenFieldsMarkers($markerArray, $currentArr);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
			if ($this->conf['templateStyle'] != 'css-styled' || !$this->previewLabel) {
				$content .= $this->getUpdateJS($this->modifyDataArrForFormUpdate($currentArr), $this->theTable."_form", "FE[".$this->theTable."]", $this->fieldList.$this->additionalUpdateFields);
			}
			return $content;
		}
		
		/**
		* Checks if the edit form may be displayed; if not, a link to login
		*
		* @return string  the template with substituted markers
		*/
		function displayEditScreen() {
			if ($this->conf['edit']) {
				// If editing is enabled
				$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $this->dataArr['uid']?$this->dataArr['uid']:$this->recUid);
				if( $this->theTable != 'fe_users' && $this->conf['setfixed.']['edit.']['_FIELDLIST']) {
					if ($this->typoVersion >= 3006000) {
						$fD = t3lib_div::_GP('fD', 1);
					} else {
						$fD = t3lib_div::GPvar('fD', 1);
					}
					$fieldArr = array();
					if (is_array($fD)) {
						reset($fD);
						while (list($field, $value) = each($fD)) {
							$origArr[$field] = rawurldecode($value);
							$fieldArr[] = $field;
						}
					}
					$theCode = $this->setfixedHash($origArr, $origArr['_FIELDLIST']);
				}
				if (is_array($origArr)) $origArr = $this->parseIncomingData($origArr);

				if (is_array($origArr) && ( ($this->theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $this->aCAuth($origArr) || !strcmp($this->authCode, $theCode) ) ) {
					// Must be logged in OR be authenticated by the aC code in order to edit
					// If the recUid selects a record.... (no check here)
					$this->markerArray = $this->addMd5EventsMarkers($this->markerArray, 'edit');
					if (is_array($origArr)) {
						if ( !strcmp($this->authCode, $this->theCode) || $this->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($this->theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
							// Display the form, if access granted.
							$content = $this->displayEditForm($origArr);
						} else {
							// Else display error, that you could not edit that particular record...
							$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');
						}
					}
				} else {
					// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
					$content = $this->getPlainTemplate('###TEMPLATE_AUTH###');
				}
				 
			} else {
				$content .= 'Edit-option is not set in TypoScript';
			}
			return $content;
		}
		/**
		* Processes a record deletion request
		*
		* @return void  sets $this->saved
		*/
		function deleteRecord() {
			if ($this->conf['delete']) {
				// If deleting is enabled
				$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $this->recUid);
				if ($GLOBALS['TSFE']->loginUser || $this->aCAuth($origArr)) {
					// Must be logged in OR be authenticated by the aC code in order to delete
					// If the recUid selects a record.... (no check here)

					if (is_array($origArr)) {
						if ($this->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($this->theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
								// Delete the record and display form, if access granted.

								// <Ries van Twisk added registrationProcess hooks>
								// Call all beforeSaveDelete hooks BEFORE the record is deleted
							if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
								foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
									$hookObj= &t3lib_div::getUserObj($classRef);
									if (method_exists($hookObj, 'registrationProcess_beforeSaveDelete')) {
										$hookObj->registrationProcess_beforeSaveDelete($origArr, $this);
									}
								}
							}
								// </Ries van Twisk added registrationProcess hooks>

							if (!$this->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
									// If the record is being fully deleted... then remove the images or files attached.
								$this->deleteFilesFromRecord($this->recUid);
							}
							if ($this->typoVersion >= 3006000) {
								$res = $this->cObj->DBgetDelete($this->theTable, $this->recUid, true);
							} else {
								$query = $this->cObj->DBgetDelete($this->theTable, $this->recUid);
								mysql(TYPO3_db, $query);
								echo mysql_error();
							}
							$this->deleteMMRelations($this->theTable, $this->recUid);
							$this->currentArr = $origArr;
							$this->saved = 1;
						} else {
							$this->error = '###TEMPLATE_NO_PERMISSIONS###';
						}
					}
				}
			}
		}
		/**
		 * Delete the files associated with a deleted record
		 *
		 * @param string  $uid: record id
		 * @return void
		 */
		function deleteFilesFromRecord($uid) {
			$rec = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $uid);
			reset($this->TCA['columns']);
			$updateFields = array();
			while (list($field, $conf) = each($this->TCA['columns'])) {
				if ($conf['config']['type'] == "group" && $conf['config']['internal_type'] == 'file') {
					if ($this->typoVersion >= 3006000) {
						$updateFields[$field] = '';
						$res = $this->cObj->DBgetUpdate($this->theTable, $uid, $updateFields, $field, true);
						unset($updateFields[$field]);
					} else {
						$query = 'UPDATE '.$this->theTable.' SET '.$field."='' WHERE uid=".$uid;
						$res = mysql(TYPO3_db, $query);
						echo mysql_error();
					}
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
		}
		 
		/**
		 * This is basically the preview display of delete
		 *
		 * @return string  the template with substituted markers
		 */
		function displayDeleteScreen() {
			if ($this->conf['delete']) {
				// If deleting is enabled
				$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $this->recUid);
				if ( ($this->theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $this->aCAuth($origArr)) {
					// Must be logged in OR be authenticated by the aC code in order to delete

					// If the recUid selects a record.... (no check here)
					if (is_array($origArr)) {
						if ($this->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($this->theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
							// Display the form, if access granted.
							$this->markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="rU" value="'.$this->recUid.'" />';
							if ( $this->theTable != 'fe_users' ) {
								$this->markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->authCode($origArr).'" />';
								$this->markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$this->prefixId.'[cmd]" value="delete" />';
							}
							$content = $this->getPlainTemplate('###TEMPLATE_DELETE_PREVIEW###', $origArr);
						} else {
							// Else display error, that you could not edit that particular record...
							$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');
						}
					}
				} else {
					// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
					if ( $this->theTable == 'fe_users' ) {
						$content = $this->getPlainTemplate('###TEMPLATE_AUTH###');
					} else {
						$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');
					}
				}
			} else {
				$content .= 'Delete-option is not set in TypoScript';
			}
			return $content;
		}
		 
		/**
		* Generates the record creation form
		*
		* @return string  the template with substituted markers
		*/
		function displayCreateScreen($cmd = 'create') {
			if ($this->conf['create']) {
				
					// <Pieter Verstraelen added registrationProcess hooks>
					// Call all beforeConfirmCreate hooks before the record has been shown and confirmed
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
					foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
						$hookObj= &t3lib_div::getUserObj($classRef);
						if (method_exists($hookObj,'registrationProcess_beforeConfirmCreate')) {
							$hookObj->registrationProcess_beforeConfirmCreate($this->dataArr, $this);
						}
					}
				}
					// </Pieter Verstraelen added registrationProcess hooks>
				
				$key = ($cmd == 'invite') ? 'INVITE': 'CREATE';
				$this->markerArray = $this->addMd5EventsMarkers($this->markerArray, 'create');
				$templateCode = $this->cObj->getSubpart($this->templateCode, ((!($this->theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $cmd == 'invite') ? '###TEMPLATE_'.$key.$this->previewLabel.'###':'###TEMPLATE_CREATE_LOGIN###'));
				if ($this->typoVersion >= 3006000) {
					$failure = t3lib_div::_GP('noWarnings') ? '': $this->failure;
				} else {
					$failure = t3lib_div::GPvar('noWarnings') ? '': $this->failure;
				}
				if (!$failure) $templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
				$templateCode = $this->removeRequired($templateCode, $failure);
				$markerArray = $this->cObj->fillInMarkerArray($this->markerArray, $this->dataArr);
				$markerArray = $this->addStaticInfoMarkers($markerArray, $this->dataArr);
				$markerArray = $this->addTcaMarkers($markerArray, $this->dataArr);
				$markerArray = $this->addFileUploadMarkers('image', $markerArray, $this->dataArr);
				$markerArray = $this->addLabelMarkers($markerArray, $this->dataArr);
				$templateCode = $this->removeStaticInfoSubparts($templateCode, $markerArray);
				$markerArray = $this->addHiddenFieldsMarkers($markerArray, $this->dataArr);
				$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
				if ($this->conf['templateStyle'] != 'css-styled' || !$this->previewLabel) {
					$content .= $this->getUpdateJS($this->modifyDataArrForFormUpdate($this->dataArr), $this->theTable."_form", "FE[".$this->theTable."]", $this->fieldList.$this->additionalUpdateFields);
				}
			}
			return $content;
		}
		/**
		 * Sends info mail to subscriber
		 *
		 * @return	string		HTML content message
		 * @see init(),compileMail(), sendMail()
		 */
		function sendInfoMail()	{
			if ($this->conf['infomail'] && $this->conf['email.']['field'])	{
				$fetch = $this->feUserData['fetch'];
				if (isset($fetch))	{
					$pidLock=' AND pid IN ('.$this->thePid.') ';
						// Getting records
					if ( $this->theTable == 'fe_users' && t3lib_div::testInt($fetch) )	{
						$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->theTable,'uid',$fetch,$pidLock,'','','1');
					} elseif ($fetch) {	// $this->conf['email.']['field'] must be a valid field in the table!
						$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->theTable,$this->conf['email.']['field'],$fetch,$pidLock,'','','100');
					}
						// Processing records
					if (is_array($DBrows))	{
						$recipient = $DBrows[0][$this->conf['email.']['field']];
						$this->dataArr = $DBrows[0];
						$this->compileMail('INFOMAIL', $DBrows, trim($recipient), $this->conf['setfixed.']);
					} elseif ($this->cObj->checkEmail($fetch)) {
						$fetchArray = array( '0' => array( 'email' => $fetch));
						$this->compileMail('INFOMAIL_NORECORD', $fetchArray, $fetch);
					}
					$content = $this->getPlainTemplate('###TEMPLATE_'.$this->infomailPrefix.'SENT###', (is_array($DBrows)?$DBrows[0]:''));
				} else {
					$content = $this->getPlainTemplate('###TEMPLATE_INFOMAIL###');
				}
			} else {
				$content='Configuration error: infomail option is not available or emailField is not setup in TypoScript';
			}
			return $content;
		}
		/**
		* Updates the input array from preview
		*
		* @param array  $inputArr: new values
		* @return array  updated array
		*/
		function modifyDataArrForFormUpdate($inputArr) {
			if (is_array($this->conf[$this->cmdKey.'.']['evalValues.'])) {
				reset($this->conf[$this->cmdKey.'.']['evalValues.']);
				while (list($theField, $theValue) = each($this->conf[$this->cmdKey.'.']['evalValues.'])) {
					$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
					while (list(, $cmd) = each($listOfCommands)) {
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
				reset($this->conf['parseValues.']);
				while (list($theField, $theValue) = each($this->conf['parseValues.'])) {
					$listOfCommands = t3lib_div::trimExplode(',', $theValue, 1);
					while (list(, $cmd) = each($listOfCommands)) {
						$cmdParts = split("\[|\]", $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
						$theCmd = trim($cmdParts[0]);
						switch($theCmd) {
							case 'multiple':
							if (isset($inputArr[$theField]) && !$this->isPreview()) {

								$inputArr[$theField] = explode(',', $inputArr[$theField]);
							}
							break;
							case 'checkArray':
							if ($inputArr[$theField] && !$this->isPreview()) {
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
			$inputArr = $this->userProcess_alt($this->conf['userFunc_updateArray'], $this->conf['userFunc_updateArray.'], $inputArr );
			return $inputArr;
		}
		/**
		* Process the front end user reply to the confirmation request
		*
		* @return string  the template with substituted markers
		*/
		function procesSetFixed() {
			if ($this->setfixedEnabled) {
				$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, $this->recUid);
				$origUsergroup = $origArr['usergroup'];
				$setfixedUsergroup = '';
				if ($this->typoVersion >= 3006000) {
					$fD = t3lib_div::_GP('fD', 1);
				} else {
					$fD = t3lib_div::GPvar('fD', 1);
				}
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
				
				$theCode = $this->setfixedHash($origArr, $origArr['_FIELDLIST']);
				if (!strcmp($this->authCode, $theCode)) {
					if ($this->feUserData['sFK'] == 'DELETE' || $this->feUserData['sFK'] == 'REFUSE') {
						if (!$this->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
							// If the record is fully deleted... then remove the image attached.
							$this->deleteFilesFromRecord($this->recUid);
						}
						if ($this->typoVersion >= 3006000) {
							$res = $this->cObj->DBgetDelete($this->theTable, $this->recUid, true);
						} else {
							$query = $this->cObj->DBgetDelete($this->theTable, $this->recUid);
							mysql(TYPO3_db, $query);
							echo mysql_error();
						}
						$this->deleteMMRelations($this->theTable, $this->recUid);
					} else {
						if ($this->theTable == 'fe_users') {
							if ($this->conf['create.']['allowUserGroupSelection']) {
								$origArr['usergroup'] = implode(',', array_unique(array_merge(array_diff(t3lib_div::trimExplode(',', $origUsergroup, 1), t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'], 1)), t3lib_div::trimExplode(',', $setfixedUsergroup, 1))));
							} elseif ($this->feUserData['sFK'] == 'APPROVE' && $origUsergroup != $this->conf['create.']['overrideValues.']['usergroup']) {
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
						$newFieldList = implode(array_intersect(t3lib_div::trimExplode(',', $this->fieldList), t3lib_div::trimExplode(',', implode($fieldArr, ','), 1)), ',');
						if ($this->typoVersion >= 3006000) {
							$res = $this->cObj->DBgetUpdate($this->theTable, $this->recUid, $origArr, $newFieldList, true);
						} else {
							$query = $this->cObj->DBgetUpdate($this->theTable, $this->recUid, $origArr, $newFieldList);
							mysql(TYPO3_db, $query);
							echo mysql_error();
						}
						$this->currentArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable,$theUid);
						$this->userProcess_alt($this->conf['setfixed.']['userFunc_afterSave'],$this->conf['setfixed.']['userFunc_afterSave.'],array('rec'=>$this->currentArr, 'origRec'=>$origArr));

							// Hook: confirmRegistrationClass_postProcess
						foreach($hookObjectsArr as $hookObj)    {
							if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
								$hookObj->confirmRegistrationClass_postProcess($origArr, $this);
							}
						} 
					}

					// Outputting template
					if ($this->theTable == 'fe_users' && ($this->feUserData['sFK'] == 'APPROVE' || $this->feUserData['sFK'] == 'ENTER')) {
						$this->markerArray = $this->addMd5LoginMarkers($this->markerArray);
						if($this->useMd5Password) {
							$origArr['password'] = '';
						}
					}
					$setfixedSufffix = $this->feUserData['sFK'];
					if ($this->conf['enableAdminReview'] && $this->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
						$setfixedSufffix .= '_REVIEW';
					}
					$content = $this->getPlainTemplate('###TEMPLATE_' . $this->setfixedPrefix . 'OK_' . $setfixedSufffix . '###', $origArr);
					if (!$content) {
						$content = $this->getPlainTemplate('###TEMPLATE_' . $this->setfixedPrefix .'OK###', $origArr);
					}
					// Compiling email
					$this->dataArr = $origArr;
					$this->compileMail(
					$this->setfixedPrefix.$setfixedSufffix,
						array($origArr),
						$origArr[$this->conf['email.']['field']],
						$this->conf['setfixed.'] );
					
					if ($this->theTable == 'fe_users') { 
							// If applicable, send admin a request to review the registration request
						if ($this->conf['enableAdminReview'] && $this->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
							$this->compileMail(
								$this->setfixedPrefix.'REVIEW',
								array($origArr),
								$this->conf['email.']['admin'],
								$this->conf['setfixed.'] );
						}
							// Auto-login on confirmation
						if ($this->conf['enableAutoLoginOnConfirmation'] && ($this->feUserData['sFK'] == 'APPROVE' || $this->feUserData['sFK'] == 'ENTER')) {
							$loginVars = array();
							$loginVars['user'] = $origArr['username'];
							$loginVars['pass'] = $origArr['password'];
							$loginVars['pid'] = $this->thePid;
							$loginVars['logintype'] = 'login';
							$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
							header('Location: '.t3lib_div::locationHeaderUrl($this->site_url.$this->cObj->getTypoLink_URL($this->loginPID.','.$GLOBALS['TSFE']->type, $loginVars)));
							exit;
						}
					}
				} else {
					$content = $this->getPlainTemplate('###TEMPLATE_SETFIXED_FAILED###');
				}
			}
			return $content;
		}
		
		/**
		 * Prepares an email message
		 *
		 * @param string  $key: template key
		 * @param array  $DBrows: invoked with just one row of fe_users!!
		 * @param string  $recipient: an email or the id of a front user
		 * @param array  $setFixedConfig: a setfixed TS config array
		 * @return void
		 */
		function compileMail($key, $DBrows, $recipient, $setFixedConfig = array()) {
			$viewOnly = true;
			$mailContent = '';
			$userContent['all'] = '';
			$HTMLContent['all'] = '';
			$adminContent['all'] = '';
			if ($this->conf['email.'][$key] || ($this->setfixedEnabled && ($key == 'SETFIXED_CREATE' || $key == 'SETFIXED_CREATE_REVIEW' || $key == 'SETFIXED_INVITE' || $key == 'SETFIXED_REVIEW'))) {
				$userContent['all'] = trim($this->cObj->getSubpart($this->templateCode, '###'.$this->emailMarkPrefix.$key.'###'));
				$userContent['all'] = $this->removeRequired($userContent['all']);
				$HTMLContent['all'] = ($this->HTMLMailEnabled && $this->dataArr['module_sys_dmail_html']) ? trim($this->cObj->getSubpart($this->templateCode, '###'.$this->emailMarkPrefix.$key.$this->emailMarkHTMLSuffix.'###')):'';
				$HTMLContent['all'] = $this->removeRequired($HTMLContent['all']);
			}
			if ($this->conf['notify.'][$key] ) {
				$adminContent['all'] = trim($this->cObj->getSubpart($this->templateCode, '###'.$this->emailMarkPrefix.$key.$this->emailMarkAdminSuffix.'###'));
				$adminContent['all'] = $this->removeRequired($adminContent['all']);
			}
			$userContent['rec'] = $this->cObj->getSubpart($userContent['all'], '###SUB_RECORD###');
			$HTMLContent['rec'] = $this->cObj->getSubpart($HTMLContent['all'], '###SUB_RECORD###');
			$adminContent['rec'] = $this->cObj->getSubpart($adminContent['all'], '###SUB_RECORD###');
			 
			reset($DBrows);
			while (list(, $r) = each($DBrows)) {
				$markerArray = $this->cObj->fillInMarkerArray($this->markerArray, $r, '', 0);
				$markerArray['###SYS_AUTHCODE###'] = $this->authCode($r);
				$markerArray = $this->setfixed($markerArray, $setFixedConfig, $r);
				$markerArray = $this->addStaticInfoMarkers($markerArray, $r, $viewOnly);
				$markerArray = $this->addTcaMarkers($markerArray, $r, $viewOnly);
				$markerArray = $this->addFileUploadMarkers('image', $markerArray, $r, $viewOnly);
				$markerArray = $this->addLabelMarkers($markerArray, $r);
				if ($userContent['rec']) {
					$userContent['rec'] = $this->removeStaticInfoSubparts($userContent['rec'], $markerArray, $viewOnly);
					$userContent['accum'] .= $this->cObj->substituteMarkerArray($userContent['rec'], $markerArray);
				}

				if ($HTMLContent['rec']) {
					$HTMLContent['rec'] = $this->removeStaticInfoSubparts($HTMLContent['rec'], $markerArray, $viewOnly);
					$HTMLContent['accum'] .= $this->cObj->substituteMarkerArray($HTMLContent['rec'], $markerArray);
				}
				if ($adminContent['rec']) {
					$adminContent['rec'] = $this->removeStaticInfoSubparts($adminContent['rec'], $markerArray, $viewOnly);
					$adminContent['accum'] .= $this->cObj->substituteMarkerArray($adminContent['rec'], $markerArray);

				}
			}
			
				// Substitute the markers and eliminate HTML markup from plain text versions
			if ($userContent['all']) {
				$userContent['final'] .= strip_tags($this->cObj->substituteSubpart($userContent['all'], '###SUB_RECORD###', $userContent['accum']));
				$userContent['final'] = $this->removeHTMLComments($userContent['final']);
				$userContent['final'] = $this->replaceHTMLBr($userContent['final']);
			}
			if ($HTMLContent['all']) {
				$HTMLContent['final'] .= $this->cObj->substituteSubpart($HTMLContent['all'], '###SUB_RECORD###', $this->pi_wrapInBaseClass($HTMLContent['accum']));
				$HTMLContent['final'] = $this->cObj->substituteMarkerArray($HTMLContent['final'], $markerArray);			 
			}
			if ($adminContent['all']) {
				$adminContent['final'] .= $this->cObj->substituteSubpart($adminContent['all'], '###SUB_RECORD###', $adminContent['accum']);
				$adminContent['final'] = $this->removeHTMLComments($adminContent['final']);
				$adminContent['final'] = $this->replaceHTMLBr($adminContent['final']);
			}
			 
			if (t3lib_div::testInt($recipient)) {
				$fe_userRec = $GLOBALS['TSFE']->sys_page->getRawRecord('fe_users', $recipient);
				$recipient = $fe_userRec['email'];
			}
			
				// Check if we need to add an attachment
			if ($this->conf['addAttachment'] && $this->conf['addAttachment.']['cmd'] == $this->cmd && $this->conf['addAttachment.']['sFK'] == $this->feUserData['sFK']) {
				$file = ($this->conf['addAttachment.']['file']) ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['addAttachment.']['file']):
				'';
			}
			$this->sendMail($recipient, $this->conf['email.']['admin'], $userContent['final'], $adminContent['final'], $HTMLContent['final'], $file);
		}
		
		function removeHTMLComments($content) {
			return preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/','',$content);
		}
		
		function replaceHTMLBr($content) {
			return preg_replace('/<br\s?\/>/',chr(10),$content);
		}
		
		/**
		* Dispatches the email messsage
		*
		* @param string  $recipient: email address
		* @param string  $admin: email address
		* @param string  $content: plain content for the recipient
		* @param string  $adminContent: plain content for admin
		* @param string  $HTMLContent: HTML content for the recipient
		* @param string  $fileAttachment: file name
		* @return void
		*/
		function sendMail($recipient, $admin, $content = '', $adminContent = '', $HTMLContent = '', $fileAttachment = '') {
			// Send mail to admin
			if ($admin && $adminContent) {
				$this->cObj->sendNotifyEmail($adminContent, $admin, '', $this->conf['email.']['from'], $this->conf['email.']['fromName'], $recipient);
			}
			// Send mail to front end user
			if ($this->HTMLMailEnabled && $HTMLContent && ($this->dataArr['module_sys_dmail_html'] || ($this->saved && $this->currentArr['module_sys_dmail_html']))) {
				$this->sendHTMLMail($HTMLContent, $content, $recipient, '', $this->conf['email.']['from'], $this->conf['email.']['fromName'], '', $fileAttachment);
			} else {
				$this->cObj->sendNotifyEmail($content, $recipient, '', $this->conf['email.']['from'], $this->conf['email.']['fromName']);
			}
		}
		
		/**
		* Invokes the HTML mailing class
		*
		* @param string  $HTMLContent: HTML version of the message
		* @param string  $PLAINContent: plain version of the message
		* @param string  $recipient: email address
		* @param string  $dummy: ''
		* @param string  $fromEmail: email address
		* @param string  $fromName: name
		* @param string  $replyTo: email address
		* @param string  $fileAttachment: file name
		* @return void
		*/
		function sendHTMLMail($HTMLContent, $PLAINContent, $recipient, $dummy, $fromEmail, $fromName, $replyTo = '', $fileAttachment = '') {
			// HTML
			if (trim($recipient)) {
				$parts = spliti('<title>|</title>', $HTMLContent, 3);
				$subject = trim($parts[1]) ? strip_tags(trim($parts[1])) :
				'Front end user registration message';
				 
				$Typo3_htmlmail = t3lib_div::makeInstance('tx_srfeuserregister_pi1_t3lib_htmlmail');
				$Typo3_htmlmail->charset = $this->charset;
				$Typo3_htmlmail->start();
				$Typo3_htmlmail->mailer = 'Typo3 HTMLMail';		 
				$Typo3_htmlmail->subject = $Typo3_htmlmail->convertName($subject);
				$Typo3_htmlmail->from_email = $fromEmail;
				$Typo3_htmlmail->returnPath = $fromEmail;
				$Typo3_htmlmail->from_name = $fromName;
				$Typo3_htmlmail->from_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->from_name));
				$Typo3_htmlmail->replyto_email = $replyTo ? $replyTo :$fromEmail;
				$Typo3_htmlmail->replyto_name = $replyTo ? '' : $fromName;
				$Typo3_htmlmail->replyto_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->replyto_name));
				$Typo3_htmlmail->organisation = '';
				$Typo3_htmlmail->priority = 3;
				 
				// ATTACHMENT
				if ($fileAttachment && file_exists($fileAttachment)) {
					$Typo3_htmlmail->addAttachment($fileAttachment);
				}
				 
				// HTML
				if (trim($HTMLContent)) {
					$Typo3_htmlmail->theParts['html']['content'] = $HTMLContent;
					$Typo3_htmlmail->theParts['html']['path'] = '';
					$Typo3_htmlmail->extractMediaLinks();
					$Typo3_htmlmail->extractHyperLinks();
					$Typo3_htmlmail->fetchHTMLMedia();
					$Typo3_htmlmail->substMediaNamesInHTML(0); // 0 = relative
					$Typo3_htmlmail->substHREFsInHTML();
					 
					$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
				}
				// PLAIN
				$Typo3_htmlmail->addPlain($PLAINContent);
				// SET Headers and Content
				$Typo3_htmlmail->setHeaders();
				$Typo3_htmlmail->setContent();
				$Typo3_htmlmail->setRecipient($recipient);
				$Typo3_htmlmail->sendtheMail();
			}
		}
		/**
		* Computes the authentication code
		*
		* @param array  $r: the data array
		* @param string  $extra: some extra mixture
		* @return string  the code
		*/
		function authCode($r, $extra = '') {
			$l = $this->codeLength;
			if ($this->conf['authcodeFields']) {
				$fieldArr = t3lib_div::trimExplode(',', $this->conf['authcodeFields'], 1);
				$value = '';
				while (list(, $field) = each($fieldArr)) {
					$value .= $r[$field].'|';
				}
				$value .= $extra.'|'.$this->conf['authcodeFields.']['addKey'];
				if ($this->conf['authcodeFields.']['addDate']) {
					$value .= '|'.date($this->conf['authcodeFields.']['addDate']);
				}
				$value .= $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
				return substr(md5($value), 0, $l);
			}
		}
		/**
		* Authenticates a record
		*
		* @param array  $r: the record
		* @return boolean  true if the record is authenticated
		*/
		function aCAuth($r) {
			if ($this->authCode && !strcmp($this->authCode, $this->authCode($r))) {
				return true;
			}
		}
		/**
		* Computes the setfixed url's
		*
		* @param array  $markerArray: the input marker array
		* @param array  $setfixed: the TS setup setfixed configuration
		* @param array  $r: the record
		* @return array  the output marker array
		*/
		function setfixed($markerArray, $setfixed, $r) {
			if ($this->setfixedEnabled && is_array($setfixed) ) {
				$setfixedpiVars = array();
				
					// Find prefix for links
				if ($GLOBALS['TSFE']->config['config']['baseURL']) {
					$urlPrefix = intval($GLOBALS['TSFE']->config['config']['baseURL']) ? $this->site_url : $GLOBALS['TSFE']->config['config']['baseURL'];
				} else {
					$urlPrefix = $GLOBALS['TSFE']->absRefPrefix ? $GLOBALS['TSFE']->absRefPrefix : $this->site_url;
				}
				
				reset($setfixed);
				while (list($theKey, $data) = each($setfixed)) {
					if (strstr($theKey, '.') ) {
						$theKey = substr($theKey, 0, -1);
					}
					unset($setfixedpiVars);
					$recCopy = $r;
					$setfixedpiVars[$this->prefixId.'[rU]'] = $r[uid];

					if ( $this->theTable != 'fe_users' && $theKey == 'EDIT' ) {
						$setfixedpiVars[$this->prefixId.'[cmd]'] = 'edit';
						if (is_array($data) ) {
							reset($data);
							while (list($fieldName, $fieldValue) = each($data)) {
								$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
								$recCopy[$fieldName] = $fieldValue;
							}
						}
						if( $this->conf['edit.']['setfixed'] ) {
							$setfixedpiVars[$this->prefixId.'[aC]'] = $this->setfixedHash($recCopy, $data['_FIELDLIST']);
						} else {
							$setfixedpiVars[$this->prefixId.'[aC]'] = $this->authCode($r);
						}
						$linkPID = $this->editPID;
					} else {
						$setfixedpiVars[$this->prefixId.'[cmd]'] = 'setfixed';
						$setfixedpiVars[$this->prefixId.'[sFK]'] = $theKey;
						if (is_array($data) ) {
							reset($data);
							while (list($fieldName, $fieldValue) = each($data)) {
								$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
								$recCopy[$fieldName] = $fieldValue;
							}
						}
						$setfixedpiVars[$this->prefixId.'[aC]'] = $this->setfixedHash($recCopy, $data['_FIELDLIST']);
						$linkPID = $this->confirmPID;
						if ($this->cmd == 'invite') {
							$linkPID = $this->confirmInvitationPID;
						}
					}
					if ($this->typoVersion >= 3006000) {
						if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
							$setfixedpiVars['L'] = t3lib_div::_GP('L');
						}
					} else {
						if (t3lib_div::GPvar('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
							$setfixedpiVars['L'] = t3lib_div::GPvar('L');
						}
					}
					$markerArray['###SETFIXED_'.strtoupper($theKey).'_URL###'] = $urlPrefix . $this->cObj->getTypoLink_URL($linkPID.','.$this->confirmType, $setfixedpiVars);
				}
			}
			return $markerArray;
		}
		
		/**
		* Computes the setfixed hash
		*
		* @param array  $recCopy: copy of the record
		* @param string  $fields: the list of fields to include in the hash computation
		* @return string  the hash value
		*/
		function setfixedHash($recCopy, $fields = '') {
			if ($fields) {
				$fieldArr = t3lib_div::trimExplode(',', $fields, 1);
				reset($fieldArr);
				while (list($k, $v) = each($fieldArr)) {
					$recCopy_temp[$k] = $recCopy[$v];
				}
			} else {
				$recCopy_temp = $recCopy;
			}
			$encStr = implode('|', $recCopy_temp).'|'.$this->conf['authcodeFields.']['addKey'].'|'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
			$hash = substr(md5($encStr), 0, $this->codeLength);
			return $hash;
		}
		/**
		* Checks if preview display is on.
		*
		* @return boolean  true if preview display is on
		*/
		function isPreview() {
			return ($this->conf[$this->cmdKey.'.']['preview'] && $this->feUserData['preview']);
		}
		/**
		* Instantiate the file creation function
		*
		* @return void
		*/
		function createFileFuncObj() {
			if (!$this->fileFunc) {
				$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
			}
		}

		/**
		* Adds form element markers from the Table Configuration Array to a marker array
		*
		* @param array  $markerArray: the input marker array
		* @param array  $dataArray: the record array
		* @return array  the output marker array
		*/
		function addTcaMarkers($markerArray, $dataArray = '', $viewOnly = false) {
			global $TCA;
			if ($this->typoVersion >= 3006000) global $TYPO3_DB;
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
				if (t3lib_div::inList($this->conf[$this->cmdKey.'.']['fields'], $colName)) {
					$colConfig = $colSettings['config'];
					$colContent = '';
					if ($this->previewLabel || $viewOnly) {
						// Configure preview based on input type
						switch ($colConfig['type']) {
							//case 'input':
							case 'text':
								$colContent = str_replace(chr(10), '<br />', $dataArray[$colName]);
								break;
							case 'check':
								// <Ries van Twisk added support for multiple checkboxes>
								if (is_array($colConfig['items'])) {
									$colContent = '<ul class="tx-srfeuserregister-multiple-checked-values">';
									foreach ($colConfig['items'] AS $key => $value) {
										$checked = ($dataArray[$colName] & (1 << $key)) ? 'checked' : '';
										$colContent .= $checked ? '<li>' . $this->getLLFromString($colConfig['items'][$key][0]) . '</li>' : '';
									}
									$colContent .= '</ul>';
									// </Ries van Twisk added support for multiple checkboxes>
								} else {
									$colContent = $dataArray[$colName] ? 'checked' : 'not checked';
								}
								break;
							case 'radio':
								if ($dataArray[$colName] != '') {
									$colContent = $this->getLLFromString($colConfig['items'][$dataArray[$colName]][0]);
								}
								break;
							case 'select':
								if ($dataArray[$colName] != '') {
									$valuesArray = is_array($dataArray[$colName]) ? $dataArray[$colName] : explode(',',$dataArray[$colName]);
									if (is_array($colConfig['items'])) {
										for ($i = 0; $i < count ($valuesArray); $i++) {
											$colContent .= ($i ? '<br />': '') . $this->getLLFromString($colConfig['items'][$valuesArray[$i]][0]);
										}
									} 
									if ($this->typoVersion >= 3006000 && $colConfig['foreign_table']) {
										$reservedValues = array();
										if ($this->theTable == 'fe_users' && $colName == 'usergroup') {
											$reservedValues = array_merge(t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['APPROVE.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['ACCEPT.']['usergroup'],1));
										}
										$valuesArray = array_diff($valuesArray, $reservedValues);
										if (!empty($valuesArray)) {
											$titleField = $TCA[$colConfig['foreign_table']]['ctrl']['label'];
											$res = $TYPO3_DB->exec_SELECTquery($titleField, $colConfig['foreign_table'],
												'uid IN ('.implode(',', $valuesArray).')');
											$i = 0;
											while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
												if ($this->theTable == 'fe_users' && $colName == 'usergroup') {
													$row = $this->getUsergroupOverlay($row);
												}
												$colContent .= ($i++ ? '<br />': '') . $row[$titleField];
											}
										}
									}
								}
								break;
							default:
								// unsupported input type
								$colContent .= $colConfig['type'].':'.$this->pi_getLL('unsupported');
						}
					} else {
						// Configure inputs based on TCA type
						switch ($colConfig['type']) {
/*
							case 'input':
								$colContent = '<input type="input" name="FE['.$this->theTable.']['.$colName.']"'.
									' size="'.($colConfig['size']?$colConfig['size']:30).'"';
								if ($colConfig['max']) {
										$colContent .= ' maxlength="'.$colConfig['max'].'"';
								}
								if ($colConfig['default']) {
									$colContent .= ' value="'.$this->getLLFromString($colConfig['default']).'"';
								}
								$colContent .= ' />';
								break;
*/
							case 'text':
								$colContent = '<textarea id="'. $this->pi_getClassName($colName) . '" name="FE['.$this->theTable.']['.$colName.']"'.
									' title="###TOOLTIP_' . (($this->cmd == 'invite')?'INVITATION_':'') . strtoupper($colName).'###"'.
									' cols="'.($colConfig['cols']?$colConfig['cols']:30).'"'.
									' rows="'.($colConfig['rows']?$colConfig['rows']:5).'"'.
									' wrap="'.($colConfig['wrap']?$colConfig['wrap']:'virtual').'"'.
									'>'.($colConfig['default']?$this->getLLFromString($colConfig['default']):'').'</textarea>';
								break;
							case 'check':
								if (is_array($colConfig['items'])) {
									// <Ries van Twisk added support for multiple checkboxes>
									$colContent  = '<ul id="'. $this->pi_getClassName($colName) . '" class="tx-srfeuserregister-multiple-checkboxes">';
									foreach ($colConfig['items'] AS $key => $value) {
										$checked = ($dataArray[$colName] & (1 << $key))?'checked="checked"':'';
										$colContent .= '<li><input type="checkbox"' . $this->pi_classParam('checkbox') . 'id="' . $this->pi_getClassName($colName) . '-' . $key .  '" name="FE['.$this->theTable.']['.$colName.'][]" value="'.$key.'" '.$checked.' /><label for="' . $this->pi_getClassName($colName) . '-' . $key .  '">'.$this->getLLFromString($colConfig['items'][$key][0]).'</label></li>';					
									}
									$colContent .= '</ul>';
									// </Ries van Twisk added support for multiple checkboxes>
								} else {
									$colContent = '<input type="checkbox"' . $this->pi_classParam('checkbox') . 'id="'. $this->pi_getClassName($colName) . '" name="FE['.$this->theTable.']['.$colName.']"' . ' value="1" />';
								}
								break;

							case 'radio':
								for ($i = 0; $i < count ($colConfig['items']); ++$i) {
									$colContent .= '<input type="radio"' . $this->pi_classParam('radio') . 'id="'. $this->pi_getClassName($colName) . '-' . $i . '" name="FE['.$this->theTable.']['.$colName.']"'.
											' value="'.$i.'" '.($i==0?'checked="checked"':'').' />' .
											'<label for="' . $this->pi_getClassName($colName) . '-' . $i . '">' . $this->getLLFromString($colConfig['items'][$i][0]) . '</label>';
								}
								break;

							case 'select':
								$valuesArray = is_array($dataArray[$colName]) ? $dataArray[$colName] : explode(',',$dataArray[$colName]);
								if (!$valuesArray[0] && $colConfig['default']) {
									$valuesArray[] = $colConfig['default'];
								}
								if ($colConfig['maxitems'] > 1) {
									$multiple = '[]" multiple="multiple';
								} else {
									$multiple = '';
								}
								if ($this->theTable == 'fe_users' && $colName == 'usergroup' && !$this->conf['allowMultipleUserGroupSelection']) {
									$multiple = '';
								}
								$colContent = '<select id="'. $this->pi_getClassName($colName) . '" name="FE['.$this->theTable.']['.$colName.']' . $multiple . '" title="###TOOLTIP_' . (($this->cmd == 'invite')?'INVITATION_':'') . strtoupper($colName).'###">';
								if (is_array($colConfig['items'])) {
									for ($i = 0; $i < count ($colConfig['items']); $i++) {
										$colContent .= '<option value="'.$colConfig['items'][$i][1]. '" ' . (in_array($colConfig['items'][$i][1], $valuesArray) ? 'selected="selected"' : '') . '>' . $this->getLLFromString($colConfig['items'][$i][0]).'</option>';
									}
								}
								if ($this->typoVersion >= 3006000 && $colConfig['foreign_table']) {
									$titleField = $TCA[$colConfig['foreign_table']]['ctrl']['label'];
									if ($this->theTable == 'fe_users' && $colName == 'usergroup') {
										$reservedValues = array_merge(t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['APPROVE.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['ACCEPT.']['usergroup'],1));
										$selectedValue = false;
									}
									$whereClause = ($this->theTable == 'fe_users' && $colName == 'usergroup') ? ' pid='.intval($this->thePid).' ' : ' 1=1 ';
									$whereClause .= $this->cObj->enableFields($colConfig['foreign_table']);
									$res = $TYPO3_DB->exec_SELECTquery('uid,'.$titleField, $colConfig['foreign_table'], $whereClause);
									if(!in_array($colName, $this->requiredArr)) {
										$colContent .= '<option value="" ' . ($valuesArray[0] ? '' : 'selected="selected"') . '></option>';
									}
									while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
										if ($this->theTable == 'fe_users' && $colName == 'usergroup') {
											if (!in_array($row['uid'], $reservedValues)) {
												$row = $this->getUsergroupOverlay($row);
												$selected = (in_array($row['uid'], $valuesArray) ? 'selected="selected"' : '');
												if(!$this->conf['allowMultipleUserGroupSelection'] && $selectedValue) {
													$selected = '';
												}
												$selectedValue = $selected ? true: $selectedValue;
												$colContent .= '<option value="'.$row['uid'].'"' . $selected . '>'.$row[$titleField].'</option>';
											}
										} else {
											$colContent .= '<option value="'.$row['uid'].'"' . (in_array($row['uid'], $valuesArray) ? 'selected="selected"' : '') . '>'.$row[$titleField].'</option>';
										}
									}
								}
								$colContent .= '</select>';
								break;
							default:
								$colContent .= $colConfig['type'].':'.$this->pi_getLL('unsupported');
						}
					}
					if ($this->previewLabel || $viewOnly) {
						$markerArray['###TCA_INPUT_VALUE_'.$colName.'###'] = $colContent;
					}
					$markerArray['###TCA_INPUT_'.$colName.'###'] = $colContent;
				}
			}
			return $markerArray;
		}
		// <Ries van Twisk added support for multiple checkboxes>
		/**
		* Check what bit is set and returns the bitnumber
		* @param	int	Number to check, ex: 16 returns 4, 32 returns 5, 0 returns -1, 1 returns 0
		* @ return	bool	Bitnumber, -1 for not found
		*/
		function _whatBit($num) {
			$num = intval($num);
			if ($num == 0) return -1;
			for ($i=0; $i<32; $i++) {
				if ($num & (1 << $i)) return $i;
			}
			return -1;
		}
		// </Ries van Twisk added support for multiple checkboxes>
		/**
		* Adds language-dependent label markers
		*
		* @param array  $markerArray: the input marker array
		* @param array  $dataArray: the record array
		* @return array  the output marker array
		*/
		function addLabelMarkers($markerArray, $dataArray) {

			// Data field labels
			$infoFields = explode(',', $this->fieldList);
			while (list(, $fName) = each($infoFields) ) {
				$markerArray['###LABEL_'.strtoupper($fName).'###'] = $this->pi_getLL($fName) ? $this->pi_getLL($fName) : $this->getLLFromString($this->TCA['columns'][$fName]['label']);
				$markerArray['###TOOLTIP_'.strtoupper($fName).'###'] = $this->pi_getLL('tooltip_' . $fName);
				$markerArray['###TOOLTIP_INVITATION_'.strtoupper($fName).'###'] = $this->pi_getLL('tooltip_invitation_' . $fName);
				// <Ries van Twisk added support for multiple checkboxes>
				if (is_array($dataArray[$fName])) {
					$colContent = '';
					$markerArray['###FIELD_'.$fName.'_CHECKED###'] = '';
					$markerArray['###LABEL_'.$fName.'_CHECKED###'] = '';
					$this->dataArr['###POSTVARS_'.$fName.'###'] = ''; 
					foreach ($dataArray[$fName] AS $key => $value) {
						$colConfig = $this->TCA['columns'][$fName]['config'];
						$markerArray['###FIELD_'.$fName.'_CHECKED###'] .= '- '.$this->getLLFromString($colConfig['items'][$value][0]).'<br />';
						$markerArray['###LABEL_'.$fName.'_CHECKED###'] .= '- '.$this->getLLFromString($colConfig['items'][$value][0]).'<br />';
						$markerArray['###POSTVARS_'.$fName.'###'] .= chr(10).'	<input type="hidden" name="FE[fe_users]['.$fName.']['.$key.']" value ="'.$value.'" />';
					}
				// </Ries van Twisk added support for multiple checkboxes>
				} else {
					$markerArray['###FIELD_'.$fName.'_CHECKED###'] = ($dataArray[$fName])?'checked':'';
					$markerArray['###LABEL_'.$fName.'_CHECKED###'] = ($dataArray[$fName])?$this->pi_getLL('yes'):$this->pi_getLL('no');
				}
				if (in_array(trim($fName), $this->requiredArr) ) {
					$markerArray['###REQUIRED_'.strtoupper($fName).'###'] = '<span>*</span>';
					$markerArray['###MISSING_'.strtoupper($fName).'###'] = $this->pi_getLL('missing_'.$fName);
					$markerArray['###MISSING_INVITATION_'.strtoupper($fName).'###'] = $this->pi_getLL('missing_invitation_'.$fName);
				} else {
					$markerArray['###REQUIRED_'.strtoupper($fName).'###'] = '';
				}
			}
			// Button labels
			$buttonLabelsList = 'register,confirm_register,back_to_form,update,confirm_update,enter,confirm_delete,cancel_delete,update_and_more';
			$buttonLabels = t3lib_div::trimExplode(',', $buttonLabelsList);
			while (list(, $labelName) = each($buttonLabels) ) {
				$markerArray['###LABEL_BUTTON_'.strtoupper($labelName).'###'] = $this->pi_getLL('button_'.$labelName);
			}
			// Labels possibly with variables
			$otherLabelsList = 'yes,no,password_repeat,tooltip_password_again,tooltip_invitation_password_again,click_here_to_register,tooltip_click_here_to_register,click_here_to_edit,tooltip_click_here_to_edit,click_here_to_delete,tooltip_click_here_to_delete'.
				',copy_paste_link,enter_account_info,enter_invitation_account_info,required_info_notice,excuse_us,'.
				',tooltip_login_username,tooltip_login_password,'.
				',registration_problem,registration_sorry,registration_clicked_twice,registration_help,kind_regards,kind_regards_cre,kind_regards_del,kind_regards_ini,kind_regards_inv,kind_regards_upd'.
				',v_verify_before_create,v_verify_invitation_before_create,v_verify_before_update,v_really_wish_to_delete,v_edit_your_account'.
				',v_dear,v_now_enter_your_username,v_notification'.
				',v_registration_created,v_registration_created_subject,v_registration_created_message1,v_registration_created_message2,v_registration_created_message3'.
				',v_to_the_administrator'.
				',v_registration_review_subject,v_registration_review_message1,v_registration_review_message2,v_registration_review_message3'.
				',v_please_confirm,v_your_account_was_created,v_follow_instructions1,v_follow_instructions2,v_follow_instructions_review1,v_follow_instructions_review2'.
				',v_invitation_confirm,v_invitation_account_was_created,v_invitation_instructions1'.
				',v_registration_initiated,v_registration_initiated_subject,v_registration_initiated_message1,v_registration_initiated_message2,v_registration_initiated_message3,v_registration_initiated_review1,v_registration_initiated_review2'.
				',v_registration_invited,v_registration_invited_subject,v_registration_invited_message1,v_registration_invited_message2'.
				',v_registration_confirmed,v_registration_confirmed_subject,v_registration_confirmed_message1,v_registration_confirmed_message2,v_registration_confirmed_review1,v_registration_confirmed_review2'.
				',v_registration_cancelled,v_registration_cancelled_subject,v_registration_cancelled_message1,v_registration_cancelled_message2'.
				',v_registration_accepted,v_registration_accepted_subject,v_registration_accepted_message1,v_registration_accepted_message2'.
				',v_registration_refused,v_registration_refused_subject,v_registration_refused_message1,v_registration_refused_message2'.
				',v_registration_accepted_subject2,v_registration_accepted_message3,v_registration_accepted_message4'.
				',v_registration_refused_subject2,v_registration_refused_message3,v_registration_refused_message4'.
				',v_registration_entered_subject,v_registration_entered_message1,v_registration_entered_message2'.
				',v_registration_updated,v_registration_updated_subject,v_registration_updated_message1'.
				',v_registration_deleted,v_registration_deleted_subject,v_registration_deleted_message1,v_registration_deleted_message2';
			$otherLabels = t3lib_div::trimExplode(',', $otherLabelsList);
			while (list(, $labelName) = each($otherLabels) ) {
				$markerArray['###LABEL_'.strtoupper($labelName).'###'] = sprintf($this->pi_getLL($labelName), $this->thePidTitle, $dataArray['username'], $dataArray['name'], $dataArray['email'], $dataArray['password']); 
			}
			return $markerArray;
		}
		/**
		* Adds URL markers to a $markerArray
		*
		* @param array  $markerArray: the input marker array
		* @return array  the output marker array
		*/
		function addURLMarkers($markerArray) {
			$vars = array();
			$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview';
			$unsetVars = t3lib_div::trimExplode(',', $unsetVarsList);

			$unsetVars['cmd'] = 'cmd';
			$markerArray['###FORM_URL###'] = $this->get_url('', $GLOBALS['TSFE']->id.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			$markerArray['###FORM_NAME###'] = $this->conf['formName'];
			$unsetVars['cmd'] = '';

			$vars['cmd'] = 'delete';
			$vars['backURL'] = rawurlencode($markerArray['###FORM_URL###']);
			$vars['rU'] = $this->recUid;
			$vars['preview'] = '1';
			$markerArray['###DELETE_URL###'] = $this->get_url('', $this->editPID.','.$GLOBALS['TSFE']->type, $vars);
			 
			$vars['cmd'] = 'create';
			$markerArray['###REGISTER_URL###'] = $this->get_url('', $this->registerPID.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			$vars['cmd'] = 'edit';
			$markerArray['###EDIT_URL###'] = $this->get_url('', $this->editPID.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			$vars['cmd'] = 'login';
			$markerArray['###LOGIN_FORM###'] = $this->get_url('', $this->loginPID.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			$vars['cmd'] = 'infomail';
			$markerArray['###INFOMAIL_URL###'] = $this->get_url('', $this->infomailPID.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			 
			$markerArray['###THE_PID###'] = $this->thePid;
			$markerArray['###THE_PID_TITLE###'] = $this->thePidTitle;
			$markerArray['###BACK_URL###'] = $this->backURL;
			$markerArray['###SITE_NAME###'] = $this->conf['email.']['fromName'];
			$markerArray['###SITE_URL###'] = $this->site_url;
			$markerArray['###SITE_WWW###'] = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
			$markerArray['###SITE_EMAIL###'] = $this->conf['email.']['from'];

			$markerArray['###HIDDENFIELDS###'] = '';
			if($this->theTable == 'fe_users') {
				$markerArray['###HIDDENFIELDS###'] = ($this->cmd?'<input type="hidden" name="'.$this->prefixId.'[cmd]" value="'.$this->cmd.'">':'');
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($this->authCode?'<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->authCode.'">':'');
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($this->backURL?'<input type="hidden" name="'.$this->prefixId.'[backURL]" value="'.htmlspecialchars($this->backURL).'">':'');
			}
			return $markerArray;
		}
		
		/**
		 * Adds Static Info markers to a marker array
		 *
		 * @param array  $markerArray: the input marker array
		 * @param array  $dataArray: the record array
		 * @return array  the output marker array
		 */
		function addStaticInfoMarkers($markerArray, $dataArray = '', $viewOnly = false) {
			if ($this->previewLabel || $viewOnly) {
				$markerArray['###FIELD_static_info_country###'] = $this->staticInfo->getStaticInfoName('COUNTRIES', is_array($dataArray)?$dataArray['static_info_country']:'');
				$markerArray['###FIELD_zone###'] = $this->staticInfo->getStaticInfoName('SUBDIVISIONS', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'');
				if (!$markerArray['###FIELD_zone###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->theTable.'][zone]" value="">';
				}
				$markerArray['###FIELD_language###'] = $this->staticInfo->getStaticInfoName('LANGUAGES', is_array($dataArray)?$dataArray['language']:'');
			} else {
				if ($this->conf['templateStyle'] == 'css-styled') {
					$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector('COUNTRIES', 'FE['.$this->theTable.']'.'[static_info_country]', '', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->conf['onChangeCountryAttribute'], $this->pi_getClassName('static_info_country'), $this->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'static_info_country'));
					$markerArray['###SELECTOR_ZONE###'] = $this->staticInfo->buildStaticInfoSelector('SUBDIVISIONS', 'FE['.$this->theTable.']'.'[zone]', '', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->pi_getClassName('zone'), $this->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'zone'));
					if (!$markerArray['###SELECTOR_ZONE###'] ) {
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->theTable.'][zone]" value="">';
					}
					$markerArray['###SELECTOR_LANGUAGE###'] = $this->staticInfo->buildStaticInfoSelector('LANGUAGES', 'FE['.$this->theTable.']'.'[language]', '', is_array($dataArray)?$dataArray['language']:'', '', '', $this->pi_getClassName('language'), $this->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'language'));
				} else {
					$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector('COUNTRIES', 'FE['.$this->theTable.']'.'[static_info_country]', '', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->conf['onChangeCountryAttribute']);
					$markerArray['###SELECTOR_ZONE###'] = $this->staticInfo->buildStaticInfoSelector('SUBDIVISIONS', 'FE['.$this->theTable.']'.'[zone]', '', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'');
					if (!$markerArray['###SELECTOR_ZONE###'] ) {
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->theTable.'][zone]" value="">';
					}
					$markerArray['###SELECTOR_LANGUAGE###'] = $this->staticInfo->buildStaticInfoSelector('LANGUAGES', 'FE['.$this->theTable.']'.'[language]', '', is_array($dataArray)?$dataArray['language']:'');
				}
			}
			return $markerArray;
		}
		
		/**
		 * Adds md5 password create/edit form markers to a marker array
		 *
		 * @param array  $markerArray: the input marker array
		 * @return array  the output marker array
		 */
		function addMd5EventsMarkers($markerArray,$cmd) {
			$markerArray['###FORM_ONSUBMIT###'] = '';
			$markerArray['###PASSWORD_ONCHANGE###'] = '';
			if ($this->useMd5Password) {
				$mode = $this->incomingData ? 'edit' : $cmd;
				$GLOBALS['TSFE']->additionalHeaderData['MD5_script'] = '<script type="text/javascript" src="typo3/md5.js"></script>';
				$GLOBALS['TSFE']->JSCode .= $this->getMD5Submit($mode);
				$markerArray['###FORM_ONSUBMIT###'] = 'onSubmit="return enc_form(this);"';
				if ($mode == 'edit') {
					$markerArray['###PASSWORD_ONCHANGE###'] = 'onChange="pw_change=1; return true;"';
				}
			}
			return $markerArray;
		}
		
		/**
		 * Adds md5 password login form markers to a marker array
		 *
		 * @param array  $markerArray: the input marker array
		 * @return array  the output marker array
		 */
		function addMd5LoginMarkers($markerArray) {
			$onSubmit = '';
			$extraHidden = '';
			if ($this->useMd5Password) {
					// Hook used by kb_md5fepw extension by Kraft Bernhard <kraftb@gmx.net>
					// This hook allows to call User JS functions.
					// The methods should also set the required JS functions to get included
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['newloginbox']['loginFormOnSubmitFuncs'])) {
					$_params = array (); 
					$onSubmitAr = array();
					$extraHiddenAr = array();
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['newloginbox']['loginFormOnSubmitFuncs'] as $funcRef) {
						list($onSub, $hid) = t3lib_div::callUserFunction($funcRef, $_params, $this);
						$onSubmitAr[] = $onSub;
						$extraHiddenAr[] = $hid;
					}
				}
				if (count($onSubmitAr)) {
					$onSubmit = implode('; ', $onSubmitAr).'; return true;';
					$onSubmit = strlen($onSubmit) ? ' onSubmit="'.$onSubmit.'"' : '';
					$extraHidden = implode(chr(10), $extraHiddenAr);
				}

			}
			$markerArray['###FORM_ONSUBMIT###'] = $onSubmit;
			$markerArray['###HIDDENFIELDS###'] = $extraHidden;
			return $markerArray;
		}
		
		/**
		* Removes irrelevant Static Info subparts (zone selection when the country has no zone)
		*
		* @param string  $templateCode: the input template
		* @param array  $markerArray: the marker array
		* @return string  the output template
		*/
		function removeStaticInfoSubparts($templateCode, $markerArray, $viewOnly = false) {
			if ($this->previewLabel || $viewOnly) {
				if (!$markerArray['###FIELD_zone###'] ) {
					return $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
				}
			} else {
				if (!$markerArray['###SELECTOR_ZONE###'] ) {
					return $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', ''); 
				}
			}
			return $templateCode;
		}
		
		/**
		 * Adds CSS styles marker to a marker array for substitution in an HTML email message
		 *
		 * @param array  $markerArray: the input marker array
		 * @return array  the output marker array
		 */
		function addCSSStyleMarkers($markerArray) {
			if ($this->HTMLMailEnabled ) {
				if ($this->conf['templateStyle'] == 'css-styled') {
					$markerArray['###CSS_STYLES###'] = '	/*<![CDATA[*/
	<!--';
					$markerArray['###CSS_STYLES###'] .= $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
					$markerArray['###CSS_STYLES###'] .= '
	-->
	/*]]>*/';
				} else {
					$markerArray['###CSS_STYLES###'] = $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
				}
			}
			return $markerArray;
		}
		
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
		}
		/**
		* Adds uploading markers to a marker array
		*
		* @param string  $theField: the field name
		* @param array  $markerArray: the input marker array
		* @param array  $dataArray: the record array
		* @return array  the output marker array
		*/
		function addFileUploadMarkers($theField, $markerArray, $dataArr = array(), $viewOnly = false) {
			$filenames = array();
			if ($dataArr[$theField]) {
				$filenames = explode(',', $dataArr[$theField]);
			}
			if ($this->previewLabel || $viewOnly) {
				$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] = $this->buildFileUploader($theField, $this->TCA['columns'][$theField]['config'], $filenames, 'FE['.$this->theTable.']', true);
			} else {
				$markerArray['###UPLOAD_' . $theField . '###'] = $this->buildFileUploader($theField, $this->TCA['columns'][$theField]['config'], $filenames, 'FE['.$this->theTable.']');
			}
			return $markerArray;
		}
		
		/**
		 * Builds a file uploader
		 *
		 * @param string  $fName: the field name
		 * @param array  $config: the field TCA config
		 * @param array  $filenames: array of uploaded file names
		 * @param string  $prefix: the field name prefix
		 * @return string  generated HTML uploading tags
		 */
		function buildFileUploader($fName, $config, $filenames = array(), $prefix, $viewOnly = false) {

			$HTMLContent = '';
			$size = $config['maxitems'];
			$cmdParts = split('\[|\]', $this->conf[$this->cmdKey.'.']['evalValues.'][$fName]);
			if(!empty($cmdParts[1])) $size = min($size, intval($cmdParts[1]));
			$size = $size ? $size : 1;
			$number = $size - sizeof($filenames);
			$dir = $config['uploadfolder'];
			
			if ($this->previewLabel || $viewOnly) {
				for ($i = 0; $i < sizeof($filenames); $i++) {
					if ($this->conf['templateStyle'] == 'css-styled') {
						$HTMLContent .= $filenames[$i] . '<a href="' . $dir.'/' . $filenames[$i] . '"' . $this->pi_classParam('file-view') . '" target="_blank" title="' . $this->pi_getLL('file_view') . '">' . $this->pi_getLL('file_view') . '</a><br />';
					} else {
						$HTMLContent .= $filenames[$i] . '&nbsp;&nbsp;<small><a href="' . $dir.'/' . $filenames[$i] . '" target="_blank">' . $this->pi_getLL('file_view') . '</a></small><br />';
					}
				}
			} else {
				for($i = 0; $i < sizeof($filenames); $i++) {
					if ($this->conf['templateStyle'] == 'css-styled') {
						$HTMLContent .= $filenames[$i] . '<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($this->conf['icon_delete']) . '" name="'.$prefix.'['.$fName.']['.$i.'][submit_delete]" value="1" title="'.$this->pi_getLL('icon_delete').'" alt="' . $this->pi_getLL('icon_delete'). '"' . $this->pi_classParam('delete-icon') . ' onclick=\'if(confirm("' . $this->pi_getLL('confirm_file_delete') . '")) return true; else return false;\' />'
								. '<a href="' . $dir.'/' . $filenames[$i] . '"' . $this->pi_classParam('file-view') . 'target="_blank" title="' . $this->pi_getLL('file_view') . '">' . $this->pi_getLL('file_view') . '</a><br />';
					} else {
						$HTMLContent .= $filenames[$i] . '&nbsp;&nbsp;<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($this->conf['icon_delete']) . '" name="'.$prefix.'['.$fName.']['.$i.'][submit_delete]" value="1" title="'.$this->pi_getLL('icon_delete').'" alt="' . $this->pi_getLL('icon_delete'). '"' . $this->pi_classParam('icon') . ' onclick=\'if(confirm("' . $this->pi_getLL('confirm_file_delete') . '")) return true; else return false;\' />&nbsp;&nbsp;<small><a href="' . $dir.'/' . $filenames[$i] . '" target="_blank">' . $this->pi_getLL('file_view') . '</a></small><br />';
					}
					$HTMLContent .= '<input type="hidden" name="' . $prefix . '[' . $fName . '][' . $i . '][name]' . '" value="' . $filenames[$i] . '" />';
				}
				for ($i = sizeof($filenames); $i < $number + sizeof($filenames); $i++) {
					$HTMLContent .= '<input id="'. $this->pi_getClassName($fName) . '-' . ($i-sizeof($filenames)) . '" name="'.$prefix.'['.$fName.']['.$i.']'.'" title="' . $this->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'image') . '" size="40" type="file" '.$this->pi_classParam('uploader').' /><br />';
				}
			}
			
			return $HTMLContent;
		}
		
		function addHiddenFieldsMarkers($markerArray, $dataArr = array()) {
			if ($this->conf[$this->cmdKey.'.']['preview'] && !$this->previewLabel) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[preview]" value="1" />';
				if ($this->theTable == 'fe_users' && $this->cmdKey == 'edit' && $this->conf[$this->cmdKey.'.']['useEmailAsUsername']) {
					$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.'][username]" value="'. htmlspecialchars($dataArr['username']).'" />';
					$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.'][email]" value="'. htmlspecialchars($dataArr['email']).'" />';
				}
			}
			if ($this->previewLabel && $this->conf['templateStyle'] == 'css-styled') {
				$fields = explode(',', $this->conf[$this->cmdKey.'.']['fields']);
				$fields = array_diff($fields, array( 'hidden', 'disable'));
				if ($this->theTable == 'fe_users') {
					$fields[] = 'password_again';
					if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] || $this->conf[$this->cmdKey.'.']['generateUsername']) {
						$fields = array_merge($fields, array( 'username'));
					}
					if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername']) {
						$fields = array_merge($fields, array( 'email'));
					}
				}
				while (list(, $fName) = each($fields) ) {
					$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->theTable.']['.$fName.']" value="'. htmlspecialchars($dataArr[$fName]).'" />';
				}
			}
			return $markerArray;
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
		function get_url($tag = '', $id, $vars = array(), $unsetVars = array(), $usePiVars = true) {
			 
			$vars = (array) $vars;
			$unsetVars = (array) $unsetVars;
			if ($usePiVars) {
				$vars = array_merge($this->piVars, $vars); //vars override pivars
				while (list(, $key) = each($unsetVars)) {
					// unsetvars override anything
					unset($vars[$key]);
				}
			}
			while (list($key, $val) = each($vars)) {
				$piVars[$this->prefixId . '['. $key . ']'] = $val;
			}
			if ($tag) {
				return $this->cObj->getTypoLink($tag, $id, $piVars);
			} else {
				return $this->cObj->getTypoLink_URL($id, $piVars);
			}
		}
		/* evalDate($value)
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
		}
		/**
		* Transforms incoming timestamps into dates
		*
		* @return parsedArray
		*/
		function parseIncomingData($origArr = array()) {
			if ($this->typoVersion >= 3006000) global $TYPO3_DB;
			
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
							if($origArr[$theField]) {
								$parsedArr[$theField] = $this->adodbTime->adodb_date( 'Y-m-d', $origArr[$theField]);
							}
							if (!$parsedArr[$theField]) {
								unset($parsedArr[$theField]);
							}
							break;
						}

					}
				}
			}
			
			if ($this->typoVersion >= 3006000) {
			$fieldsList = array_keys($parsedArr);
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
				if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
					if (!$parsedArr[$colName]) {
						$parsedArr[$colName] = '';
					} else {
						$valuesArray = array();
						$res = $TYPO3_DB->exec_SELECTquery(
							'uid_local,uid_foreign,sorting',
							$colSettings['config']['MM'],
							'uid_local='.intval($parsedArr['uid']).$this->cObj->enableFields($colSettings['config']['foreign_table']),
							'',
							'sorting');
						while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
							$valuesArray[] = $row['uid_foreign'];
						}
						$parsedArr[$colName] = implode(',', $valuesArray);
					}
				}
			}
			}
			
			return $parsedArr;
		}
		
		/**
		 * Transforms outgoing dates into timestamps
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

								$parsedArr[$theField] = $this->adodbTime->adodb_mktime(0,0,0,$month,$day,$year);
							}
							break;
						}
					}
				}
			}
			
				// update the MM relation count field
			$fieldsList = array_keys($parsedArr);
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
				if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
					$parsedArr[$colName] = count(explode(',', $parsedArr[$colName]));
				}
			}
			
			return $parsedArr;
		}
		
		/**
		 * Update MM relations
		 *
		 * @return void
		 */
		function updateMMRelations($origArr = array()) {
			if ($this->typoVersion >= 3006000) {
				global $TYPO3_DB;
							// update the MM relation
			$fieldsList = array_keys($origArr);
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
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
			}
		}
		
		/**
		 * Delete MM relations
		 *
		 * @return void
		 */
		function deleteMMRelations($table,$uid) {
			if ($this->typoVersion >= 3006000) {
				global $TYPO3_DB;
				// update the MM relation
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
				if (in_array($colName, $fieldsList) && $colSettings['config']['type'] == 'select' && $colSettings['config']['MM']) {
					$res = $TYPO3_DB->exec_DELETEquery($colSettings['config']['MM'], 'uid_local='.intval($uid));
				}
			}
			}
		}

		function getLLFromString($string) {
			global $LOCAL_LANG, $TSFE;
			$arr = explode(':',$string);
			if($arr[0] == 'LLL' && $arr[1] == 'EXT') {
				$temp = $this->pi_getLL($arr[3]);
				if ($temp) {
					return $temp;
				} else {
					if ($this->typoVersion >= 3006000 ) {
						return $TSFE->sL($string);
					} else {
						$filename = t3lib_div::getFileAbsFileName($arr[1].':'.$arr[2]);
						include_once($filename);
						$this->LOCAL_LANG = t3lib_div::array_merge_recursive_overrule($this->LOCAL_LANG,$LOCAL_LANG);
						return $this->pi_getLL($arr[3]);
					}
				}
			}
		}

		/**
		 * Returns the relevant usergroup overlay record fields
		 * Adapted from t3lib_page.php
		 *
		 * @param	mixed		If $usergroup is an integer, it's the uid of the usergroup overlay record and thus the usergroup overlay record is returned. If $usergroup is an array, it's a usergroup record and based on this usergroup record the language overlay record is found and OVERLAYED before the usergroup record is returned.
		 * @param	integer		Language UID if you want to set an alternative value to $this->pidRecord->sys_language_uid which is default. Should be >=0
		 * @return	array		usergroup row which is overlayed with language_overlay record (or the overlay record alone)
		 */
		function getUsergroupOverlay($usergroup, $languageUid = -1) {
			global $TYPO3_DB;
			// Initialize:
			if ($languageUid < 0) {
				$languageUid = $this->pidRecord->sys_language_uid;
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
		}

		/**
		* Function imported from class.tslib_content.php
		*  unescape replaced by decodeURIComponent
		*
		* Returns a JavaScript <script> section with some function calls to JavaScript functions from "t3lib/jsfunc.updateform.js" (which is also included by setting a reference in $GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'])
		* The JavaScript codes simply transfers content into form fields of a form which is probably used for editing information by frontend users. Used by fe_adminLib.inc.
		*
		* @param       array           Data array which values to load into the form fields from $formName (only field names found in $fieldList)
		* @param       string          The form name
		* @param       string          A prefix for the data array
		* @param       string          The list of fields which are loaded
		* @return      string
		*/
		function getUpdateJS($dataArray, $formName, $arrPrefix, $fieldList) {
			$JSPart = '';
			$updateValues = t3lib_div::trimExplode(',', $fieldList);
			if ($this->conf['templateStyle'] == 'css-styled') {
				$form = $this->pi_getClassName($formName);
			} else {
				$form = $formName;
			}
			while (list(, $fKey) = each($updateValues)) {
				$value = $dataArray[$fKey];
				if (is_array($value)) {
					reset($value);
					while (list(, $Nvalue) = each($value)) {
						$JSPart .= "
		updateForm('".$form."','".$arrPrefix."[".$fKey."][]',".$this->quoteJSvalue($Nvalue, true).")";
					}
				} else {
					$JSPart .= "
		updateForm('".$form."','".$arrPrefix."[".$fKey."]',".$this->quoteJSvalue($value, true).")";
				}
			}
			$JSPart = '<script type="text/javascript">
	/*<![CDATA[*/ 
<!--'.$JSPart.'
// -->
	/*]]>*/
</script>';
			if ($this->conf['templateStyle'] == 'css-styled') {
				$GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . t3lib_extMgm::siteRelPath('sr_feuser_register') .'scripts/jsfunc.updateform.js"></script>';
			} else {
				$GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . 't3lib/jsfunc.updateform.js"></script>';
			}
			return $JSPart;
		}
		
		/**
		 * Function imported from class.t3lib_div.php
		 * See http://bugs.typo3.org/view.php?id=277
		 * NOTE: chr(10) and chr(13) also need to be escaped for textarea elements
		 */
		function quoteJSvalue($value, $inScriptTags = false)	{
			$value = addcslashes($value, '\''.chr(10).chr(13));
			if (!$inScriptTags)	{
				$value = htmlspecialchars($value);
			}
			return '\''.$value.'\'';
		}
		
		/**
		 * From the 'KB MD5 FE Password (kb_md5fepw)' extension.
		 *
		 * @author	Kraft Bernhard <kraftb@gmx.net>
		 */
		function getMD5Submit($cmd) {
			$JSPart = '
				';
			if ($cmd == 'edit') {
				$JSPart .= "var pw_change = 0;
				";
			}
			$JSPart .= "function enc_form(form) {
					var pass = form['FE[" . $this->theTable . "][password]'].value;
					var pass_again = form['FE[" . $this->theTable . "][password_again]'].value;
					";
			if ($cmd != 'edit') {
				$JSPart .= "if (pass == '') {
						alert('" . $this->pi_getLL('missing_password') . "');
						form['FE[" . $this->theTable . "][password]'].select();
						form['FE[" . $this->theTable . "][password]'].focus();
						return false;
					}
					";
			}
			$JSPart .= "if (pass != pass_again) {
						alert('" . $this->pi_getLL('evalErrors_twice_password') . "');
						form['FE[" . $this->theTable . "][password]'].select();
						form['FE[" . $this->theTable . "][password]'].focus();
						return false;
					}
					";
			if ($cmd == 'edit') {
				$JSPart .= "if (pw_change) {
						";
			}
			$JSPart .= "var enc_pass = MD5(pass);
						form['FE[" . $this->theTable . "][password]'].value = enc_pass;
						form['FE[" . $this->theTable . "][password_again]'].value = enc_pass;
					";
			if ($cmd == 'edit') {
				$JSPart .= "}
					";
			}
			$JSPart .= "return true;
				}";
			return $JSPart;
		}
		
		/**
		 * From the 'salutationswitcher' extension.
		 *
		 * @author	Oliver Klee <typo-coding@oliverklee.de>
		 */
		    // list of allowed suffixes
		var $allowedSuffixes = array('formal', 'informal');
		
		/**
		 * Returns the localized label of the LOCAL_LANG key, $key
		 * In $this->conf['salutation'], a suffix to the key may be set (which may be either 'formal' or 'informal').
		 * If a corresponding key exists, the formal/informal localized string is used instead.
		 * If the key doesn't exist, we just use the normal string.
		 *
		 * Example: key = 'greeting', suffix = 'informal'. If the key 'greeting_informal' exists, that string is used.
		 * If it doesn't exist, we'll try to use the string with the key 'greeting'.
		 *
		 * Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
		 *
		 * @param    string        The key from the LOCAL_LANG array for which to return the value.
		 * @param    string        Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
		 * @param    boolean        If true, the output label is passed through htmlspecialchars()
		 * @return    string        The value from LOCAL_LANG.
		 */
		function pi_getLL($key, $alt = '', $hsc = FALSE) {
				// If the suffix is allowed and we have a localized string for the desired salutation, we'll take that.
			if (isset($this->conf['salutation']) && in_array($this->conf['salutation'], $this->allowedSuffixes, 1)) {
				$expandedKey = $key.'_'.$this->conf['salutation'];
				if (isset($this->LOCAL_LANG[$this->LLkey][$expandedKey])) {
					$key = $expandedKey;
				}
			}
			return parent::pi_getLL($key, $alt, $hsc);
		}
		
		function pi_loadLL() {
				// flatten the structure of labels overrides
			if (is_array($this->conf['_LOCAL_LANG.'])) {
				$done = false;
				$i = 0;
				while(!$done && $i < 10) {
					$done = true;
					reset($this->conf['_LOCAL_LANG.']);
					while(list($k,$lA)=each($this->conf['_LOCAL_LANG.'])) {
						if (is_array($lA)) {
							foreach($lA as $llK => $llV)    {
								if (is_array($llV))    {
									foreach ($llV as $llK2 => $llV2) {
										$this->conf['_LOCAL_LANG.'][$k][$llK . $llK2] = $llV2;
									}
									unset($this->conf['_LOCAL_LANG.'][$k][$llK]);
									$done = false;
									++$i;
								}
							}
						}
					}
				}
			}
			parent::pi_loadLL();
		}
		
	}
	if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php"]) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php"]);
	}
?>
