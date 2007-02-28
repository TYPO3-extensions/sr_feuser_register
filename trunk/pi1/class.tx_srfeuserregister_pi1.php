<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skaarhoj (kasper2007@typo3.com)
*  (c) 2004-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
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
 * $Id$
 * 
 * @author Kasper Skaarhoj <kasper2007@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 *
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
	// To get the pid language overlay:
require_once(PATH_t3lib.'class.t3lib_page.php');
	// For use with images:
require_once (PATH_t3lib.'class.t3lib_basicfilefunc.php');
	// For translating items from other extensions
// require_once (t3lib_extMgm::extPath('lang').'lang.php');

require_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_urlvalidator.php');

require_once(PATH_BE_srfeuserregister.'control/class.tx_srfeuserregister_control.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_auth.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_email.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_lang.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_tca.php');
require_once(PATH_BE_srfeuserregister.'marker/class.tx_srfeuserregister_marker.php');
require_once(PATH_BE_srfeuserregister.'model/class.tx_srfeuserregister_data.php');
require_once(PATH_BE_srfeuserregister.'view/class.tx_srfeuserregister_display.php');


define('SAVED_SUFFIX', '_SAVED');
define('SETFIXED_PREFIX', 'SETFIXED_');


class tx_srfeuserregister_pi1 extends tslib_pibase {
	var $cObj;
	var $conf = array();
	var $config = array();
	var $site_url = '';
	
		// Plugin initialization variables
	var $prefixId = 'tx_srfeuserregister_pi1';  // Same as class name
	var $scriptRelPath = 'pi1/class.tx_srfeuserregister_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'sr_feuser_register';  // The extension key.
	var $thePid = 0;
	var $templateCode = '';

	var $loginPID;
		
	var $cmd;
	var $setfixedEnabled = 1;
	var $incomingData = FALSE;
	var $previewLabel = '';
	var $backURL;
	var $inError = array(); // array of fields with eval errors other than absence
	var $error = '';
	var $nc = ''; // "&no_cache=1" if you want that parameter sent.
	var $additionalUpdateFields = '';
	var $emailMarkPrefix = 'EMAIL_TEMPLATE_';
	var $emailMarkAdminSuffix = '_ADMIN';
	var $emailMarkHTMLSuffix = '_HTML';
	var $sys_language_content;
	var $charset = 'iso-8859-1'; // charset to be used in emails and form conversions
	var $cmdKey;
	var $fileFunc = ''; // Set to a basic_filefunc object for file uploads
	var $freeCap; // object of type tx_srfreecap_pi2
	var $auth; // object of type tx_srfeuserregister_auth
	var $control; // object of type tx_srfeuserregister_control
	var $data; // object of type tx_srfeuserregister_data
	var $display; // object of type tx_srfeuserregister_display
	var $email; // object of type tx_srfeuserregister_email
	var $lang; // object of type tx_srfeuserregister_lang
	var $tca;  // object of type tx_srfeuserregister_tca
	var $marker; // object of type tx_srfeuserregister_marker


	function main($content, &$conf) {
		global $TSFE;
		$failure = false; // is set if data did not have the required fields set.

		$this->init($conf);		

			// Evaluate incoming data
		if (count($this->data->dataArr)) {
			$this->data->setName();
			$this->data->parseValues();
			$this->data->overrideValues();
			if ($this->data->feUserData['submit'] || $this->data->feUserData['doNotSave'] || $this->data->feUserData['linkToPID']) {
				// a button was clicked on
				$this->data->evalValues($this->marker->getArray());
				if ($this->conf['evalFunc'] ) {
					$this->data->dataArr = $this->userProcess('evalFunc', $this->data->dataArr);
				}
			} else {
				//this is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// we are going to redisplay
				$this->data->evalValues($this->marker->getArray());
				$failure = true;
			}
			$this->data->setUsername();
			if (!$failure && !$this->data->feUserData['preview'] && !$this->data->feUserData['doNotSave'] ) {
				$this->data->setPassword();
				$this->data->save();
			}
		} else {
			$this->data->defaultValues($this->marker->getArray()); // If no incoming data, this will set the default values.
			$this->data->feUserData['preview'] = 0; // No preview if data is not received
		}
		if ($failure ) {
			$this->data->feUserData['preview'] = 0;
		} // No preview flag if a evaluation failure has occured
		$this->previewLabel = ($this->data->feUserData['preview']) ? '_PREVIEW' : ''; // Setting preview template label suffix.


			// Display forms
		if ($this->data->saved) {
				// Displaying the page here that says, the record has been saved. You're able to include the saved values by markers.
			switch($this->cmd) {
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
						$this->marker->setArray( $this->marker->addMd5LoginMarkers($this->marker->getArray()));
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
			$templateCode = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_'.$key.'###');
			$markerArray = $this->cObj->fillInMarkerArray($this->marker->getArray(), $this->data->currentArr, '',TRUE, 'FIELD_', TRUE);
			$markerArray = $this->marker->addStaticInfoMarkers($markerArray, $this->data->currentArr);
			$markerArray = $this->tca->addTcaMarkers($markerArray, $this->data->currentArr, true);
			$markerArray = $this->marker->addLabelMarkers($markerArray, $this->data->currentArr);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);

				// Send email message(s)
			$this->email->compile($key, array($this->data->currentArr), $this->data->currentArr[$this->conf['email.']['field']], $this->marker->getArray(), $this->conf['setfixed.']);

				// Link to on edit save
				// backURL may link back to referring process
			if ($this->data->theTable == 'fe_users' && 
				$this->cmd == 'edit' && 
				($this->backURL || ($this->conf['linkToPID'] && ($this->data->feUserData['linkToPID'] || !$this->conf['linkToPIDAddButton']))) ) {
				$destUrl = ($this->backURL ? $this->backURL : ($TSFE->absRefPrefix ? '' : $this->site_url).$this->cObj->getTypoLink_URL($this->conf['linkToPID'].','.$TSFE->type));
				header('Location: '.t3lib_div::locationHeaderUrl($destUrl));
				exit;
			}
				// Auto-login on create
			if ($this->data->theTable == 'fe_users' && $this->cmd == 'create' && !$this->setfixedEnabled && $this->conf['enableAutoLoginOnCreate']) {
				$loginVars = array();
				$loginVars['user'] = $this->data->currentArr['username'];
				$loginVars['pass'] = $this->data->currentArr['password'];
				$loginVars['pid'] = $this->thePid;
				$loginVars['logintype'] = 'login';
				$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
				header('Location: '.t3lib_div::locationHeaderUrl($this->cObj->getTypoLink_URL($this->loginPID.','.$GLOBALS['TSFE']->type, $loginVars)));
				exit;
			}
		} else if($this->error) {
				// If there was an error, we return the template-subpart with the error message
			$templateCode = $this->cObj->getSubpart($this->templateCode, $this->error);
			$this->marker->setArray($this->marker->addLabelMarkers($this->marker->getArray(), $this->data->dataArr));
			$content = $this->cObj->substituteMarkerArray($templateCode, $this->marker->getArray());
		} else {
				// Finally, if there has been no attempt to save. That is either preview or just displaying and empty or not correctly filled form:
			switch($this->cmd) {
				case 'setfixed':
					if ($this->conf['infomail']) {
						$this->setfixedEnabled = 1;
					}
					$content = $this->control->processSetFixed($this->marker->getArray());
					break;
				case 'infomail':
					if ($this->conf['infomail']) {
						$this->setfixedEnabled = 1;
					}
					$content = $this->email->sendInfo($this->marker->getArray());
					break;
				case 'delete':
					$content = $this->display->deleteScreen();
					break;
				case 'edit':
					$content = $this->display->editScreen();
					break;
				case 'invite':
				case 'create':
					$content = $this->display->createScreen($this->cmd);
					break;
				default:
					if ($this->data->theTable == 'fe_users' && $TSFE->loginUser) {
						$content = $this->display->createScreen($this->cmd);
					} else {
						$content = $this->display->editScreen();
					}
					break;
			}
		}
		$rc = $this->pi_wrapInBaseClass($content);
		return $rc; 
	}


	/**
	* Initialization
	*
	* @return void
	*/
	function init(&$conf) {
		global $TSFE, $TCA, $TYPO3_CONF_VARS;

			// plugin initialization
		$this->conf = $conf;

		if (t3lib_extMgm::isLoaded('sr_freecap') ) {
			require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		}

		$this->lang = t3lib_div::makeInstance('tx_srfeuserregister_lang');
		$this->data = t3lib_div::makeInstance('tx_srfeuserregister_data');
		$this->auth = t3lib_div::makeInstance('tx_srfeuserregister_auth');
		$this->marker = t3lib_div::makeInstance('tx_srfeuserregister_marker');
		$this->tca = t3lib_div::makeInstance('tx_srfeuserregister_tca');
		$this->display = t3lib_div::makeInstance('tx_srfeuserregister_display');
		$this->email = t3lib_div::makeInstance('tx_srfeuserregister_email');
		$this->control = t3lib_div::makeInstance('tx_srfeuserregister_control');

		$this->lang->init($this, $this->conf, $this->config);
		$this->lang->pi_loadLL();
		$this->pi_USER_INT_obj = 1;
		$this->pi_setPiVarDefaults();
		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$this->sys_language_content = t3lib_div::testInt($TSFE->config['config']['sys_language_uid']) ? intval($TSFE->config['config']['sys_language_uid']) : 0;


		$this->loginPID = intval($this->conf['loginPID']) ? strval(intval($this->conf['loginPID'])) : $TSFE->id;

			// prepare for character set settings
		if ($TSFE->metaCharset) {
			$this->charset = $TSFE->csConvObj->parse_charset($TSFE->metaCharset);
		}

			// Initialise fileFunc object
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

			// Get parameters
		$this->data->feUserData = t3lib_div::_GP($this->prefixId);
		$fe = t3lib_div::_GP('FE');

		// <Steve Webster added short url feature>
			// Get hash variable if provided and if short url feature is enabled
		if ($this->conf['useShortUrls']) {
			$this->cleanShortUrlCache();
				// Check and process for short URL if the regHash GET parameter exists
			if (isset($this->data->feUserData['regHash'])) {
				$getVars = $this->getStoredURL($this->data->feUserData['regHash']);
				foreach ($getVars as $k => $v ) {
					t3lib_div::_GETset($v,$k);
				}
				$this->data->feUserData = t3lib_div::_GP($this->prefixId);
			}
		}
		// </Steve Webster added short url feature>

			// Establishing compatibility with Direct Mail extension
		$this->data->feUserData['rU'] = t3lib_div::_GP('rU') ? t3lib_div::_GP('rU') : $this->data->feUserData['rU'];
		$this->data->feUserData['aC'] = t3lib_div::_GP('aC') ? t3lib_div::_GP('aC') : $this->data->feUserData['aC'];
		$this->data->feUserData['cmd'] = t3lib_div::_GP('cmd') ? t3lib_div::_GP('cmd') : $this->data->feUserData['cmd'];
		$this->data->feUserData['sFK'] = t3lib_div::_GP('sFK') ? t3lib_div::_GP('sFK') : $this->data->feUserData['sFK'];

		$this->data->dataArr = $fe[$this->data->theTable];
		if (is_array($this->data->dataArr['module_sys_dmail_category']))	{	// no array elements are allowed for $this->cObj->fillInMarkerArray
			$this->data->dataArr['module_sys_dmail_category'] = implode(',',$this->data->dataArr['module_sys_dmail_category']);
		}

		$this->backURL = rawurldecode($this->data->feUserData['backURL']);

			// Setting cmd and various switches
		if ($this->data->theTable == 'fe_users' && $this->data->feUserData['cmd'] == 'login' ) {
			unset($this->data->feUserData['cmd']);
		}
		$this->cmd = $this->data->feUserData['cmd'] ? $this->data->feUserData['cmd'] : $this->cObj->caseshift($this->cObj->data['select_key'],'lower');

		if ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['useFlexforms'] && t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
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
				$TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['useFlexforms']
			);
		} else {
			$this->cmd = $this->cmd ? $this->cmd : $this->cObj->caseshift($this->conf['defaultCODE'],'lower');
		}

		// Ralf Hettinger: avoid data from edit forms being visible by back buttoning to client side cached pages
		// This only solves data being visible by back buttoning for edit forms.
		// It won't help against data being visible by back buttoning in create forms.
		$noLoginCommands = array('','create','invite','setfixed','infomail');
		if (!$GLOBALS['TSFE']->loginUser && !(in_array($this->cmd,$noLoginCommands))) {
			$this->cmd='';
			$this->data->dataArr = array();
		}
		
		if ($this->cmd == 'edit' || $this->cmd == 'invite') {
			$this->cmdKey = $this->cmd;
		} else {
			$this->cmdKey = 'create';
		}
		if (isset($this->conf['setfixed'])) {
			$this->setfixedEnabled = $this->conf['setfixed'];
		}
	
			// Initialise password encryption
		if ($this->data->theTable == 'fe_users' && t3lib_extMgm::isLoaded('kb_md5fepw')) {
			require_once(t3lib_extMgm::extPath('kb_md5fepw').'class.tx_kbmd5fepw_funcs.php');
			$this->useMd5Password = TRUE;
			$this->conf['enableAutoLoginOnConfirmation'] = FALSE;
			$this->conf['enableAutoLoginOnCreate'] = FALSE;
		}

		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('module_sys_dmail_category')));
			$this->conf[$this->cmdKey.'.']['required'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'], 1), array('module_sys_dmail_category')));
		}
		
		if ($this->data->theTable == 'fe_users') {
			$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'] . ',username', 1)));
			$this->conf[$this->cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['required'] . ',username', 1)));
			if ($this->conf[$this->cmdKey.'.']['generateUsername']) {
				$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('username')));
			}

			if ($this->conf[$this->cmdKey.'.']['generatePassword'] && $this->cmdKey != 'edit') {
				$this->conf[$this->cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $this->conf[$this->cmdKey.'.']['fields'], 1), array('password')));
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
				if ($this->cmdKey == 'edit' && is_array($this->conf['setfixed.'])) {
					if ($this->conf['enableAdminReview'] && is_array($this->conf['setfixed.']['ACCEPT.'])) {
						$this->conf[$this->cmdKey.'.']['overrideValues.']['usergroup'] = $this->conf['setfixed.']['ACCEPT.']['usergroup'];
					} elseif ($this->conf['setfixed'] && is_array($this->conf['setfixed.']['APPROVE.'])) {
						$this->conf[$this->cmdKey.'.']['overrideValues.']['usergroup'] = $this->conf['setfixed.']['APPROVE.']['usergroup'];
					}
				}
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

			// Fetching the template file
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);
		$markerArray = array();

			// Set globally substituted markers, fonts and colors.
		if ($this->conf['templateStyle'] != 'css-styled') {
			$splitMark = md5(microtime());
			list($markerArray['###GW1B###'], $markerArray['###GW1E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap1.']));
			list($markerArray['###GW2B###'], $markerArray['###GW2E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap2.']));
			list($markerArray['###GW3B###'], $markerArray['###GW3E###']) = explode($splitMark, $this->cObj->stdWrap($splitMark, $this->conf['wrap3.']));
			$markerArray['###GC1###'] = $this->cObj->stdWrap($this->conf['color1'], $this->conf['color1.']);
			$markerArray['###GC2###'] = $this->cObj->stdWrap($this->conf['color2'], $this->conf['color2.']);
			$markerArray['###GC3###'] = $this->cObj->stdWrap($this->conf['color3'], $this->conf['color3.']);
		}
		$markerArray['###CHARSET###'] = $this->charset;
		$markerArray['###PREFIXID###'] = $this->prefixId;
		$this->thePid = intval($this->conf['pid']) ? strval(intval($this->conf['pid'])) : $TSFE->id;

		$this->data->init($this, $this->conf, $this->config,$this->lang, $this->tca, $this->auth, $this->freeCap);
		$this->auth->init($this, $this->conf, $this->config, $this->data->feUserData['aC']);
		$this->marker->init($this, $this->conf, $this->config, $this->data, $this->tca, $this->lang, $this->auth);
		$this->tca->init($this, $this->conf, $this->config, $this->data, $this->lang);
		$this->display->init($this, $this->conf, $this->config, $this->data, $this->marker, $this->tca, $this->auth);
		$this->email->init($this, $this->conf, $this->config, $this->display, $this->data, $this->marker, $this->tca, $this->auth);
		$this->control->init($this, $this->conf, $this->config, $this->display, $this->data, $this->marker, $this->auth, $this->email, $this->tca);

			// Setting URL, HIDDENFIELDS and signature markers
		$markerArray = $this->marker->addURLMarkers($this->marker->markerArray);

		if (is_object($this->freeCap)) {
			$markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
		}
		$markerArray = array_merge($markerArray, $this->marker->getArray());
		$this->marker->setArray($markerArray);

			// If data is submitted, we take care of it here.
		if ($this->cmd == 'delete' && !$this->data->feUserData['preview'] && !$this->data->feUserData['doNotSave'] ) {
			// Delete record if delete command is sent + the preview flag is NOT set.
			$this->data->deleteRecord();
		}

	}	// init



	/**
		*  Get the stored variables using the hash value to access the database
		*/
	function getStoredURL($regHash) {
		global $TYPO3_DB;
		
			// get the serialised array from the DB based on the passed hash value
		$varArray = array();
		$res = $TYPO3_DB->exec_SELECTquery('params','cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash,'cache_md5params'));
		while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$varArray = unserialize($row['params']);
		}
			// convert the array to one that will be properly incorporated into the GET global array.
		$retArray = array();
		reset($varArray);
		while (list($key, $val) = each($varArray)){
			$search = array('[\]]', '[\[]');
			$replace = array ( '\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			eval("\$retArr".$newkey."='$val';");
		}
		return $retArr;
	}	// getStoredURL


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
	}	// userProcess


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
	}	// userProcess_alt


	/**
	* Instantiate the file creation function
	*
	* @return void
	*/
/*	function createFileFuncObj() {
		if (!$this->fileFunc) {
			$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
		}
	}
*/

	/**
	* Check what bit is set and returns the bitnumber
	* @param	int	Number to check, ex: 16 returns 4, 32 returns 5, 0 returns -1, 1 returns 0
	* @ return	bool	Bitnumber, -1 for not found
	*/
/*	function _whatBit($num) {
		$num = intval($num);
		if ($num == 0) return -1;
		for ($i=0; $i<32; $i++) {
			if ($num & (1 << $i)) return $i;
		}
		return -1;
	}
*/

	/**
		*  Clears obsolete hashes used for short url's
		*/
	function cleanShortUrlCache() {
		global $TYPO3_DB;

		$shortUrlLife = intval($this->conf['shortUrlLife']) ? strval(intval($this->conf['shortUrlLife'])) : '30';
		
		$max_life = time() - (86400 * intval($shortUrlLife));
		$res = $TYPO3_DB->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');	
	}	// cleanShortUrlCache
	// </Steve Webster added short url feature>


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']);
}
?>
