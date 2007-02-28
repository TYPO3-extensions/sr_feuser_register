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
	var $tca;
	var $auth;

	var $thePid;
	var $thePidTitle;
	var $cmd;
	var $site_url;
	var $backURL;
	var $prefixId;
	var $previewLabel;
	var $staticInfo;
	var $useMd5Password;
	var $setfixedEnabled;

	var $confirmPID;
	var $confirmInvitationPID;

	var $pidArray = array();
	var $markerArray = array();
	var $sys_language_content;
	var $confirmType;
	var $cObj;


	function init(&$pibase, &$conf, &$config, &$data, &$tca, &$lang, $auth)	{
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->data = &$data;
		$this->tca = &$tca;
		$this->lang = &$lang;
		$this->auth = $auth;

		$this->cObj = &$pibase->cObj;
		$this->thePid = $pibase->thePid;


			// set the title language overlay
		$pidRecord = t3lib_div::makeInstance('t3lib_pageSelect');
		$pidRecord->init(0);
		$pidRecord->sys_language_uid = $this->sys_language_content;
		$row = $pidRecord->getPage($this->thePid);

		$this->thePidTitle = trim($this->conf['pidTitleOverride']) ? trim($this->conf['pidTitleOverride']) : $row['title'];

		$this->backURL = $pibase->backURL;
		$this->cmd = $pibase->cmd;
		$this->site_url = $pibase->site_url;
		$this->backURL = $pibase->backURL;
		$this->prefixId = $pibase->prefixId;

		$this->previewLabel = $pibase->previewLabel;
		$this->staticInfo = $pibase->staticInfo;
		$this->useMd5Password = $pibase->useMd5Password;
		$this->setfixedEnabled = $pibase->setfixedEnabled;
		$this->sys_language_content = $pibase->sys_language_content;
		$this->useMd5Password = $pibase->useMd5Password;

		$this->confirmType = intval($this->conf['confirmType']) ? strval(intval($this->conf['confirmType'])) : $TSFE->type;
		if ($this->conf['confirmType'] == '0' ) {
			$this->confirmType = '0';
		};

		// set the pid's
		$registerPID = intval($this->conf['registerPID']) ? strval(intval($this->conf['registerPID'])) : $TSFE->id;
		$editPID = intval($this->conf['editPID']) ? strval(intval($this->conf['editPID'])) : $TSFE->id;
		$infomailPID = intval($this->conf['infomailPID']) ? strval(intval($this->conf['infomailPID'])) : $registerPID;
		$this->confirmPID = intval($this->conf['confirmPID']) ? strval(intval($this->conf['confirmPID'])) : $registerPID;
		$this->confirmInvitationPID = intval($this->conf['confirmInvitationPID']) ? strval(intval($this->conf['confirmInvitationPID'])) : $this->confirmPID;
		$loginPID = intval($this->conf['loginPID']) ? strval(intval($this->conf['loginPID'])) : $TSFE->id;

		$this->pidArray = array('edit' => $editPID, 'register' => $registerPID, 'login' => $loginPID, 'infomail' => $infomailPID);

			// Initialise static info library
		$this->staticInfo = t3lib_div::makeInstance('tx_staticinfotables_pi1');
		$this->staticInfo->init();
	}

	function &getArray()	{
		return $this->markerArray;
	}

	function setArray($param, $value = '')	{
		if (is_array($param))	{
			$this->markerArray = $param;
		} else {
			$this->markerArray[$param] = $value;
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
		global $TSFE;
		
		if ($this->setfixedEnabled && is_array($setfixed) ) {
			$setfixedpiVars = array();
			
			reset($setfixed);
			while (list($theKey, $data) = each($setfixed)) {
				if (strstr($theKey, '.') ) {
					$theKey = substr($theKey, 0, -1);
				}
				unset($setfixedpiVars);
				$recCopy = $r;
				$setfixedpiVars[$this->prefixId.'[rU]'] = $r[uid];

				if ( $this->data->theTable != 'fe_users' && $theKey == 'EDIT' ) {
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
					$setfixedpiVars[$this->prefixId.'[aC]'] = $this->auth->setfixedHash($recCopy, $data['_FIELDLIST']);
					$linkPID = $this->confirmPID;
					if ($this->cmd == 'invite') {
						$linkPID = $this->confirmInvitationPID;
					}
				}
				if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = t3lib_div::_GP('L');
				}

				if ($this->conf['useShortUrls']) {
					$thisHash = $this->storeFixedPiVars($setfixedpiVars);
					$setfixedpiVars = array($this->prefixId.'[regHash]' => $thisHash);
				}
				$markerArray['###SETFIXED_'.$this->cObj->caseshift($theKey,'upper').'_URL###'] = ($TSFE->absRefPrefix ? '' : $this->site_url) . $this->cObj->getTypoLink_URL($linkPID.','.$this->confirmType, $setfixedpiVars);
			}
		}
		return $markerArray;
	}	// setfixed


	/**
	* Adds language-dependent label markers
	*
	* @param array  $markerArray: the input marker array
	* @param array  $dataArray: the record array
	* @return array  the output marker array
	*/
	function addLabelMarkers($markerArray, $dataArray) {
		// Data field labels
		$infoFields = t3lib_div::trimExplode(',', $this->data->fieldList, 1);
		while (list(, $fName) = each($infoFields)) {
			$markerArray['###LABEL_'.$this->cObj->caseshift($fName,'upper').'###'] = $this->lang->pi_getLL($fName) ? $this->lang->pi_getLL($fName) : $this->lang->getLLFromString($this->tca->TCA['columns'][$fName]['label']);
			$markerArray['###TOOLTIP_'.$this->cObj->caseshift($fName,'upper').'###'] = $this->lang->pi_getLL('tooltip_' . $fName);
			$markerArray['###TOOLTIP_INVITATION_'.$this->cObj->caseshift($fName,'upper').'###'] = $this->lang->pi_getLL('tooltip_invitation_' . $fName);
			// <Ries van Twisk added support for multiple checkboxes>
			$colConfig = $this->tca->TCA['columns'][$fName]['config'];
			
			if ($colConfig['type'] == 'select' && $colConfig['items'])	{ // (is_array($dataArray[$fName])) {
				$colContent = '';
				$markerArray['###FIELD_'.$fName.'_CHECKED###'] = '';
				$markerArray['###LABEL_'.$fName.'_CHECKED###'] = '';
				$this->data->dataArr['###POSTVARS_'.$fName.'###'] = '';
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
			if (in_array(trim($fName), $this->data->requiredArr) ) {
				$markerArray['###REQUIRED_'.$this->cObj->caseshift($fName,'upper').'###'] = '<span>*</span>';
				$markerArray['###MISSING_'.$this->cObj->caseshift($fName,'upper').'###'] = $this->lang->pi_getLL('missing_'.$fName);
				$markerArray['###MISSING_INVITATION_'.$this->cObj->caseshift($fName,'upper').'###'] = $this->lang->pi_getLL('missing_invitation_'.$fName);
			} else {
				$markerArray['###REQUIRED_'.$this->cObj->caseshift($fName,'upper').'###'] = '';
			}
		}
		// Button labels
		$buttonLabelsList = 'register,confirm_register,back_to_form,update,confirm_update,enter,confirm_delete,cancel_delete,update_and_more';
		$buttonLabels = t3lib_div::trimExplode(',', $buttonLabelsList, 1);
		while (list(, $labelName) = each($buttonLabels) ) {
			$markerArray['###LABEL_BUTTON_'.$this->cObj->caseshift($labelName,'upper').'###'] = $this->lang->pi_getLL('button_'.$labelName);
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
			
		if (isset($this->conf['extraLabels']) && $this->conf['extraLabels'] != '') {
			$otherLabelsList .= ',' . $this->conf['extraLabels'];
		}
		$otherLabels = t3lib_div::trimExplode(',', $otherLabelsList, 1);
		while (list(, $labelName) = each($otherLabels) ) {
			$markerArray['###LABEL_'.$this->cObj->caseshift($labelName,'upper').'###'] = sprintf($this->lang->pi_getLL($labelName), $this->thePidTitle, $dataArray['username'], $dataArray['name'], $dataArray['email'], $dataArray['password']); 
		}

		return $markerArray;
	}	// addLabelMarkers


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
		
		if ($this->conf['templateStyle'] == 'css-styled') {
			$form = $this->pibase->pi_getClassName($this->data->theTable.'_form');
		} else {
			$form = $this->data->theTable.'_form';
		}

		$markerArray['###FORM_NAME###'] = $form; // $this->conf['formName'];
		$unsetVars['cmd'] = '';

		$vars['cmd'] = $this->cmd;
		$vars['backURL'] = rawurlencode($this->get_url('', $GLOBALS['TSFE']->id.','.$GLOBALS['TSFE']->type, $vars));
		$vars['cmd'] = 'delete';
		$vars['rU'] = $this->data->recUid;
		$vars['preview'] = '1';
		$markerArray['###DELETE_URL###'] = $this->get_url('', $this->pidArray['edit'].','.$GLOBALS['TSFE']->type, $vars);
		
		$vars['backURL'] = rawurlencode($markerArray['###FORM_URL###']);
		$vars['cmd'] = 'create';
		$markerArray['###REGISTER_URL###'] = $this->get_url('', $this->pidArray['register'].','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'edit';
		$markerArray['###EDIT_URL###'] = $this->get_url('', $this->pidArray['edit'].','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'login';
		$markerArray['###LOGIN_FORM###'] = $this->get_url('', $this->pidArray['login'].','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
		$vars['cmd'] = 'infomail';
		$markerArray['###INFOMAIL_URL###'] = $this->get_url('', $this->pidArray['infomail'].','.$GLOBALS['TSFE']->type, $vars, $unsetVars);
			
		$markerArray['###THE_PID###'] = $this->thePid;
		$markerArray['###THE_PID_TITLE###'] = $this->thePidTitle;
		$markerArray['###BACK_URL###'] = $this->backURL;
		$markerArray['###SITE_NAME###'] = $this->conf['email.']['fromName'];
		$markerArray['###SITE_URL###'] = $this->site_url;
		$markerArray['###SITE_WWW###'] = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
		$markerArray['###SITE_EMAIL###'] = $this->conf['email.']['from'];

		$markerArray['###HIDDENFIELDS###'] = '';
		if($this->data->theTable == 'fe_users') {
			$markerArray['###HIDDENFIELDS###'] = ($this->cmd?'<input type="hidden" name="'.$this->prefixId.'[cmd]" value="'.$this->cmd.'" />':'');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($this->auth->authCode?'<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->auth->authCode.'" />':'');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . ($this->backURL?'<input type="hidden" name="'.$this->prefixId.'[backURL]" value="'.htmlspecialchars($this->backURL).'" />':'');
		}
		return $markerArray;
	}	// addURLMarkers

	
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
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->data->theTable.'][zone]" value="" />';
			}
			$markerArray['###FIELD_language###'] = $this->staticInfo->getStaticInfoName('LANGUAGES', is_array($dataArray)?$dataArray['language']:'');
		} else {
			if ($this->conf['templateStyle'] == 'css-styled') {
				$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector('COUNTRIES', 'FE['.$this->data->theTable.']'.'[static_info_country]', '', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->conf['onChangeCountryAttribute'], $this->pibase->pi_getClassName('static_info_country'), $this->pibase->lang->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'static_info_country'));
				$markerArray['###SELECTOR_ZONE###'] = $this->staticInfo->buildStaticInfoSelector('SUBDIVISIONS', 'FE['.$this->data->theTable.']'.'[zone]', '', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->pibase->pi_getClassName('zone'), $this->lang->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'zone'));
				if (!$markerArray['###SELECTOR_ZONE###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->data->theTable.'][zone]" value="" />';
				}
				$markerArray['###SELECTOR_LANGUAGE###'] = $this->staticInfo->buildStaticInfoSelector('LANGUAGES', 'FE['.$this->data->theTable.']'.'[language]', '', is_array($dataArray)?$dataArray['language']:'', '', '', $this->pibase->pi_getClassName('language'), $this->lang->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'language'));
			} else {
				$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector('COUNTRIES', 'FE['.$this->data->theTable.']'.'[static_info_country]', '', is_array($dataArray)?$dataArray['static_info_country']:'', '', $this->conf['onChangeCountryAttribute']);
				$markerArray['###SELECTOR_ZONE###'] = $this->staticInfo->buildStaticInfoSelector('SUBDIVISIONS', 'FE['.$this->data->theTable.']'.'[zone]', '', is_array($dataArray)?$dataArray['zone']:'', is_array($dataArray)?$dataArray['static_info_country']:'');
				if (!$markerArray['###SELECTOR_ZONE###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$this->data->theTable.'][zone]" value="" />';
				}
				$markerArray['###SELECTOR_LANGUAGE###'] = $this->staticInfo->buildStaticInfoSelector('LANGUAGES', 'FE['.$this->data->theTable.']'.'[language]', '', is_array($dataArray)?$dataArray['language']:'');
			}
		}
		return $markerArray;
	}	// addStaticInfoMarkers


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
			$mode = count($this->data->dataArr) ? 'edit' : $cmd;
			$GLOBALS['TSFE']->additionalHeaderData['MD5_script'] = '<script type="text/javascript" src="typo3/md5.js"></script>';
			$GLOBALS['TSFE']->JSCode .= $this->getMD5Submit($mode);
			$markerArray['###FORM_ONSUBMIT###'] = 'onsubmit="return enc_form(this);"';
			if ($mode == 'edit') {
				$markerArray['###PASSWORD_ONCHANGE###'] = 'onchange="pw_change=1; return true;"';
			}
		}
		return $markerArray;
	}	// addMd5EventsMarkers


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
		return $markerArray;
	}	// addMd5LoginMarkers
	

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
				var pass = form['FE[" . $this->data->theTable . "][password]'].value;
				var pass_again = form['FE[" . $this->data->theTable . "][password_again]'].value;
				";
		if ($cmd != 'edit') {
			$JSPart .= "if (pass == '') {
					alert('" . $this->lang->pi_getLL('missing_password') . "');
					form['FE[" . $this->data->theTable . "][password]'].select();
					form['FE[" . $this->data->theTable . "][password]'].focus();
					return false;
				}
				";
		}
		$JSPart .= "if (pass != pass_again) {
					alert('" . $this->lang->pi_getLL('evalErrors_twice_password') . "');
					form['FE[" . $this->data->theTable . "][password]'].select();
					form['FE[" . $this->data->theTable . "][password]'].focus();
					return false;
				}
				";
		if ($cmd == 'edit') {
			$JSPart .= "if (pw_change) {
					";
		}
		$JSPart .= "var enc_pass = MD5(pass);
					form['FE[" . $this->data->theTable . "][password]'].value = enc_pass;
					form['FE[" . $this->data->theTable . "][password_again]'].value = enc_pass;
				";
		if ($cmd == 'edit') {
			$JSPart .= "}
				";
		}
		$JSPart .= "return true;
			}";
		return $JSPart;
	}	// getMD5Submit


	/**
		* Adds CSS styles marker to a marker array for substitution in an HTML email message
		*
		* @param array  $markerArray: the input marker array
		* @return array  the output marker array
		*/
	function addCSSStyleMarkers($markerArray) {
		$HTMLMailEnabled = $this->conf['email.'][HTMLMail];
		if ($HTMLMailEnabled ) {
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
	}	// addCSSStyleMarkers

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
				$HTMLContent .= '<input id="'. $this->pibase->pi_getClassName($fName) . '-' . ($i-sizeof($filenames)) . '" name="'.$prefix.'['.$fName.']['.$i.']'.'" title="' . $this->lang->pi_getLL('tooltip_' . (($this->cmd == 'invite')?'invitation_':'')  . 'image') . '" size="40" type="file" '.$this->pibase->pi_classParam('uploader').' /><br />';
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
	* @return array  the output marker array
	*/
	function addFileUploadMarkers($theField, $markerArray, $dataArr = array(), $viewOnly = false) {
		$filenames = array();
		if ($dataArr[$theField]) {
			$filenames = explode(',', $dataArr[$theField]);
		}
		if ($this->previewLabel || $viewOnly) {
			$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] = $this->buildFileUploader($theField, $this->tca->TCA['columns'][$theField]['config'], $filenames, 'FE['.$this->data->theTable.']', true);
		} else {
			$markerArray['###UPLOAD_' . $theField . '###'] = $this->buildFileUploader($theField, $this->tca->TCA['columns'][$theField]['config'], $filenames, 'FE['.$this->data->theTable.']');
		}
		return $markerArray;
	}	// addFileUploadMarkers


	function addHiddenFieldsMarkers($markerArray, $dataArr = array()) {
		if ($this->conf[$this->cmdKey.'.']['preview'] && !$this->previewLabel) {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[preview]" value="1" />';
			if ($this->data->theTable == 'fe_users' && $this->cmdKey == 'edit' && $this->conf[$this->cmdKey.'.']['useEmailAsUsername']) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->data->theTable.'][username]" value="'. htmlspecialchars($dataArr['username']).'" />';
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->data->theTable.'][email]" value="'. htmlspecialchars($dataArr['email']).'" />';
			}
		}
		if ($this->previewLabel && $this->conf['templateStyle'] == 'css-styled') {
			$fields = explode(',', $this->conf[$this->cmdKey.'.']['fields']);
			$fields = array_diff($fields, array( 'hidden', 'disable'));
			if ($this->data->theTable == 'fe_users') {
				$fields[] = 'password_again';
				if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername'] || $this->conf[$this->cmdKey.'.']['generateUsername']) {
					$fields = array_merge($fields, array( 'username'));
				}
				if ($this->conf[$this->cmdKey.'.']['useEmailAsUsername']) {
					$fields = array_merge($fields, array( 'email'));
				}
			}
			while (list(, $fName) = each($fields)) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$this->data->theTable.']['.$fName.']" value="'. htmlspecialchars($dataArr[$fName]).'" />';
			}
		}
		return $markerArray;
	}	// addHiddenFieldsMarkers


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
	}	// removeStaticInfoSubparts


	/**
		*  Store the setfixed vars and return a replacement hash
		*/
	function storeFixedPiVars($vars) {
		global $TYPO3_DB, $TYPO3_CONF_VARS;
		
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
