<?php

/*
 *  Copyright notice
 *
 *  (c) 2007-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

/**
 * Marker functions
 */

define('SAVED_SUFFIX', '_SAVED');
define('SETFIXED_PREFIX', 'SETFIXED_');


class tx_srfeuserregister_marker
{
	/**
	 * @var string Extension name
	 */
	public $extensionName = 'SrFeuserRegister';

	public $conf = array();
	public $data;
	public $control;
	public $controlData;
	public $tca;
	protected $pibaseObj;
	public $previewLabel;
	public $staticInfo;
	public $markerArray = array();
	public $buttonLabelsList;
	public $otherLabelsList;
	public $dataArray; // temporary array of data
	private $urlMarkerArray;

	public function init (
		$confObj,
		$data,
		$tca,
		$controlData,
		$uid,
		$token,
		$pibaseObj
	) {
		$this->conf = $confObj->getConf();
		$this->data = $data;
		$this->tca = $tca;
		$this->pibaseObj = $pibaseObj;
		$this->controlData = $controlData;
		$theTable = $this->controlData->getTable();

		if (t3lib_extMgm::isLoaded('static_info_tables')) {
			$this->staticInfo = t3lib_div::getUserObj('&tx_staticinfotables_pi1');
		}
		$markerArray = array();

		$charset = $GLOBALS['TSFE']->metaCharset ? $GLOBALS['TSFE']->metaCharset : 'utf-8';
		$prefixId = $this->controlData->getPrefixId();
		$extKey = $this->controlData->getExtKey();
		$markerArray['###CHARSET###'] = $charset;
		$markerArray['###PREFIXID###'] = $prefixId;

			// Setting URL, HIDDENFIELDS and signature markers
		$urlMarkerArray = $this->generateURLMarkers(
				$this->controlData->getBackURL(),
				$uid,
				$token,
				$theTable,
				$extKey,
				$prefixId
			);
		$this->setUrlMarkerArray($urlMarkerArray);
		$markerArray = array_merge($markerArray, $urlMarkerArray);
		$this->setArray($markerArray);

			// Button labels
		$buttonLabelsList = 'register,confirm_register,back_to_form,update,confirm_update,enter,confirm_delete,cancel_delete,update_and_more,password_forgotten';

		$this->setButtonLabelsList($buttonLabelsList);

		$otherLabelsList = 'yes,no,new_password,password_again,tooltip_password_again,tooltip_invitation_password_again,click_here_to_register,tooltip_click_here_to_register,click_here_to_edit,tooltip_click_here_to_edit,click_here_to_delete,tooltip_click_here_to_delete,click_here_to_see_terms,tooltip_click_here_to_see_terms'.
		',copy_paste_link,enter_account_info,enter_invitation_account_info,required_info_notice,excuse_us,'.
			',tooltip_login_username,tooltip_login_password,'.
			',registration_problem,registration_sorry,registration_clicked_twice,registration_help,kind_regards,kind_regards_cre,kind_regards_del,kind_regards_ini,kind_regards_inv,kind_regards_upd'.
			',v_dear,v_verify_before_create,v_verify_invitation_before_create,v_verify_before_update,v_really_wish_to_delete,v_edit_your_account'.
			',v_now_enter_your_username,v_now_choose_password,v_notification'.
			',v_registration_created,v_registration_created_subject,v_registration_created_message1,v_registration_created_message2,v_registration_created_message3'.
			',v_to_the_administrator'.
			',v_registration_review_subject,v_registration_review_message1,v_registration_review_message2,v_registration_review_message3'.
			',v_please_confirm,v_your_account_was_created,v_your_account_was_created_nomail,v_follow_instructions1,v_follow_instructions2,v_follow_instructions_review1,v_follow_instructions_review2'.
			',v_invitation_confirm,v_invitation_account_was_created,v_invitation_instructions1'.
			',v_registration_initiated,v_registration_initiated_subject,v_registration_initiated_message1,v_registration_initiated_message2,v_registration_initiated_message3,v_registration_initiated_review1,v_registration_initiated_review2'.
			',v_registration_invited,v_registration_invited_subject,v_registration_invited_message1,v_registration_invited_message1a,v_registration_invited_message2'.
			',v_registration_infomail_message1a'.
			',v_registration_confirmed,v_registration_confirmed_subject,v_registration_confirmed_message1,v_registration_confirmed_message2,v_registration_confirmed_review1,v_registration_confirmed_review2'.
			',v_registration_cancelled,v_registration_cancelled_subject,v_registration_cancelled_message1,v_registration_cancelled_message2'.
			',v_registration_accepted,v_registration_accepted_subject,v_registration_accepted_message1,v_registration_accepted_message2'.
			',v_registration_refused,v_registration_refused_subject,v_registration_refused_message1,v_registration_refused_message2'.
			',v_registration_accepted_subject2,v_registration_accepted_message3,v_registration_accepted_message4'.
			',v_registration_refused_subject2,v_registration_refused_message3,v_registration_refused_message4'.
			',v_registration_entered,v_registration_entered_subject,v_registration_entered_message1,v_registration_entered_message2'.
			',v_registration_updated,v_registration_updated_subject,v_registration_updated_message1'.
			',v_registration_deleted,v_registration_deleted_subject,v_registration_deleted_message1,v_registration_deleted_message2'.
			',v_registration_unsubscribed,v_registration_unsubscribed_subject,v_registration_unsubscribed_message1,v_registration_unsubscribed_message2';
		$this->setOtherLabelsList($otherLabelsList);
	}


	public function getButtonLabelsList () {
		return $this->buttonLabelsList;
	}


	public function setButtonLabelsList ($buttonLabelsList) {
		$this->buttonLabelsList = $buttonLabelsList;
	}


	public function getOtherLabelsList () {
		return $this->otherLabelsList;
	}


	public function setOtherLabelsList ($otherLabelsList) {
		$this->otherLabelsList = $otherLabelsList;
	}


	public function addOtherLabelsList ($otherLabelsList) {
		if ($otherLabelsList != '') {

			$formerOtherLabelsList = $this->getOtherLabelsList();

			if ($formerOtherLabelsList != '') {
				$newOtherLabelsList = $formerOtherLabelsList . ',' . $otherLabelsList;
				$newOtherLabelsList = t3lib_div::uniqueList($newOtherLabelsList);
				$this->setOtherLabelsList($newOtherLabelsList);
			}
		}
	}


	public function getArray () {
		return $this->markerArray;
	}


	public function setArray ($param, $value = '') {
		if (is_array($param)) {
			$this->markerArray = $param;
		} else {
			$this->markerArray[$param] = $value;
		}
	}


	public function getPreviewLabel () {
		return $this->previewLabel;
	}


	public function setPreviewLabel ($label) {
		$this->previewLabel = $label;
	}


	// enables the usage of {data:<field>}, {tca:<field>} and {meta:<stuff>} in the label markers
	public function replaceVariables (
		$matches
	) {
		$confObj = t3lib_div::getUserObj('&tx_srfeuserregister_conf');
		$cObj = t3lib_div::makeInstance('tslib_cObj');;
		$conf = $confObj->getConf();
		$controlData = t3lib_div::getUserObj('&tx_srfeuserregister_controldata');

		$rc = '';

		switch ($matches[1]) {
			case 'data':
				$dataArray = $this->getReplaceData();
				$row = $dataArray['row'];
				$rc = $row[$matches[2]];
			break;
			case 'tca':
				if (!is_array($this->tmpTcaMarkers)) {
					$this->tmpTcaMarkers = array();
					$dataArray = $this->getReplaceData();
					$row = $dataArray['row'];
					$cmd = $controlData->getCmd();
					$cmdKey = $controlData->getCmdKey();
					$theTable = $controlData->getTable();
					$this->tca->addTcaMarkers(
						$this->tmpTcaMarkers,
						$conf,
						$cObj,
						$controlData,
						$row,
						$this->data->getOrigArray(),
						$cmd,
						$cmdKey,
						$theTable,
						$controlData->getPrefixId(),
						TRUE,
						'',
						FALSE
					);
				}
				$rc = $this->tmpTcaMarkers['###TCA_INPUT_' . $matches[2] . '###'];
			break;
			case 'meta':
				if ($matches[2] == 'title') {
					$rc = $controlData->getPidTitle();
				}
			break;
		}
		if (is_array($rc)) {
			$rc = implode(',', $rc);
		}
		return $rc;
	}


	public function setReplaceData ($data) {
		$this->dataArray['row'] = $data['row'];
	}


	public function getReplaceData () {
		return $this->dataArray;
	}


	/**
	* Sets the error markers to 'no error'
	*
	* @param string command key
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return void  all initialization done directly on array $this->dataArray
	*/
	public function setNoError (
		$cmdKey,
		&$markContentArray
	) {
		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			foreach($this->conf[$cmdKey . '.']['evalValues.'] as $theField => $theValue) {
				$markContentArray['###EVAL_ERROR_FIELD_' . $theField . '###'] = '<!--no error-->';
			}
		}
	} // setNoError


	/**
	 * Gets the field name needed for the name attribute of the input HTML tag.
	 *
	 * @param string name of the table
	 * @param string name of the field
	 * @return string  FE[tablename][fieldname]  ... POST var to transmit the entries with the form
	 */
	public function getFieldName($theTable, $theField)
	{
		if ($theField === 'password') {
			// See FrontendLoginFormRsaEncryption.js
			$fieldName = 'pass';
		} else {
			$fieldName = 'FE[' . $theTable . '][' . $theField . ']';
		}
		return $fieldName;
	}

	/**
	* Adds language-dependant label markers
	*
	* @param array  $markerArray: the input marker array
	* @param array  $row: the record array
	* @param array  $origRow: the original record array as stored in the database
	* @param array  $requiredArray: the required fields array
	* @param array  info fields
	* @param array  $TCA[tablename]['columns']
	* @return void
	*/
	public function addLabelMarkers(
		&$markerArray,
		$conf,
		$cObj,
		$extKey,
		$theTable,
		$row,
		$origRow,
		$securedArray,
		$keepFields,
		$requiredArray,
		$infoFields,
		$tcaColumns,
		$bChangesOnly = false,
		$prefixId
	) {
		$formUrlMarkerArray = $this->generateFormURLMarkers($prefixId);
		$urlMarkerArray = $this->getUrlMarkerArray();
		$formUrlMarkerArray = array_merge($urlMarkerArray, $formUrlMarkerArray);

		if (is_array($securedArray)) {
			foreach ($securedArray as $field => $value) {
				$row[$field] = $securedArray[$field];
			}
		}

		if (!$markerArray) {
			$markerArray = $this->getArray();
		}

		// Data field labels
		$infoFieldArray = t3lib_div::trimExplode(',', $infoFields, 1);
		$charset = $GLOBALS['TSFE']->renderCharset ? $GLOBALS['TSFE']->renderCharset : 'utf-8';
		$specialFieldArray = t3lib_div::trimExplode(',', $this->data->getSpecialFieldList(), 1);

		if ($specialFieldArray[0] != '') {
			$infoFieldArray = array_merge($infoFieldArray, $specialFieldArray);
			$requiredArray = array_merge($requiredArray, $specialFieldArray);
		}

		foreach($infoFieldArray as $theField) {
			$markerkey = $cObj->caseshift($theField, 'upper');
			$bValueChanged = FALSE;

			if (isset($row[$theField]) && isset($origRow[$theField])) {
				if (is_array($row[$theField]) && is_array($origRow[$theField])) {
					$diffArray = array_diff($row[$theField], $origRow[$theField]);
					if (count($diffArray)) {
						$bValueChanged = TRUE;
					}
				} else {
					if ($row[$theField] != $origRow[$theField]) {
						$bValueChanged = TRUE;
					}
				}
			}

			if (!$bChangesOnly || $bValueChanged || in_array($theField, $keepFields)) {
				$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($theTable . '.' . $theField, $this->extensionName);
				if (empty($label)) {
					$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($theField, $this->extensionName);
				}
				$label = (empty($label) ? \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($tcaColumns[$theField]['label'], $this->extensionName) : $label);
				$label = htmlspecialchars($label, ENT_QUOTES, $charset);
			} else {
				$label = '';
			}
			$markerArray['###LABEL_' . $markerkey . '###'] = $label;
			$markerArray['###TOOLTIP_' . $markerkey . '###'] = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_' . $theField, $this->extensionName);
			$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_invitation_' . $theField, $this->extensionName);
			$label = htmlspecialchars($label, ENT_QUOTES, $charset);
			$markerArray['###TOOLTIP_INVITATION_' . $markerkey . '###'] = $label;
			$colConfig = $tcaColumns[$theField]['config'];

			if ($colConfig['type'] == 'select' && $colConfig['items']) {
				$colContent = '';
				$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] = '';
				$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] = '';
				$markerArray['###POSTVARS_' . $markerkey . '###'] = '';
				if (isset($row[$theField])) {
					if (is_array($row[$theField])) {
						$fieldArray = $row[$theField];
					} else {
						$fieldArray = t3lib_div::trimExplode(',', $row[$theField]);
					}
					foreach ($fieldArray as $key => $value) {
						$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($colConfig['items'][$value][0], $this->extensionName);
						$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] .= '- ' . $label . '<br />';
						$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] .= '- ' . $label . '<br />';
						$markerArray['###POSTVARS_' . $markerkey.'###'] .= chr(10) . '	<input type="hidden" name="FE[fe_users][' . $theField . '][' . $key . ']" value ="' . $value . '" />';
					}
				}
			} else if ($colConfig['type'] == 'check') {
				$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] = ($row[$theField]) ? 'checked' : '';
				$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] = ($row[$theField]) ? \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('yes', $this->extensionName) : \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('no', $this->extensionName);
			}

			if (in_array(trim($theField), $requiredArray)) {
				$markerArray['###REQUIRED_' . $markerkey . '###'] = $cObj->cObjGetSingle($conf['displayRequired'], $conf['displayRequired.'], $extKey);
				$key = 'missing_' . $theField;
				$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($key, $this->extensionName);
				if ($label == '') {
					$label = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('internal_no_text_found', $this->extensionName);
					$label = sprintf($label, $key);
				}
				$markerArray['###MISSING_' . $markerkey . '###'] = $label;
				$markerArray['###MISSING_INVITATION_' . $markerkey . '###'] = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('missing_invitation_' . $theField, $this->extensionName);
			} else {
				$markerArray['###REQUIRED_' . $markerkey . '###'] = '';
				$markerArray['###MISSING_' . $markerkey . '###'] = '';
				$markerArray['###MISSING_INVITATION_' . $markerkey . '###'] = '';
			}
			$markerArray['###NAME_' . $markerkey . '###'] = $this->getFieldName($theTable, $theField);
		}
		$markerArray['###NAME_PASSWORD_AGAIN###'] = $this->getFieldName($theTable, 'password_again');
		$buttonLabels = t3lib_div::trimExplode(',', $this->getButtonLabelsList(), 1);

		foreach($buttonLabels as $labelName) {
			if ($labelName) {
				$buttonKey = strtoupper($labelName);
				$markerArray['###LABEL_BUTTON_' . $buttonKey . '###'] = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('button_' . $labelName, $this->extensionName);
				$attributes = '';

				if (
					isset($conf['button.'])
					&& isset($conf['button.'][$buttonKey . '.'])
					&& isset($conf['button.'][$buttonKey . '.']['attribute.'])
				) {
					$attributesArray = array();
					foreach ($conf['button.'][$buttonKey . '.']['attribute.'] as $key => $value) {
						$attributesArray[] = $key . '="' . $value . '"';
					}
					$attributes = implode(' ', $attributesArray);
					$attributes = $cObj->substituteMarkerArray($attributes, $formUrlMarkerArray);
				}
				$markerArray['###ATTRIBUTE_BUTTON_' . $buttonKey . '###'] = $attributes;
			}
		}
			// Assemble the name to be substituted in the labels
		$name = '';
		if ($conf['salutation'] == 'informal' && $row['first_name'] != '') {
			$name = $row['first_name'];
		} else {
				// Honour Address List (tt_address) configuration settings
			if ($theTable == 'tt_address' && t3lib_extMgm::isLoaded('tt_address') && isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address'])) {
				$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address']);
				if (is_array($extConf)) {
					$nameFormat = '';
					if ($extConf['disableCombinedNameField'] != '1' && $row['name'] != '') {
						$name = $row['name'];
					} else if (isset($extConf['backwardsCompatFormat'])) {
						$nameFormat = $extConf['backwardsCompatFormat'];
					}
					if ($nameFormat != '') {
						$name = sprintf(
							$nameFormat,
							$row['first_name'],
							$row['middle_name'],
							$row['last_name']
						);
					}
				}
			}
			if ($name == '' && isset($row['name'])) {
				$name = trim($row['name']);
			}
			if ($name == '') {
				$name = ((isset($row['first_name']) && trim($row['first_name'])) ? trim($row['first_name']) : '') .
					((isset($row['middle_name']) && trim($row['middle_name'])) ? ' ' . trim($row['middle_name']) : '') .
					((isset($row['last_name']) && trim($row['last_name'])) ? ' ' . trim($row['last_name']) : '');
				$name = trim($name);
			}
			if ($name == '') {
				$name = 'id(' . $row['uid'] . ')';
			}
		}

		$this->tmpTcaMarkers = NULL; // reset function replaceVariables
		$otherLabelsList = $this->getOtherLabelsList();

		if (isset($conf['extraLabels']) && $conf['extraLabels'] != '') {
			$otherLabelsList .= ',' . $conf['extraLabels'];
		}
		$otherLabels = t3lib_div::trimExplode(',', $otherLabelsList, 1);
		$dataArray = array();
		$dataArray['row'] = $row;
		$this->setReplaceData($dataArray);
		$username = ($row['username'] != '' ? $row['username'] : $row['email']);

		$genderLabelArray = array();
		$vDear = 'v_dear';
		if ($row['gender'] == '0' || $row['gender'] == 'm') {
			$vDear = 'v_dear_male';
		} else if ($row['gender'] == '1' || $row['gender'] == 'f') {
			$vDear = 'v_dear_female';
		}
		$genderLabelArray['v_dear'] = $vDear;
		foreach($otherLabels as $value) {
			if (isset($genderLabelArray[$value])) {
				$labelName = $genderLabelArray[$value];
			} else {
				$labelName = $value;
			}
			$langText = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate($labelName, $this->extensionName);
			$label = sprintf(
				$langText,
				$this->controlData->getPidTitle(),
				htmlspecialchars($username),
				htmlspecialchars($name),
				htmlspecialchars($row['email']),
					// No clear-text password
				''
			);
			$label = preg_replace_callback('/{([a-z_]+):([a-zA-Z0-9_]+)}/', array($this, 'replaceVariables'), $label);
			$markerkey = $cObj->caseshift($value, 'upper');
			$markerArray['###LABEL_' . $markerkey . '###'] = $label;
		}
	}


	public function setRow ($row) {
		$this->row = $row;
	}


	public function getRow ($row) {
		return $this->row;
	}


	/**
	* Generates the URL markers
	*
	* @param string auth code
	* @return void
	*/
	protected function generateURLMarkers (
		$backUrl,
		$uid,
		$token,
		$theTable,
		$extKey,
		$prefixId
	) {
		$markerArray = array();
		$vars = array();
		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview';
		$unsetVars = t3lib_div::trimExplode(',', $unsetVarsList);
		$unsetVars['cmd'] = 'cmd';
		$unsetVarsAll = $unsetVars;
		$unsetVarsAll[] = 'token';
		$formUrl = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $GLOBALS['TSFE']->id . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVarsAll);

		unset($unsetVars['cmd']);
		$markerArray['###FORM_URL###'] = $formUrl;
		$form = $this->pibaseObj->pi_getClassName($theTable . '_form');

		$markerArray['###FORM_NAME###'] = $form; // $this->conf['formName'];

		$ac = $this->controlData->getFeUserData('aC');
		if ($ac) {
			$vars['aC'] = $ac;
		}
		$vars['cmd'] = $this->controlData->getCmd();
		$vars['token'] = $token;
		$vars['backURL'] = rawurlencode($formUrl);
		$vars['cmd'] = 'delete';
		$vars['rU'] = $uid;
		$vars['preview'] = '1';

		$markerArray['###DELETE_URL###'] = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $this->controlData->getPid('edit') . ',' . $GLOBALS['TSFE']->type, $vars);

		$vars['cmd'] = 'create';

		$unsetVars[] = 'regHash';
		$url = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $this->controlData->getPid('register') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);
		$markerArray['###REGISTER_URL###'] = $url;

		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,doNotSave,preview';
		$unsetVars = t3lib_div::trimExplode(',', $unsetVarsList);

		$vars['cmd'] = 'login';
		$markerArray['###LOGIN_FORM###'] = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $this->controlData->getPid('login') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);

		$vars['cmd'] = 'infomail';
		$markerArray['###INFOMAIL_URL###'] = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $this->controlData->getPid('infomail') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);

		$vars['cmd'] = 'edit';

		$markerArray['###EDIT_URL###'] = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $this->controlData->getPid('edit') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);
		$markerArray['###THE_PID###'] = $this->controlData->getPid();
		$markerArray['###THE_PID_TITLE###'] = $this->controlData->getPidTitle();
		$markerArray['###BACK_URL###'] = $backUrl;
		$markerArray['###SITE_NAME###'] = $this->conf['email.']['fromName'];
		$markerArray['###SITE_URL###'] = $this->controlData->getSiteUrl();
		$markerArray['###SITE_WWW###'] = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
		$markerArray['###SITE_EMAIL###'] = $this->conf['email.']['from'];
			// Set the url to the terms and conditions
		if ($this->conf['terms.']['url']) {
			$termsUrlParam = $this->conf['terms.']['url'];
		} else {
			$termsUrlParam = ($this->conf['terms.']['file'] ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['terms.']['file']) : '');
		}
		$markerArray['###TERMS_URL###'] = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $termsUrlParam, array(), array(), false);
		return $markerArray;
	}


	/**
	 * Generates the form URL markers
	 *
	 * @param string $prefixId
	 * @return void
	 */
	protected function generateFormURLMarkers($prefixId)
	{
		$commandArray = array('register', 'edit', 'delete', 'confirm', 'login');
		$markerArray = array();
		$vars = array();
		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview';
		$unsetVars = t3lib_div::trimExplode(',', $unsetVarsList);
		$unsetVars['cmd'] = 'cmd';
		$unsetVarsAll = $unsetVars;
		$unsetVarsAll[] = 'token';
		$commandPidArray = array();

		foreach ($commandArray as $command) {
			$upperCommand = strtoupper($command);
			$pid = $this->conf[$command . 'PID'];
			if (!$pid) {
				$pid = $GLOBALS['TSFE']->id;
			}
			$formUrl = \SJBR\SrFeuserRegister\Utility\UrlUtility::get($prefixId, '', $pid . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVarsAll);
			$markerArray['###FORM_' . $upperCommand . '_URL###'] = $formUrl;
		}
		return $markerArray;
	}


	public function setUrlMarkerArray ($markerArray) {
		$this->urlMarkerArray = $markerArray;
	}

	public function getUrlMarkerArray () {
		return $this->urlMarkerArray;
	}

	/**
	* Adds URL markers to a $markerArray
	*
	* @param array  $markerArray: the input marker array
	* @param string auth code
	* @return void
	*/
	public function addGeneralHiddenFieldsMarkers (
		&$markerArray,
		$cmd,
		$token
	) {
		$localMarkerArray = array();
		$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');
		$authCode = $authObj->getAuthCode();

		$backUrl = $this->controlData->getBackURL();
		$extKey = $this->controlData->getExtKey();
		$prefixId = $this->controlData->getPrefixId();

		$localMarkerArray['###HIDDENFIELDS###'] = $markerArray['###HIDDENFIELDS###'] . ($cmd ? '<input type="hidden" name="' . $prefixId . '[cmd]" value="' . $cmd . '" />':'');
		$localMarkerArray['###HIDDENFIELDS###'] .= chr(10) . ($authCode?'<input type="hidden" name="' . $prefixId . '[aC]" value="' . $authCode . '" />':'');
		$localMarkerArray['###HIDDENFIELDS###'] .= chr(10) . ($backUrl?'<input type="hidden" name="' . $prefixId . '[backURL]" value="' . htmlspecialchars($backUrl) . '" />':'');
		$this->addFormToken(
			$localMarkerArray,
			$token,
			$extKey,
			$prefixId
		);

		$markerArray = array_merge($markerArray, $localMarkerArray);
	}

	/**
	* Adds Static Info markers to a marker array
	*
	* @param array  $markerArray: the input marker array
	* @param array  $row: the table record
	* @return void
	*/
	public function addStaticInfoMarkers (
		&$markerArray,
		$prefixId,
		$row = '',
		$viewOnly = FALSE
	) {
		if (!$markerArray) {
			$markerArray = $this->getArray();
		}

		if (is_object($this->staticInfo)) {
			$cmd = $this->controlData->getCmd();
			$theTable = $this->controlData->getTable();
			if ($this->controlData->getMode() == MODE_PREVIEW || $viewOnly) {
				$markerArray['###FIELD_static_info_country###'] = $this->staticInfo->getStaticInfoName('COUNTRIES', is_array($row)?$row['static_info_country']:'');
				$markerArray['###FIELD_zone###'] = $this->staticInfo->getStaticInfoName('SUBDIVISIONS', is_array($row)?$row['zone']:'', is_array($row)?$row['static_info_country']:'');
				if (!$markerArray['###FIELD_zone###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE['.$theTable.'][zone]" value="" />';
				}
				$markerArray['###FIELD_language###'] = $this->staticInfo->getStaticInfoName('LANGUAGES',  is_array($row) ? $row['language'] : '');
			} else {
				$idCountry = $this->pibaseObj->pi_getClassName('static_info_country');
				$titleCountry = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'static_info_country', $this->extensionName);
				$idZone = $this->pibaseObj->pi_getClassName('zone');
				$titleZone = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'zone', $this->extensionName);
				$idLanguage = $this->pibaseObj->pi_getClassName('language');
				$titleLanguage = \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'language', $this->extensionName);
				$selected = (is_array($row) && isset($row['static_info_country']) ? $row['static_info_country'] : array());
				$where = '';
				if (isset($this->conf['where.']) && is_array($this->conf['where.'])) {
					$where = $this->conf['where.']['static_countries'];
				}
				$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfo->buildStaticInfoSelector(
					'COUNTRIES',
					'FE[' . $theTable . ']' . '[static_info_country]',
					'',
					$selected,
					'',
					$this->conf['onChangeCountryAttribute'],
					$idCountry,
					$titleCountry,
					$where,
					'',
					$this->conf['useLocalCountry']
				);
				$where = '';
				if (isset($this->conf['where.']) && is_array($this->conf['where.'])) {
					$where = $this->conf['where.']['static_country_zones'];
				}
				$markerArray['###SELECTOR_ZONE###'] =
					$this->staticInfo->buildStaticInfoSelector(
						'SUBDIVISIONS',
						'FE[' . $theTable . ']' . '[zone]',
						'',
						is_array($row) ? $row['zone'] : '',
						is_array($row) ? $row['static_info_country'] : '',
						'',
						$idZone,
						$titleZone,
						$where
					);
				if (!$markerArray['###SELECTOR_ZONE###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $theTable . '][zone]" value="" />';
				}

				$where = '';
				if (isset($this->conf['where.']) && is_array($this->conf['where.'])) {
					$where = $this->conf['where.']['static_languages'];
				}

				$markerArray['###SELECTOR_LANGUAGE###'] =
					$this->staticInfo->buildStaticInfoSelector(
						'LANGUAGES',
						'FE[' . $theTable . ']' . '[language]',
						'',
						is_array($row) ? $row['language'] : '',
						'',
						'',
						$idLanguage,
						$titleLanguage,
						$where
					);
			}
		}
	}

	/**
	 * Adds password transmission markers
	 *
	 * @param array  $markerArray: the input marker array
	 * @param boolean $usePassword: whether the password field is configured	
	 * @param boolean $usePasswordAgain: whether the password again field is configured
	 * @return void
	 */
	public function addPasswordTransmissionMarkers(&$markerArray, $usePassword, $usePasswordAgain)
	{
		if (!$markerArray) {
 			$markerArray = $this->getArray();
 		}
 		if ($usePassword) {
 			\SJBR\SrFeuserRegister\Security\TransmissionSecurity::getMarkers($markerArray, $usePasswordAgain);
 		}
	}

	/**
	* Builds a file uploader
	*
	* @param string  $theField: the field name
	* @param array  $config: the field TCA config
	* @param array  $filenames: array of uploaded file names
	* @param string  $prefix: the field name prefix
	* @return string  generated HTML uploading tags
	*/
	public function buildFileUploader (
		$theField,
		$config,
		$cmd,
		$cmdKey,
		$filenameArray,
		$prefixId,
		$viewOnly = FALSE,
		$activity = '',
		$bHtml = TRUE
	) {
		$HTMLContent = '';
		$size = $config['maxitems'];
		$cmdParts = preg_split('/\[|\]/', $this->conf[$cmdKey . '.']['evalValues.'][$theField]);
		if(!empty($cmdParts[1])) {
			$size = min($size, intval($cmdParts[1]));
		}
		$size = $size ? $size : 1;
		$number = $size - sizeof($filenameArray);
		$dir = $config['uploadfolder'];

		if ($viewOnly) {
			for ($i = 0; $i < sizeof($filenameArray); $i++) {
				$HTMLContent .= $filenameArray[$i];
				if ($activity == 'email') {
					if ($bHtml)	{
						$HTMLContent .= '<br />';
					} else {
						$HTMLContent .= chr(13) . chr(10);
					}
				} else if ($bHtml) {
					$HTMLContent .= '<a href="' . $dir . '/' . $filenameArray[$i] . '"' .
					$this->pibaseObj->pi_classParam('file-view', '') .
					' target="_blank" title="' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('file_view', $this->extensionName) . '">' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('file_view', $this->extensionName) . '</a><br />';
				}
			}
		} else {
			for($i = 0; $i < sizeof($filenameArray); $i++) {
				$HTMLContent .=
					$filenameArray[$i] . '<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($this->conf['icon_delete']) . '" name="' . $prefixId . '[' . $theField . '][' . $i . '][submit_delete]" value="1" title="' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('icon_delete', $this->extensionName) . '" alt="' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('icon_delete', $this->extensionName) . '"' .
					$this->pibaseObj->pi_classParam('delete-view', '') .
					' onclick=\'if(confirm("' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('confirm_file_delete', $this->extensionName) . '")) return true; else return false;\' />'
					. '<a href="' . $dir . '/' . $filenameArray[$i] . '" ' .
					$this->pibaseObj->pi_classParam('file-view', '') .
					' target="_blank" title="' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('file_view', $this->extensionName) . '">' .
					\SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('file_view', $this->extensionName) . '</a><br />';
				$HTMLContent .= '<input type="hidden" name="' . $prefixId . '[' . $theField . '][' . $i . '][name]' . '" value="' . $filenameArray[$i] . '" />';
			}
			for ($i = sizeof($filenameArray); $i < $number + sizeof($filenameArray); $i++) {
				$HTMLContent .= '<input id="' .
				$this->pibaseObj->pi_getClassName($theField) .
				'-' . ($i-sizeof($filenameArray)) . '" name="' . $prefixId . '[' . $theField . '][' . $i . ']" title="' . \SJBR\SrFeuserRegister\Utility\LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'image', $this->extensionName) . '" size="40" type="file" ' .
				$this->pibaseObj->pi_classParam('uploader-view', '') .
				' /><br />';
			}
		}
		return $HTMLContent;
	}


	/**
	* Adds uploading markers to a marker array
	*
	* @param string  $theField: the field name
	* @param array  $markerArray: the input marker array
	* @param array  $dataArray: the record array
	* @return void
	*/
	public function addFileUploadMarkers (
		$theTable,
		$theField,
		$fieldConfig,
		&$markerArray,
		$cmd,
		$cmdKey,
		$dataArray = array(),
		$viewOnly = FALSE,
		$activity = '',
		$bHtml = TRUE
	) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$filenameArray = array();

		if ($dataArray[$theField]) {
			$filenameArray = $dataArray[$theField];
		}

		if ($viewOnly) {
			$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] =
				$this->buildFileUploader(
					$theField,
					$fieldConfig['config'],
					$cmd,
					$cmdKey,
					$filenameArray,
					'FE[' . $theTable . ']',
					TRUE,
					$activity,
					$bHtml
				);
		} else {
			$markerArray['###UPLOAD_' . $theField . '###'] =
				$this->buildFileUploader(
					$theField,
					$fieldConfig['config'],
					$cmd,
					$cmdKey,
					$filenameArray,
					'FE[' . $theTable . ']',
					FALSE,
					$activity,
					$bHtml
				);
			$max_size = $fieldConfig['config']['max_size'] * 1024;
			$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="MAX_FILE_SIZE" value="' . $max_size . '" />';
		}
	}

	/**
	* Inserts a token for the form and stores it
	*
	* @param array  $markerArray: the token is added to the '###HIDDENFIELDS###' marker
	*/
	public function addFormToken (
		&$markerArray,
		$token,
		$extKey,
		$prefixId
	) {
		$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId . '[token]" value="' . $token . '" />';
	}


	public function addHiddenFieldsMarkers (
		&$markerArray,
		$theTable,
		$extKey,
		$prefixId,
		$cmdKey,
		$mode,
		$token,
		$bUseEmailAsUsername,
		$cmdKeyFields,
		$dataArray = array()
	) {
		if (!$markerArray) {
			$markerArray = $this->getArray();
		}

		if ($this->conf[$cmdKey.'.']['preview'] && $mode != MODE_PREVIEW) {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId .  '[preview]" value="1" />';
			if (
				$theTable == 'fe_users' &&
				$cmdKey == 'edit' &&
				$bUseEmailAsUsername
			) {
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE[' . $theTable . '][username]" value="' . htmlspecialchars($dataArray['username']) . '" />';
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE[' . $theTable . '][email]" value="' . htmlspecialchars($dataArray['email']) . '" />';
			}
		}
		$fieldArray = t3lib_div::trimExplode(',', $cmdKeyFields, 1);

		if ($mode == MODE_PREVIEW) {
			$fieldArray = array_diff($fieldArray, array('hidden', 'disable'));

			if ($theTable == 'fe_users') {
				if ($this->conf[$cmdKey . '.']['useEmailAsUsername'] || $this->conf[$cmdKey . '.']['generateUsername']) {
					$fieldArray = array_merge($fieldArray, array('username'));
				}
				if ($this->conf[$cmdKey . '.']['useEmailAsUsername']) {
					$fieldArray = array_merge($fieldArray, array('email'));
				}
				if ($cmdKey === 'edit' && !in_array('email', $fieldArray) && !in_array('username', $fieldArray)) {
					$fieldArray = array_merge($fieldArray, array('username'));
				}
			}
			$fields = implode(',', $fieldArray);
			$fields = $this->controlData->getOpenFields($fields);
			$fieldArray = explode(',', $fields);

			foreach ($fieldArray as $theField) {
				$value = $dataArray[$theField];
				if (is_array($value)) {
					$value = implode (',', $value);
				} else {
					$value = htmlspecialchars($dataArray[$theField]);
				}
				$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE[' . $theTable . '][' . $theField . ']" value="' . $value . '" />';
			}
		} else if (
				($theTable == 'fe_users') &&
				($cmdKey == 'edit' || $cmdKey == 'password') &&
				!in_array('email', $fieldArray) &&
				!in_array('username', $fieldArray)
			) {
				// Password change form probably contains neither email nor username
			$theField = 'username';
			$value = htmlspecialchars($dataArray[$theField]);
			$markerArray['###HIDDENFIELDS###'] .= LF . '<input type="hidden" name="FE[' . $theTable . '][' . $theField . ']" value="' . $value . '" />';
		}

		$this->addFormToken(
			$markerArray,
			$token,
			$extKey,
			$prefixId
		);
	}


	/**
	* Removes irrelevant Static Info subparts (zone selection when the country has no zone)
	*
	* @param string  $templateCode: the input template
	* @param array  $markerArray: the marker array
	* @return string  the output template
	*/
	public function removeStaticInfoSubparts (
		$templateCode,
		$markerArray,
		$viewOnly = FALSE
	) {
		$cObj = t3lib_div::makeInstance('tslib_cObj');

		if (!$markerArray) {
			$markerArray = $this->getArray();
		}

		if ($this->controlData->getMode() == MODE_PREVIEW || $viewOnly) {
			if (!$markerArray['###FIELD_zone###']) {
				return $cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
			}
		} else {
			if (!$markerArray['###SELECTOR_ZONE###']) {
				return $cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
			}
		}
		return $templateCode;
	}


	/**
	 * Adds elements to the input $markContentArray based on the values from the fields from $fieldList found in $row
	 *
	 * @param	array		Array with key/values being marker-strings/substitution values.
	 * @param	array		An array with keys found in the $fieldList (typically a record) which values should be moved to the $markContentArray
	 * @param	string		A list of fields from the $row array to add to the $markContentArray array. If empty all fields from $row will be added (unless they are integers)
	 * @param	boolean		If set, all values added to $markContentArray will be nl2br()'ed
	 * @param	string		Prefix string to the fieldname before it is added as a key in the $markContentArray. Notice that the keys added to the $markContentArray always start and end with "###"
	 * @param	boolean		If set, all values are passed through htmlspecialchars() - RECOMMENDED to avoid most obvious XSS and maintain XHTML compliance.
	 * @return	array		The modified $markContentArray
	 */
	public function fillInMarkerArray (
		&$markerArray,
		$row,
		$securedArray,
		$fieldList = '',
		$nl2br = TRUE,
		$prefix = 'FIELD_',
		$HSC = TRUE
	) {
		if (is_array($securedArray)) {
			foreach ($securedArray as $field => $value) {
				$row[$field] = $securedArray[$field];
			}
		}

		if ($fieldList)	{
			$fArr = t3lib_div::trimExplode(',', $fieldList, 1);
			foreach($fArr as $field) {
				$markerArray['###' . $prefix . $field . '###'] = $nl2br ? nl2br($row[$field]) : $row[$field];
			}
		} else {
			if (is_array($row)) {
				foreach($row as $field => $value) {
					$bFieldIsInt = t3lib_utility_Math::canBeInterpretedAsInteger($field);
					if (!$bFieldIsInt) {
						if (is_array($value)) {
							$value = implode(',', $value);
						}
						if ($HSC) {
							$value = htmlspecialchars($value);
						}
						$markerArray['###' . $prefix . $field . '###'] = $nl2br ? nl2br($value) : $value;
					}
				}
			}
		}

		// Add global markers
		$extKey = $this->controlData->getExtKey();
		$prefixId = $this->controlData->getPrefixId();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'] as $classRef) {
				$hookObj= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($classRef);
				if (method_exists($hookObj, 'addGlobalMarkers')) {
					$hookObj->addGlobalMarkers($markerArray, $this);
				}
			}
		}
		// Add captcha markers
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['captcha'] as $classRef) {
				$hookObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($classRef);
				$hookObj->addGlobalMarkers($markerArray, $this);
			}
		}
		return $markerArray;
	}
}