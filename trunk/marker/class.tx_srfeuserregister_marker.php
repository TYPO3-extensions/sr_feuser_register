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
 * marker functions
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


require_once(PATH_BE_static_info_tables.'pi1/class.tx_staticinfotables_pi1.php');


class tx_srfeuserregister_marker {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $data;
	var $control;
	var $tca;
	var $auth;

	var $thePidTitle;
	var $site_url;
	var $prefixId;
	var $previewLabel;
	var $staticInfo;
	var $setfixedEnabled;

	var $markerArray = array();
	var $sys_language_content;
	var $confirmType;
	var $cObj;
	var $buttonLabelsList;
	var $otherLabelsList;


	function init(&$pibase, &$conf, &$config, &$data, &$tca, &$lang, &$control, &$auth, &$freeCap)	{
		global $TSFE;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->data = &$data;
		$this->tca = &$tca;
		$this->lang = &$lang;
		$this->control = &$control;
		$this->auth = &$auth;

		$this->cObj = &$pibase->cObj;


			// set the title language overlay
		$pidRecord = t3lib_div::makeInstance('t3lib_pageSelect');
		$pidRecord->init(0);
		$pidRecord->sys_language_uid = $this->sys_language_content;
		$row = $pidRecord->getPage($this->control->thePid);

		$this->thePidTitle = trim($this->conf['pidTitleOverride']) ? trim($this->conf['pidTitleOverride']) : $row['title'];

		$this->site_url = $this->control->getSiteUrl();
		$this->prefixId = $this->control->prefixId;

		$this->staticInfo = $pibase->staticInfo;
		$this->setfixedEnabled = $pibase->setfixedEnabled;
		$this->sys_language_content = $pibase->sys_language_content;

		$this->confirmType = intval($this->conf['confirmType']) ? strval(intval($this->conf['confirmType'])) : $TSFE->type;
		if ($this->conf['confirmType'] == '0' ) {
			$this->confirmType = '0';
		};

			// Initialise static info library
		$this->staticInfo = t3lib_div::makeInstance('tx_staticinfotables_pi1');
		$this->staticInfo->init();

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

			// Setting URL, HIDDENFIELDS and signature markers
		$this->addURLMarkers($markerArray);

		if (is_object($freeCap)) {
			$markerArray = array_merge($markerArray, $freeCap->makeCaptcha());
		}
		$this->setArray($markerArray);

				// Button labels
		$buttonLabelsList = 'register,confirm_register,back_to_form,update,confirm_update,enter,confirm_delete,cancel_delete,update_and_more';

		$this->setButtonLabelsList ($buttonLabelsList);

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
			',v_registration_infomail_message1'.
			',v_registration_confirmed,v_registration_confirmed_subject,v_registration_confirmed_message1,v_registration_confirmed_message2,v_registration_confirmed_review1,v_registration_confirmed_review2'.
			',v_registration_cancelled,v_registration_cancelled_subject,v_registration_cancelled_message1,v_registration_cancelled_message2'.
			',v_registration_accepted,v_registration_accepted_subject,v_registration_accepted_message1,v_registration_accepted_message2'.
			',v_registration_refused,v_registration_refused_subject,v_registration_refused_message1,v_registration_refused_message2'.
			',v_registration_accepted_subject2,v_registration_accepted_message3,v_registration_accepted_message4'.
			',v_registration_refused_subject2,v_registration_refused_message3,v_registration_refused_message4'.
			',v_registration_entered_subject,v_registration_entered_message1,v_registration_entered_message2'.
			',v_registration_updated,v_registration_updated_subject,v_registration_updated_message1'.
			',v_registration_deleted,v_registration_deleted_subject,v_registration_deleted_message1,v_registration_deleted_message2';
		$this->setOtherLabelsList($otherLabelsList);
	}

	function getButtonLabelsList()	{
		return $this->buttonLabelsList;
	}

	function setButtonLabelsList(&$buttonLabelsList)	{
		$this->buttonLabelsList = $buttonLabelsList;
	}

	function getOtherLabelsList()	{
		return $this->otherLabelsList;
	}

	function setOtherLabelsList(&$otherLabelsList)	{
		$this->otherLabelsList = $otherLabelsList;
	}


	function getArray()	{
		return $this->markerArray;
	}

	function setArray($param, $value = '')	{
		if (is_array($param))	{
			$this->markerArray = $param;
		} else {
			$this->markerArray[$param] = $value;
		}
	}

	function getPreviewLabel()	{
		return $this->previewLabel;
	}

	function setPreviewLabel($label)	{
		$this->previewLabel = $label;
	}

	/**
	* Computes the setfixed url's
	*
	* @param array  $markerArray: the input marker array
	* @param array  $setfixed: the TS setup setfixed configuration
	* @param array  $r: the record
	* @return void
	*/
	function setfixed(&$markerArray, $setfixed, $r) {
		global $TSFE;
		
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}

		if ($this->setfixedEnabled && is_array($setfixed) ) {
			$setfixedpiVars = array();
			$theTable = $this->data->getTable();
			reset($setfixed);
			while (list($theKey, $data) = each($setfixed)) {
				if (strstr($theKey, '.') ) {
					$theKey = substr($theKey, 0, -1);
				}
				unset($setfixedpiVars);
				$recCopy = $r;
				$setfixedpiVars[$this->prefixId.'[rU]'] = $r['uid'];

				if ( $theTable != 'fe_users' && $theKey == 'EDIT' ) {
// 					$setfixedpiVars[$this->prefixId.'[cmd]'] = 'edit';
					if (is_array($data) ) {
						reset($data);
						while (list($fieldName, $fieldValue) = each($data)) {
							$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
							$recCopy[$fieldName] = $fieldValue;
						}
					}
					if( $this->conf['edit.']['setfixed'] ) {
						$setfixedpiVars[$this->prefixId.'[aC]'] = $this->auth->setfixedHash($recCopy, $data['_FIELDLIST']);
					} else {
						$setfixedpiVars[$this->prefixId.'[aC]'] = $this->auth->authCode($r);
					}
					$linkPID = $this->control->getPID('edit');
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
					$setfixedpiVars[$this->prefixId.'[aC]'] = $this->auth->setfixedHash($recCopy, $data['_FIELDLIST']);
					$linkPID = $this->control->getPID('confirm');
					if ($this->control->getCmd() == 'invite') {
						$linkPID = $this->control->getPID('confirmInvitation');

					}
				}
				if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = t3lib_div::_GP('L');
				}

				if ($this->conf['useShortUrls']) {
					$thisHash = $this->storeFixedPiVars($setfixedpiVars);
					$setfixedpiVars = array($this->prefixId.'[regHash]' => $thisHash);
				}
				$conf = array();
				$conf['disableGroupAccessCheck'] = TRUE;
				$url = $this->cObj->getTypoLink_URL($linkPID.','.$this->confirmType, $setfixedpiVars, '', $conf);
				$markerArray['###SETFIXED_'.$this->cObj->caseshift($theKey,'upper').'_URL###'] = ($TSFE->absRefPrefix ? '' : $this->site_url) . $url;
			}
		}
	}	// setfixed


	/**
	* Adds language-dependent label markers
	*
	* @param array  $markerArray: the input marker array
	* @param array  $dataArray: the record array
	* @param array  $requiredArray: the required fields array
	* @return void
	*/
	function addLabelMarkers(&$markerArray, &$dataArray, &$requiredArray) {
		global $TYPO3_CONF_VARS;

		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}

		// Data field labels
		$infoFields = t3lib_div::trimExplode(',', $this->data->getFieldList(), 1);
		while (list(, $fName) = each($infoFields)) {
			$markerkey = $this->cObj->caseshift($fName,'upper');
			$markerArray['###LABEL_'.$markerkey.'###'] = $this->lang->pi_getLL($fName) ? $this->lang->pi_getLL($fName) : $this->lang->getLLFromString($this->tca->TCA['columns'][$fName]['label']);
			$markerArray['###TOOLTIP_'.$markerkey.'###'] = $this->lang->pi_getLL('tooltip_' . $fName);
			$markerArray['###TOOLTIP_INVITATION_'.$markerkey.'###'] = $this->lang->pi_getLL('tooltip_invitation_' . $fName);
			// <Ries van Twisk added support for multiple checkboxes>
			$colConfig = $this->tca->TCA['columns'][$fName]['config'];

			if ($colConfig['type'] == 'select' && $colConfig['items'])	{ // (is_array($dataArray[$fName])) {
				$colContent = '';
				$markerArray['###FIELD_'.$fName.'_CHECKED###'] = '';
				$markerArray['###LABEL_'.$fName.'_CHECKED###'] = '';
				$this->data->dataArray['###POSTVARS_'.$fName.'###'] = '';
				$fieldArray = t3lib_div::trimExplode(',', $dataArray[$fName]);
				foreach ($fieldArray AS $key => $value) {
					$markerArray['###FIELD_'.$fName.'_CHECKED###'] .= '- '.$this->lang->getLLFromString($colConfig['items'][$value][0]).'<br />';
					$markerArray['###LABEL_'.$fName.'_CHECKED###'] .= '- '.$this->lang->getLLFromString($colConfig['items'][$value][0]).'<br />';
					$markerArray['###POSTVARS_'.$fName.'###'] .= chr(10).'	<input type="hidden" name="FE[fe_users]['.$fName.']['.$key.']" value ="'.$value.'" />';
				}
			// </Ries van Twisk added support for multiple checkboxes>
			} else {
				$markerArray['###FIELD_'.$fName.'_CHECKED###'] = ($dataArray[$fName])?'checked':'';
				$markerArray['###LABEL_'.$fName.'_CHECKED###'] = ($dataArray[$fName])?$this->lang->pi_getLL('yes'):$this->lang->pi_getLL('no');
			}
			if (in_array(trim($fName), $requiredArray) ) {
				$markerArray['###REQUIRED_'.$markerkey.'###'] = '<span>*</span>';
				$markerArray['###MISSING_'.$markerkey.'###'] = $this->lang->pi_getLL('missing_'.$fName);
				$markerArray['###MISSING_INVITATION_'.$markerkey.'###'] = $this->lang->pi_getLL('missing_invitation_'.$fName);
			} else {
				$markerArray['###REQUIRED_'.$markerkey.'###'] = '';
			}
		}

		$buttonLabels = t3lib_div::trimExplode(',', $this->getButtonLabelsList(), 1);
		while (list(, $labelName) = each($buttonLabels) ) {
			if ($labelName)	{
				$markerArray['###LABEL_BUTTON_'.$this->cObj->caseshift($labelName,'upper').'###'] = $this->lang->pi_getLL('button_'.$labelName);
			}
		}
		$otherLabelsList = $this->getOtherLabelsList();

		if (isset($this->conf['extraLabels']) && $this->conf['extraLabels'] != '') {
			$otherLabelsList .= ',' . $this->conf['extraLabels'];
		}
		$otherLabels = t3lib_div::trimExplode(',', $otherLabelsList, 1);
		while (list(, $labelName) = each($otherLabels) ) {
			$markerArray['###LABEL_'.$this->cObj->caseshift($labelName,'upper').'###'] = sprintf($this->lang->pi_getLL($labelName), $this->thePidTitle, $dataArray['username'], $dataArray['name'], $dataArray['email'], $dataArray['password']);
		}

	}	// addLabelMarkers


	/**
	* Adds URL markers to a $markerArray
	*
	* @param array  $markerArray: the input marker array
	* @return void
	*/
	function addURLMarkers(&$markerArray) {
		$backUrl = rawurldecode($this->data->getFeUserData('backURL'));
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$vars = array();
		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview';
		$unsetVars = t3lib_div::trimExplode(',', $unsetVarsList);
		$unsetVars['cmd'] = 'cmd';
		$markerArray['###FORM_URL###'] = $this->control->getUrl('', $GLOBALS['TSFE']->id.','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$theTable = $this->data->getTable();
		
		if ($this->conf['templateStyle'] == 'css-styled') {
			$form = $this->pibase->pi_getClassName($theTable.'_form');
		} else {
			$form = $theTable.'_form';
		}

		$markerArray['###FORM_NAME###'] = $form; // $this->conf['formName'];
		$unsetVars['cmd'] = '';
		$vars['cmd'] = $this->control->getCmd();
		$vars['backURL'] = rawurlencode($this->control->getUrl('', $GLOBALS['TSFE']->id.','.$GLOBALS['TSFE']->type, $vars));
		$vars['cmd'] = 'delete';
		$vars['rU'] = $this->data->getRecUid();
		$vars['preview'] = '1';
		$markerArray['###DELETE_URL###'] = $this->control->getUrl('', $this->control->getPID('edit').','.$GLOBALS['TSFE']->type, $vars);
		$vars['backURL'] = rawurlencode($markerArray['###FORM_URL###']);
		$vars['cmd'] = 'create';
		$markerArray['###REGISTER_URL###'] = $this->control->getUrl('', $this->control->getPID('register').','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'edit';
		$markerArray['###EDIT_URL###'] = $this->control->getUrl('', $this->control->getPID('edit').','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'login';
		$markerArray['###LOGIN_FORM###'] = $this->control->getUrl('', $this->control->getPID('login').','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'infomail';
		$markerArray['###INFOMAIL_URL###'] = $this->control->getUrl('', $this->control->getPID('infomail').','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$markerArray['###THE_PID###'] = $this->control->thePid;
		$markerArray['###THE_PID_TITLE###'] = $this->thePidTitle;
		$markerArray['###BACK_URL###'] = $backUrl;
		$markerArray['###SITE_NAME###'] = $this->conf['email.']['fromName'];
		$markerArray['###SITE_URL###'] = $this->site_url;
		$markerArray['###SITE_WWW###'] = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
		$markerArray['###SITE_EMAIL###'] = $this->conf['email.']['from'];
		$markerArray['###HIDDENFIELDS###'] = '';
		if($theTable == 'fe_users') {
			$cmd = $this->control->getCmd();
			$markerArray['###HIDDENFIELDS###'] = ($cmd ? '<input type="hidden" name="'.$this->prefixId.'[cmd]" value="'.$cmd.'" />':'');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($this->auth->authCode?'<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->auth->authCode.'" />':'');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($backUrl?'<input type="hidden" name="'.$this->prefixId.'[backURL]" value="'.htmlspecialchars($backUrl).'" />':'');
		}

	}	// addURLMarkers

	
	/**
		* Adds Static Info markers to a marker array
		*
		* @param array  $markerArray: the input marker array
		* @param array  $dataArray: the record array
		* @return void
		*/
	function addStaticInfoMarkers(&$markerArray, $dataArray = '', $viewOnly = false) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}

		$cmd = $this->control->getCmd();
		$theTable = $this->data->getTable();
		if ($this->control->getMode() == MODE_PREVIEW || $viewOnly) {
			$markerArray['###FIELD_static_info_country###'] = $this->staticInfo->getStaticInfoName('COUNTRIES', is_array($dataArray)?$dataArray['static_info_country']:'');
			$markerArray['###FIELD_zone###'] = $this->staticInfo->getStaticInfoName('SUBDIVISIONS', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'');
			if (!$markerArray['###FIELD_zone###'] ) {
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$theTable.'][zone]" value="" />';
			}
			$markerArray['###FIELD_language###'] = $this->staticInfo->getStaticInfoName('LANGUAGES', is_array($dataArray)?$dataArray['language']:'');
		} else {
			if ($this->conf['templateStyle'] == 'css-styled') {
				$idCountry = $this->pibase->pi_getClassName('static_info_country');
				$titleCountry = $this->pibase->lang->pi_getLL('tooltip_' . (($cmd == 'invite')?'invitation_':'')  . 'static_info_country');
				$idZone = $this->pibase->pi_getClassName('zone');
				$titleZone = $this->lang->pi_getLL('tooltip_' . (($cmd == 'invite')?'invitation_':'')  . 'zone');
				$idLanguage = $this->pibase->pi_getClassName('language');
				$titleLanguage = $this->lang->pi_getLL('tooltip_' . (($cmd == 'invite')?'invitation_':'')  . 'language');
			}
			$selected = (is_array($dataArray)?$dataArray['static_info_country']:'');
			$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector(
				'COUNTRIES', 
				'FE['.$theTable.']'.'[static_info_country]', 
				'', 
				$selected, 
				'', 
				$this->conf['onChangeCountryAttribute'],
				$idCountry,
				$titleCountry
			);

			$markerArray['###SELECTOR_ZONE###'] = 
				$this->staticInfo->buildStaticInfoSelector(
					'SUBDIVISIONS',
					'FE['.$theTable.']'.'[zone]',
					'', 
					is_array($dataArray)?$dataArray['zone']:'',
					is_array($dataArray)?$dataArray['static_info_country']:'',
					'',
					$idZone,
					$titleZone
				);
			if (!$markerArray['###SELECTOR_ZONE###'] ) {
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$theTable.'][zone]" value="" />';
			}

			$markerArray['###SELECTOR_LANGUAGE###'] = 
				$this->staticInfo->buildStaticInfoSelector(
				'LANGUAGES',
				'FE['.$theTable.']'.'[language]',
				'',
				is_array($dataArray)?$dataArray['language']:'',
				'',
				'',
				$idLanguage,
				$titleLanguage
			);
		}
	}	// addStaticInfoMarkers


	/**
		* Adds md5 password create/edit form markers to a marker array
		*
		* @param array  $markerArray: the input marker array
		* @return void
		*/
	function addMd5EventsMarkers(&$markerArray,$cmd) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$markerArray['###FORM_ONSUBMIT###'] = '';
		$markerArray['###PASSWORD_ONCHANGE###'] = '';
		if ($this->control->useMd5Password) {
			$mode = count($this->data->getDataArray) ? 'edit' : $cmd;
			$GLOBALS['TSFE']->additionalHeaderData['MD5_script'] = '<script type="text/javascript" src="typo3/md5.js"></script>';
			$GLOBALS['TSFE']->JSCode .= $this->getMD5Submit($mode);
			$markerArray['###FORM_ONSUBMIT###'] = 'onsubmit="return enc_form(this);"';
			if ($mode == 'edit') {
				$markerArray['###PASSWORD_ONCHANGE###'] = 'onchange="pw_change=1; return true;"';
			}
		}
	}	// addMd5EventsMarkers


	/**
		* Adds md5 password login form markers to a marker array
		*
		* @param array  $markerArray: the input marker array
		* @return void
		*/
	function addMd5LoginMarkers(&$markerArray) {
		global $TYPO3_CONF_VARS;

		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$onSubmit = '';
		$extraHidden = '';
		if ($this->control->useMd5Password) {
				// Hook used by kb_md5fepw extension by Kraft Bernhard <kraftb@gmx.net>
				// This hook allows to call User JS functions.
				// The methods should also set the required JS functions to get included
			$loginFormOnSubmitFuncs = $TYPO3_CONF_VARS['EXTCONF']['newloginbox']['loginFormOnSubmitFuncs'];
			if (is_array($loginFormOnSubmitFuncs)) {
				$_params = array (); 
				$onSubmitAr = array();
				$extraHiddenAr = array();
				foreach($loginFormOnSubmitFuncs as $funcRef) {
					list($onSub, $hid) = t3lib_div::callUserFunction($funcRef, $_params, $this->pibase);
					$onSubmitAr[] = $onSub;
					$extraHiddenAr[] = $hid;
				}
			}
			if (count($onSubmitAr)) {
				$onSubmit = implode('; ', $onSubmitAr).'; return true;';
				$onSubmit = strlen($onSubmit) ? ' onsubmit="'.$onSubmit.'"' : '';
				$extraHidden = implode(chr(10), $extraHiddenAr);
			}

		}
		$markerArray['###FORM_ONSUBMIT###'] = $onSubmit;
		$markerArray['###HIDDENFIELDS###'] = $extraHidden;
	}	// addMd5LoginMarkers
	

	/**
		* From the 'KB MD5 FE Password (kb_md5fepw)' extension.
		*
		* @author	Kraft Bernhard <kraftb@gmx.net>
		*/
	function getMD5Submit($cmd) {
		$theTable = $this->data->getTable();
		$JSPart = '
			';
		if ($cmd == 'edit') {
			$JSPart .= 'var pw_change = 0;
			';
		}
		$JSPart .= 'function enc_form(form) {
				var pass = form[\'FE[' . $theTable . '][password]\'].value;
				var pass_again = form[\'FE[' . $theTable . '][password_again]\'].value;
				';
		if ($cmd != 'edit') {
			$JSPart .= 'if (pass == \'\') {
					alert(\'' . $this->lang->pi_getLL('missing_password') . '\');
					form[\'FE[' . $theTable . '][password]\'].select();
					form[\'FE[' . $theTable . '][password]\'].focus();
					return false;
				}
				';
		}
		$JSPart .= 'if (pass != pass_again) {
					alert(\'' . $this->lang->pi_getLL('evalErrors_twice_password') . '\');
					form[\'FE[' . $theTable . '][password]\'].select();
					form[\'FE[' . $theTable . '][password]\'].focus();
					return false;
				}
				';
		if ($cmd == 'edit') {
			$JSPart .= 'if (pw_change) {
				';
		}
		if ($cmd == 'create') {
			$JSPart .= 'if (!enc_pass) {
			';
		}
		$JSPart .= 'var enc_pass = MD5(pass);
					form[\'FE[' . $theTable . '][password]\'].value = enc_pass;
					form[\'FE[' . $theTable . '][password_again]\'].value = enc_pass;
				';
		if ($cmd == 'create' || $cmd == 'edit') {
			$JSPart .= '}
			';
		}
		$JSPart .= 'return true;
			}';
		return $JSPart;
	}	// getMD5Submit


	/**
	* Builds a file uploader
	*
	* @param string  $fName: the field name
	* @param array  $config: the field TCA config
	* @param array  $filenames: array of uploaded file names
	* @param string  $prefix: the field name prefix
	* @return string  generated HTML uploading tags
	*/
	function buildFileUploader($fName, $config, $cmd, $cmdKey, $filenames = array(), $prefix, $viewOnly = false) {
		$HTMLContent = '';
		$size = $config['maxitems'];
		$cmdParts = split('\[|\]', $this->conf[$cmdKey.'.']['evalValues.'][$fName]);
		if(!empty($cmdParts[1])) $size = min($size, intval($cmdParts[1]));
		$size = $size ? $size : 1;
		$number = $size - sizeof($filenames);
		$dir = $config['uploadfolder'];

		if ($this->control->getMode() == MODE_PREVIEW || $viewOnly) {
			for ($i = 0; $i < sizeof($filenames); $i++) {
				if ($this->conf['templateStyle'] == 'css-styled') {
					$HTMLContent .= $filenames[$i] . '<a href="' . $dir.'/' . $filenames[$i] . '"' . $this->pibase->pi_classParam('file-view') . '" target="_blank" title="' . $this->lang->pi_getLL('file_view') . '">' . $this->lang->pi_getLL('file_view') . '</a><br />';
				} else {
					$HTMLContent .= $filenames[$i] . '&nbsp;&nbsp;<small><a href="' . $dir.'/' . $filenames[$i] . '" target="_blank">' . $this->lang->pi_getLL('file_view') . '</a></small><br />';
				}
			}
		} else {
			for($i = 0; $i < sizeof($filenames); $i++) {
				if ($this->conf['templateStyle'] == 'css-styled') {
					$HTMLContent .= $filenames[$i] . '<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($this->conf['icon_delete']) . '" name="'.$prefix.'['.$fName.']['.$i.'][submit_delete]" value="1" title="'.$this->lang->pi_getLL('icon_delete').'" alt="' . $this->lang->pi_getLL('icon_delete'). '"' . $this->pibase->pi_classParam('delete-icon') . ' onclick=\'if(confirm("' . $this->lang->pi_getLL('confirm_file_delete') . '")) return true; else return false;\' />'
							. '<a href="' . $dir.'/' . $filenames[$i] . '"' . $this->pibase->pi_classParam('file-view') . 'target="_blank" title="' . $this->lang->pi_getLL('file_view') . '">' . $this->lang->pi_getLL('file_view') . '</a><br />';
				} else {
					$HTMLContent .= $filenames[$i] . '&nbsp;&nbsp;<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($this->conf['icon_delete']) . '" name="'.$prefix.'['.$fName.']['.$i.'][submit_delete]" value="1" title="'.$this->lang->pi_getLL('icon_delete').'" alt="' . $this->lang->pi_getLL('icon_delete'). '"' . $this->pibase->pi_classParam('icon') . ' onclick=\'if(confirm("' . $this->lang->pi_getLL('confirm_file_delete') . '")) return true; else return false;\' />&nbsp;&nbsp;<small><a href="' . $dir.'/' . $filenames[$i] . '" target="_blank">' . $this->lang->pi_getLL('file_view') . '</a></small><br />';
				}
				$HTMLContent .= '<input type="hidden" name="' . $prefix . '[' . $fName . '][' . $i . '][name]' . '" value="' . $filenames[$i] . '" />';
			}
			for ($i = sizeof($filenames); $i < $number + sizeof($filenames); $i++) {
				$HTMLContent .= '<input id="'. $this->pibase->pi_getClassName($fName) . '-' . ($i-sizeof($filenames)) . '" name="'.$prefix.'['.$fName.']['.$i.']'.'" title="' . $this->lang->pi_getLL('tooltip_' . (($cmd == 'invite')?'invitation_':'')  . 'image') . '" size="40" type="file" '.$this->pibase->pi_classParam('uploader').' /><br />';
			}
		}

		return $HTMLContent;
	}	// buildFileUploader


	/**
	* Adds uploading markers to a marker array
	*
	* @param string  $theField: the field name
	* @param array  $markerArray: the input marker array
	* @param array  $dataArray: the record array
	* @return void
	*/
	function addFileUploadMarkers($theField, &$markerArray, $cmd, $cmdKey, $dataArray = array(), $viewOnly = false) {
		$theTable = $this->data->getTable();

		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$filenames = array();
		if ($dataArray[$theField]) {
			$filenames = explode(',', $dataArray[$theField]);
		}
		if ($viewOnly) {
			$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] = $this->buildFileUploader($theField, $this->tca->TCA['columns'][$theField]['config'], $cmd, $cmdKey, $filenames, 'FE['.$theTable.']', true);
		} else {
			$markerArray['###UPLOAD_' . $theField . '###'] = $this->buildFileUploader($theField, $this->tca->TCA['columns'][$theField]['config'], $cmd, $cmdKey, $filenames, 'FE['.$theTable.']');
		}
	}	// addFileUploadMarkers


	function addHiddenFieldsMarkers(&$markerArray, $cmdKey, $mode, $dataArray = array()) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$theTable = $this->data->getTable();

		if ($this->conf[$cmdKey.'.']['preview'] && $mode != MODE_PREVIEW) {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[preview]" value="1" />';
			if ($theTable == 'fe_users' && $cmdKey == 'edit' && $this->conf[$cmdKey.'.']['useEmailAsUsername']) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][username]" value="'. htmlspecialchars($dataArray['username']).'" />';
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][email]" value="'. htmlspecialchars($dataArray['email']).'" />';
			}
		}
		if ($mode == MODE_PREVIEW && $this->conf['templateStyle'] == 'css-styled') {
			$fields = explode(',', $this->conf[$cmdKey.'.']['fields']);
			$fields = array_diff($fields, array( 'hidden', 'disable'));
			if ($theTable == 'fe_users') {
				$fields[] = 'password_again';
				if ($this->conf[$cmdKey.'.']['useEmailAsUsername'] || $this->conf[$cmdKey.'.']['generateUsername']) {
					$fields = array_merge($fields, array( 'username'));
				}
				if ($this->conf[$cmdKey.'.']['useEmailAsUsername']) {
					$fields = array_merge($fields, array( 'email'));
				}
			}
			while (list(, $fName) = each($fields)) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.']['.$fName.']" value="'. htmlspecialchars($dataArray[$fName]).'" />';
			}
		}
	}	// addHiddenFieldsMarkers


	/**
	* Removes irrelevant Static Info subparts (zone selection when the country has no zone)
	*
	* @param string  $templateCode: the input template
	* @param array  $markerArray: the marker array
	* @return string  the output template
	*/
	function removeStaticInfoSubparts($templateCode, &$markerArray, $viewOnly = false) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		if ($this->control->getMode() == MODE_PREVIEW || $viewOnly) {
			if (!$markerArray['###FIELD_zone###'] ) {
				return $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
			}
		} else {
			if (!$markerArray['###SELECTOR_ZONE###'] ) {
				return $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', ''); 
			}
		}
		return $templateCode;
	}	// removeStaticInfoSubparts


	/**
		*  Store the setfixed vars and return a replacement hash
		*/
	function storeFixedPiVars($vars) {
		global $TYPO3_DB;
		
			// create a unique hash value
		$regHash_array = t3lib_div::cHashParams(t3lib_div::implodeArrayForUrl('',$vars));
		$regHash_calc = t3lib_div::shortMD5(serialize($regHash_array),20);
			// and store it with a serialized version of the array in the DB
		$res = $TYPO3_DB->exec_SELECTquery('md5hash','cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash_calc,'cache_md5params'));
		if (!$TYPO3_DB->sql_num_rows($res))  {
			$insertFields = array (
				'md5hash' => $regHash_calc,
				'tstamp' => time(),
				'type' => 99,
				'params' => serialize($vars)
			);
			$TYPO3_DB->exec_INSERTquery('cache_md5params',$insertFields);
		}
		return $regHash_calc;
	}	// storeFixedPiVars



}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/marker/class.tx_srfeuserregister_marker.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/marker/class.tx_srfeuserregister_marker.php']);
}
?>
