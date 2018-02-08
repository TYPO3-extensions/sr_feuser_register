<?php
namespace SJBR\SrFeuserRegister\View;

/*
 *  Copyright notice
 *
 *  (c) 2007-2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Captcha\CaptchaManager;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Security\TransmissionSecurity;
use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\Utility\UrlUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\StaticInfoTables\PiBaseApi;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Marker functions
 */
class Marker
{

	/**
	 * Subpart prefix and suffix constants
	 */
	const INFOMAIL_PREFIX = 'INFOMAIL_';
	const PREVIEW_SUFFIX = '_PREVIEW';
	const SAVED_SUFFIX = '_SAVED';
	const SETFIXED_PREFIX = 'SETFIXED_';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extensionKey;

	/**
	 *  Extension name
	 *
	 * @var string Extension name
	 */
	protected $extensionName;

	/**
	 * Prefix used for CSS classes and variables
	 *
	 * @var string
	 */
	protected $prefixId;

	/**
	 * The table being used
	 *
	 * @var string
	 */
	protected $theTable;

	/**
	 * The plugin configuration
	 *
	 * @var array
	 */
	protected $conf;

	/**
	 * Content object
	 *
	 * @var ContentObjectRenderer
	 */
	protected $cObj;

	/**
	 * The request parameters object
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * The token to use for next inteaction
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * The Static Info object
	 *
	 * @var \SJBR\StaticInfoTables\PiBaseApi
	 */
	protected $staticInfoObj = null;

	/**
	 * The usergroup hook object
	 *
	 */
	protected $userGroupObj = null;

	/**
	 * List of button label names
	 *
	 * @var string
	 */
	protected $buttonLabelsList = '';

	/**
	 * List of other label names
	 *
	 * @var string
	 */
	protected $otherLabelsList = '';

	/**
	 * The template html code
	 *
	 * @var string
	 */
	protected $templateCode;

	/**
	 * Marker array
	 *
	 * @var array
	 */
	protected $markerArray = array();

	/**
	 * Url marker array
	 *
	 * @var array
	 */
	protected $urlMarkerArray = array();

	/**
	 * Marker-based template service
	 *
	 * @var \TYPO3\CMS\Core\Service\MarkerBasedTemplateService
	 */
	protected $markerBasedTemplateService = null;

	public $previewLabel;

	/**
	 * Temporary array of data
	 */
	public $dataArray;

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param Parameters $parameters: the request parameters object
	 * @param string $buttonLabelsList: a list of button label names
	 * @param string $otherLabelsList: a list of other label names
	 * @return void
	 */
	public function __construct(
		$extensionKey,
		$prefixId,
		$theTable,
		array $conf,
		Parameters $parameters,
		$buttonLabelsList,
		$otherLabelsList
	){
	 	$this->extensionKey = $extensionKey;
	 	$this->extensionName = GeneralUtility::underscoredToUpperCamelCase($this->extensionKey);
	 	$this->prefixId = $prefixId;
	 	$this->theTable = $theTable;
	 	$this->conf = $conf;
	 	$this->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
	 	$this->parameters = $parameters;
	 	$this->token = SessionData::readToken($this->extensionKey);
	 	// Static Info object
		if (ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$this->staticInfoObj = GeneralUtility::makeInstance(PiBaseApi::class);
			if ($this->staticInfoObj->needsInit()) {
				$this->staticInfoObj->init();
			}
		}
		// Marker-based service
		$this->markerBasedTemplateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
		// Usergroup hook object
		if ($this->theTable === 'fe_users') {
			$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable]['usergroup']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId][$this->theTable]['usergroup'] : array();
			foreach ($hookClassArray as $classRef) {
				$this->userGroupObj = GeneralUtility::makeInstance($classRef);
				if (is_object($this->userGroupObj)) {
					break;
				}
			}
		}
		$this->setButtonLabelsList($buttonLabelsList);
		$this->setOtherLabelsList($otherLabelsList);
	}

	/**
	 * Set the list of button labels
	 *
	 * @param string list of button labels
	 * @return void
	 */
	protected function setButtonLabelsList($buttonLabelsList)
	{
		if ($buttonLabelsList) {
			$this->buttonLabelsList = $buttonLabelsList;
		} else {
			$this->buttonLabelsList = 'register,confirm_register,send_invitation,send_invitation_now,back_to_form,update,confirm_update,enter,confirm_delete,cancel_delete,update_and_more,password_forgotten';
		}
	}

	/**
	 * Get the list of button labels
	 *
	 * @return string list of button labels
	 */
	public function getButtonLabelsList()
	{
		return $this->buttonLabelsList;
	}

	/**
	 * Set the list of other labels
	 *
	 * @param string list of other labels
	 * @return void
	 */
	protected function setOtherLabelsList($otherLabelsList)
	{
		$this->otherLabelsList = 'yes,no,new_password,password_again,tooltip_password_again,tooltip_invitation_password_again,click_here_to_register,tooltip_click_here_to_register,click_here_to_edit,tooltip_click_here_to_edit,click_here_to_delete,tooltip_click_here_to_delete,click_here_to_see_terms,tooltip_click_here_to_see_terms'
			. ',copy_paste_link,enter_account_info,enter_invitation_account_info,required_info_notice,excuse_us'
			. ',tooltip_login_username,tooltip_login_password'
			. ',invalidToken'
			. ',registration_problem,registration_sorry,registration_clicked_twice,registration_help,kind_regards,kind_regards_cre,kind_regards_del,kind_regards_ini,kind_regards_inv,kind_regards_upd'
			. ',v_dear,v_verify_before_create,v_verify_invitation_before_create,v_verify_before_update,v_really_wish_to_delete,v_edit_your_account'.
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
		if ($otherLabelsList) {
				$this->otherLabelsList .= ',' . $otherLabelsList;
				$this->otherLabelsList = GeneralUtility::uniqueList($this->otherLabelsList);
		}
	}

	/**
	 * Get the list of other labels
	 *
	 * @return string list of other labels
	 */
	public function getOtherLabelsList()
	{
		return $this->otherLabelsList;
	}

	/**
	 * Set the marker array
	 *
	 * @param array $markerArray
	 * @return void
	 */
	protected function setMarkerArray(array $markerArray)
	{
		$this->markerArray = $markerArray;
	}

	/**
	 * Gets the template html code
	 *
	 * @return string the html code
	 */
	public function getTemplateCode()
	{
		if (empty($this->templateCode)) {
			$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);
		}
		return $this->templateCode;
	}

	/**
	 * Get the marker
	 *
	 * @return array the marker array
	 */
	public function getMarkerArray()
	{
		return $this->markerArray;
	}

	public function getPreviewLabel()
	{
		return $this->previewLabel;
	}

	public function setPreviewLabel($mode)
	{
		$this->previewLabel = $mode === AbstractView::MODE_PREVIEW ? '_PREVIEW' : '';
	}


	// enables the usage of {data:<field>}, {tca:<field>} and {meta:<stuff>} in the label markers
	public function replaceVariables($matches) {
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
					$cmd = $this->parameters->getCmd();
					$cmdKey = $this->parameters->getCmdKey();
					$this->addTcaMarkers(
						$this->tmpTcaMarkers,
						$row,
						$this->origArray,
						$cmd,
						$cmdKey,
						true,
						'',
						false
					);
				}
				$rc = $this->tmpTcaMarkers['###TCA_INPUT_' . $matches[2] . '###'];
			break;
			case 'meta':
				if ($matches[2] == 'title') {
					$rc = $this->parameters->getPidTitle();
				}
			break;
		}
		if (is_array($rc)) {
			$rc = implode(',', $rc);
		}
		return $rc;
	}


	public function setReplaceData($data)
	{
		$this->dataArray['row'] = $data['row'];
	}


	public function getReplaceData()
	{
		return $this->dataArray;
	}


	/**
	 * Sets the error markers to 'no error'
	 *
	 * @param string command key
	 * @return void  all initialization done directly on array $this->dataArray
	 */
	public function setNoError($cmdKey)
	{
		$markerArray = $this->getMarkerArray();
		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			foreach($this->conf[$cmdKey . '.']['evalValues.'] as $theField => $theValue) {
				$this->markerArray['###EVAL_ERROR_FIELD_' . $theField . '###'] = '<!--no error-->';
			}
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Gets the field name needed for the name attribute of the input HTML tag.
	 *
	 * @param string name of the field
	 * @return string  FE[tablename][fieldname]  ... POST var to transmit the entries with the form
	 */
	public function getFieldName($theField)
	{
		if ($theField === 'password') {
			// See FrontendLoginFormRsaEncryption.js
			$fieldName = 'pass';
		} else {
			$fieldName = 'FE[' . $this->theTable . '][' . $theField . ']';
		}
		return $fieldName;
	}

	/**
	 * Adds language-dependant label markers
	 *
	 * @param array $row: the record array
	 * @param array $origArray: the original record array as stored in the database
	 * @param array $requiredArray: the required fields array
	 * @param string list of info fields
	 * @param string list of special fields
	 * @param bool $changesOnly
	 * @return void
	 */
	public function addLabelMarkers(array $row, array $origArray, array $securedArray, array $keepFields, array $requiredArray, $infoFields, $specialFieldList, $changesOnly = false) {
		$markerArray = $this->getMarkerArray();
		$tcaColumns = $GLOBALS['TCA'][$this->theTable]['columns'];
		$formUrlMarkerArray = $this->getUrlMarkerArray();
		$row = array_merge($row, $securedArray);

		// Data field labels
		$infoFieldArray = GeneralUtility::trimExplode(',', $infoFields, true);
		$charset = $GLOBALS['TSFE']->renderCharset ?: 'utf-8';
		$specialFieldArray = GeneralUtility::trimExplode(',', $specialFieldList, true);
		$infoFieldArray = array_merge($infoFieldArray, $specialFieldArray);
		$requiredArray = array_merge($requiredArray, $specialFieldArray);

		foreach ($infoFieldArray as $theField) {
			$markerkey = mb_strtoupper($theField, 'utf-8');
			// Determine whether the value of the field was changed
			$valueChanged = !isset($row[$theField]) && isset($origArray[$theField]);
			if (isset($row[$theField])) {
				if (isset($origArray[$theField])) {
					if (is_array($row[$theField]) && is_array($origArray[$theField])) {
						$valueChanged = count(array_diff($row[$theField], $origArray[$theField])) || count(array_diff($origArray[$theField], $row[$theField]));
					} else {
						$valueChanged = !isset($origArray[$theField]) || ($row[$theField] != $origArray[$theField]);
					}
				} else {
					$valueChanged = true;
				}
			}
			if (!$changesOnly || $valueChanged || in_array($theField, $keepFields)) {
				$label = LocalizationUtility::translate($this->theTable . '.' . $theField, $this->extensionName);
				if (empty($label)) {
					$label = LocalizationUtility::translate($theField, $this->extensionName);
				}
				$label = (empty($label) ? LocalizationUtility::translate($tcaColumns[$theField]['label'], $this->extensionName) : $label);
				$label = htmlspecialchars($label, ENT_QUOTES, $charset);
			} else {
				$label = '';
			}
			$markerArray['###LABEL_' . $markerkey . '###'] = $label;
			$markerArray['###TOOLTIP_' . $markerkey . '###'] = LocalizationUtility::translate('tooltip_' . $theField, $this->extensionName);
			$label = LocalizationUtility::translate('tooltip_invitation_' . $theField, $this->extensionName);
			$label = htmlspecialchars($label, ENT_QUOTES, $charset);
			$markerArray['###TOOLTIP_INVITATION_' . $markerkey . '###'] = $label;
			$colConfig = $tcaColumns[$theField]['config'];

			if ($colConfig['type'] === 'select' && $colConfig['items']) {
				$colContent = '';
				$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] = '';
				$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] = '';
				$markerArray['###POSTVARS_' . $markerkey . '###'] = '';
				if (isset($row[$theField])) {
					if (is_array($row[$theField])) {
						$fieldArray = $row[$theField];
					} else {
						$fieldArray = GeneralUtility::trimExplode(',', $row[$theField]);
					}
					foreach ($fieldArray as $key => $value) {
						$label = LocalizationUtility::translate($colConfig['items'][$value][0], $this->extensionName);
						$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] .= '- ' . $label . '<br />';
						$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] .= '- ' . $label . '<br />';
						$markerArray['###POSTVARS_' . $markerkey.'###'] .= chr(10) . '	<input type="hidden" name="FE[fe_users][' . $theField . '][' . $key . ']" value ="' . $value . '" />';
					}
				}
			} else if ($colConfig['type'] == 'check') {
				$markerArray['###FIELD_' . $markerkey . '_CHECKED###'] = ($row[$theField]) ? 'checked' : '';
				$markerArray['###LABEL_' . $markerkey . '_CHECKED###'] = ($row[$theField]) ? LocalizationUtility::translate('yes', $this->extensionName) : LocalizationUtility::translate('no', $this->extensionName);
			}

			if (in_array(trim($theField), $requiredArray)) {
				$markerArray['###REQUIRED_' . $markerkey . '###'] = $this->cObj->cObjGetSingle($this->conf['displayRequired'], $this->conf['displayRequired.'], $this->extensionKey);
				$key = 'missing_' . $theField;
				$label = LocalizationUtility::translate($key, $this->extensionName);
				if ($label == '') {
					$label = LocalizationUtility::translate('internal_no_text_found', $this->extensionName);
					$label = sprintf($label, $key);
				}
				$markerArray['###MISSING_' . $markerkey . '###'] = $label;
				$markerArray['###MISSING_INVITATION_' . $markerkey . '###'] = LocalizationUtility::translate('missing_invitation_' . $theField, $this->extensionName);
			} else {
				$markerArray['###REQUIRED_' . $markerkey . '###'] = '';
				$markerArray['###MISSING_' . $markerkey . '###'] = '';
				$markerArray['###MISSING_INVITATION_' . $markerkey . '###'] = '';
			}
			$markerArray['###NAME_' . $markerkey . '###'] = $this->getFieldName($theField);
		}
		$markerArray['###NAME_PASSWORD_AGAIN###'] = $this->getFieldName('password_again');
		$buttonLabels = GeneralUtility::trimExplode(',', $this->getButtonLabelsList(), true);

		foreach ($buttonLabels as $labelName) {
			if ($labelName) {
				$buttonKey = strtoupper($labelName);
				$markerArray['###LABEL_BUTTON_' . $buttonKey . '###'] = LocalizationUtility::translate('button_' . $labelName, $this->extensionName);
				$attributes = '';

				if (
					isset($this->conf['button.'])
					&& isset($this->conf['button.'][$buttonKey . '.'])
					&& isset($this->conf['button.'][$buttonKey . '.']['attribute.'])
				) {
					$attributesArray = array();
					foreach ($this->conf['button.'][$buttonKey . '.']['attribute.'] as $key => $value) {
						$attributesArray[] = $key . '="' . $value . '"';
					}
					$attributes = implode(' ', $attributesArray);
					$attributes = $this->substituteMarkerArray($attributes, $formUrlMarkerArray);
				}
				$markerArray['###ATTRIBUTE_BUTTON_' . $buttonKey . '###'] = $attributes;
			}
		}
		// Assemble the name to be substituted in the labels
		$name = '';
		if ($this->conf['salutation'] === 'informal' && !empty($row['first_name'])) {
			$name = $row['first_name'];
		} else {
			// Honour Address List (tt_address) configuration settings
			if ($this->theTable === 'tt_address' && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_address')) {
				$settings = \TYPO3\TtAddress\Utility\SettingsUtility::getSettings();
				$nameFormat = '';
				if ($settings->isStoreBackwardsCompatName() && !empty($row['name'])) {
					$name = $row['name'];
				} else {
					$nameFormat = $settings->getBackwardsCompatFormat();
					if (!empty($nameFormat)) {
						$name = sprintf($nameFormat, $row['first_name'], $row['middle_name'], $row['last_name']);
					}
				}
			}
			if (empty($name) && isset($row['name'])) {
				$name = trim($row['name']);
			}
			if (empty($name)) {
				$name = ((isset($row['first_name']) && trim($row['first_name'])) ? trim($row['first_name']) : '') .
					((isset($row['middle_name']) && trim($row['middle_name'])) ? ' ' . trim($row['middle_name']) : '') .
					((isset($row['last_name']) && trim($row['last_name'])) ? ' ' . trim($row['last_name']) : '');
				$name = trim($name);
			}
			if (empty($name)) {
				$name = 'id(' . $row['uid'] . ')';
			}
		}
		// Reset function replaceVariables
		$this->tmpTcaMarkers = null;
		$otherLabelsList = $this->getOtherLabelsList();

		if (isset($this->conf['extraLabels']) && $this->conf['extraLabels'] != '') {
			$otherLabelsList .= ',' . $this->conf['extraLabels'];
		}
		$otherLabels = GeneralUtility::trimExplode(',', $otherLabelsList, true);
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
		foreach ($otherLabels as $value) {
			if (isset($genderLabelArray[$value])) {
				$labelName = $genderLabelArray[$value];
			} else {
				$labelName = $value;
			}
			$langText = LocalizationUtility::translate($labelName, $this->extensionName);
			$label = sprintf($langText, $this->parameters->getPidTitle(), htmlspecialchars($username), htmlspecialchars($name), htmlspecialchars($row['email']), '');
			// $this->origArray is used by replaceVariables
			$this->origArray = $origArray;
			$label = preg_replace_callback('/{([a-z_]+):([a-zA-Z0-9_]+)}/', array($this, 'replaceVariables'), $label);
			$markerkey = mb_strtoupper($value, 'utf-8');
			$markerArray['###LABEL_' . $markerkey . '###'] = $label;
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Initializes the marker array
	 * Generates the URL markers
	 *
	 * @param string $uid of the current record
	 * @return void
	 */
	public function generateURLMarkers($uid = 0)
	{
		$vars = array();
		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview,countryChange,fileDelete,key';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);
		$unsetVars['cmd'] = 'cmd';
		$unsetVarsAll = $unsetVars;
		$unsetVarsAll[] = 'token';
		$formUrl = UrlUtility::get($this->prefixId, '', $GLOBALS['TSFE']->id . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVarsAll);
		$backUrl = $this->parameters->getBackURL();

		$markerArray['###CHARSET###'] = $GLOBALS['TSFE']->metaCharset ?: 'utf-8';
		$markerArray['###PREFIXID###'] = $this->prefixId;

		unset($unsetVars['cmd']);
		$markerArray['###FORM_URL###'] = $formUrl;
		$form = CssUtility::getClassName($this->prefixId, $this->theTable . '_form');
		$markerArray['###FORM_NAME###'] = $this->conf['formName'] ?: $form;

		$unsetVarsList = 'mode,pointer,sort,sword,submit,doNotSave,countryChange,fileDelete,key';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);	
		$ac = $this->parameters->getFeUserData('aC');
		if ($ac) {
			$vars['aC'] = $ac;
		}	
		$vars['token'] = $this->token;
		$vars['backURL'] = rawurlencode($formUrl);
		$vars['cmd'] = 'delete';
		$vars['rU'] = $uid;
		$vars['preview'] = '1';
		$markerArray['###DELETE_URL###'] = UrlUtility::get($this->prefixId, '', $this->parameters->getPid('edit') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);

		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview,countryChange,fileDelete,key,regHash';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);
		$vars['cmd'] = 'create';
		$url = UrlUtility::get($this->prefixId, '', $this->parameters->getPid('register') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);
		$markerArray['###REGISTER_URL###'] = $url;

		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,aC,sFK,doNotSave,preview,countryChange,fileDelete,key';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);
		$vars['cmd'] = 'login';
		$markerArray['###LOGIN_FORM###'] = UrlUtility::get($this->prefixId, '', $this->parameters->getPid('login') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);

		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,doNotSave,preview,countryChange,fileDelete,key';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);
		$vars['cmd'] = 'infomail';
		$markerArray['###INFOMAIL_URL###'] = UrlUtility::get($this->prefixId, '', $this->parameters->getPid('infomail') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);

		$vars['cmd'] = 'edit';
		$markerArray['###EDIT_URL###'] = UrlUtility::get($this->prefixId, '', $this->parameters->getPid('edit') . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);
		$markerArray['###THE_PID###'] = (int) $this->parameters->getPid();
		$markerArray['###THE_PID_TITLE###'] = $this->parameters->getPidTitle();
		$markerArray['###BACK_URL###'] = $backUrl;
		$markerArray['###SITE_NAME###'] = $this->conf['email.']['fromName'];
		$markerArray['###SITE_URL###'] = UrlUtility::getSiteUrl();
		$markerArray['###SITE_WWW###'] = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
		$markerArray['###SITE_EMAIL###'] = $this->conf['email.']['from'];
		// Set the url to the terms and conditions
		if ($this->conf['terms.']['url']) {
			$termsUrlParam = $this->conf['terms.']['url'];
		} else {
			$termsUrlParam = ($this->conf['terms.']['file'] ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['terms.']['file']) : '');
		}
		$markerArray['###TERMS_URL###'] = UrlUtility::get($this->prefixId, '', $termsUrlParam, array(), array(), false);

		$formUrlMarkerArray = $this->generateFormURLMarkers();
		$markerArray = array_merge($markerArray, $formUrlMarkerArray);	
		$this->setUrlMarkerArray($markerArray);
		$this->setMarkerArray(array_merge($this->getMarkerArray(), $markerArray));
	}

	/**
	 * Generates the form URL markers
	 *
	 * @return void
	 */
	protected function generateFormURLMarkers()
	{
		$commandArray = array('register', 'edit', 'delete', 'confirm', 'login');
		$markerArray = array();
		$vars = array();
		$unsetVarsList = 'mode,pointer,sort,sword,backURL,submit,rU,aC,sFK,doNotSave,preview,cmd,token,countryChange,fileDelete,key';
		$unsetVars = GeneralUtility::trimExplode(',', $unsetVarsList);
		$commandPidArray = array();
		foreach ($commandArray as $command) {
			$upperCommand = strtoupper($command);
			$pid = $this->conf[$command . 'PID'];
			if (!$pid) {
				$pid = $GLOBALS['TSFE']->id;
			}
			$formUrl = UrlUtility::get($this->prefixId, '', $pid . ',' . $GLOBALS['TSFE']->type, $vars, $unsetVars);
			$markerArray['###FORM_' . $upperCommand . '_URL###'] = $formUrl;
		}
		return $markerArray;
	}

	/**
	 * Add setfixed URLs marker
	 *
	 * @param array $setfixedUrls: pairs of key => url
	 * @return void
	 */
	public function addSetfixedUrlMarkers(array $setfixedUrls)
	{
		foreach ($setfixedUrls as $key => $url) {
			$marker = '###SETFIXED_' . mb_strtoupper($key, 'utf-8') . '_URL###';
			$isAbsoluteURL = ((strncmp($url, 'http://', 7) == 0) || (strncmp($url, 'https://', 8) == 0));
			$url = ($isAbsoluteURL ? '' : UrlUtility::getSiteUrl()) . ltrim($url, '/');
			$this->markerArray[$marker] = str_replace(array('[',']'), array('%5B', '%5D'), $url);
		}
	}

	/**
	 * Add back URL marker
	 *
	 * @param string $url: the back url
	 * @return void
	 */
	public function addBackUrlMarker($url)
	{
		$this->markerArray['###BACK_URL###'] = $url;
	}

	/**
	 * Set the Url marker array
	 *
	 * @param array $markerArray: a marker array
	 * @return void
	 */
	protected function setUrlMarkerArray(array $markerArray)
	{
		$this->urlMarkerArray = $markerArray;
	}

	/**
	 * Get the Url marker array
	 *
	 * @return array the Url marker array
	 */
	public function getUrlMarkerArray()
	{
		return $this->urlMarkerArray;
	}

	/**
	 * Remove password markers
	 *
	 * @return void
	 */
	public function removePasswordMarkers()
	{
		// Avoid cleartext password in HTML source
		$this->markerArray['###FIELD_password###'] = '';
		$this->markerArray['###FIELD_password_again###'] = '';
	}

	/**
	 * Add authCode marker
	 *
	 * @param string $row: a record
	 * @return void
	 */
	public function addAuthCodeMarker(array $row) {
		$this->markerArray['###SYS_AUTHCODE###'] = Authentication::authCode($row, $this->conf);
	}

	/**
	 * Add evaluation errors marker
	 *
	 * @param array $markerArray: evaluation errors markers
	 * @return void
	 */
	public function addEvalValuesMarkers(array $markerArray)
	{
		$this->markerArray = array_merge($this->markerArray, $markerArray);
	}

	/**
	 * Adds Static Info markers to a marker array
	 *
	 * @param array $row: the table record
	 * @return void
	 */
	public function addStaticInfoMarkers($row = array(), $viewOnly = false)
	{
		$markerArray = $this->getMarkerArray();
		if ($this->staticInfoObj !== null) {
			$cmd = $this->parameters->getCmd();
			if ($viewOnly) {
				$markerArray['###FIELD_static_info_country###'] = $this->staticInfoObj->getStaticInfoName('COUNTRIES', is_array($row) ? $row['static_info_country']:'');
				$markerArray['###FIELD_zone###'] = $this->staticInfoObj->getStaticInfoName('SUBDIVISIONS', is_array($row)?$row['zone']:'', is_array($row)?$row['static_info_country']:'');
				if (!$markerArray['###FIELD_zone###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][zone]" value="" />' . LF;
				}
				$markerArray['###FIELD_language###'] = $this->staticInfoObj->getStaticInfoName('LANGUAGES',  is_array($row) ? $row['language'] : '');
			} else {
				$idCountry = CssUtility::getClassName($this->prefixId, 'static_info_country');
				$titleCountry = LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'static_info_country', $this->extensionName);
				$idZone = CssUtility::getClassName($this->prefixId, 'zone');
				$titleZone = LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'zone', $this->extensionName);
				$idLanguage = CssUtility::getClassName($this->prefixId, 'language');
				$titleLanguage = LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'language', $this->extensionName);
				$selected = (is_array($row) && isset($row['static_info_country']) ? $row['static_info_country'] : array());
				$where = '';
				if (isset($this->conf['where.']) && is_array($this->conf['where.'])) {
					$where = $this->conf['where.']['static_countries'];
				}
				$markerArray['###SELECTOR_STATIC_INFO_COUNTRY###'] = $this->staticInfoObj->buildStaticInfoSelector(
					'COUNTRIES',
					'FE[' . $this->theTable . ']' . '[static_info_country]',
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
					$this->staticInfoObj->buildStaticInfoSelector(
						'SUBDIVISIONS',
						'FE[' . $this->theTable . ']' . '[zone]',
						'',
						is_array($row) ? $row['zone'] : '',
						is_array($row) ? $row['static_info_country'] : '',
						'',
						$idZone,
						$titleZone,
						$where
					);
				if (!$markerArray['###SELECTOR_ZONE###'] ) {
					$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][zone]" value="" />' . LF;
				}

				$where = '';
				if (isset($this->conf['where.']) && is_array($this->conf['where.'])) {
					$where = $this->conf['where.']['static_languages'];
				}

				$markerArray['###SELECTOR_LANGUAGE###'] =
					$this->staticInfoObj->buildStaticInfoSelector(
						'LANGUAGES',
						'FE[' . $this->theTable . ']' . '[language]',
						'',
						is_array($row) ? $row['language'] : '',
						'',
						'',
						$idLanguage,
						$titleLanguage,
						$where
					);
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[countryChange]" value="0" />' . LF;
			}
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Removes irrelevant Static Info subparts (zone selection when the country has no zone)
	 *
	 * @param string $templateCode: the input template
	 * @param boolean $viewOnly: whether the fields are presented for view only or for input/update
	 * @return string the output template
	 */
	public function removeStaticInfoSubparts($templateCode, $viewOnly = false) {
		$markerArray = $this->getMarkerArray();
		if ($viewOnly) {
			if (!$markerArray['###FIELD_zone###']) {
				return $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
			}
		} else {
			if (!$markerArray['###SELECTOR_ZONE###']) {
				return $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_zone###', '');
			}
		}
		return $templateCode;
	}

	/**
	 * Adds password transmission markers
	 *
	 * @param boolean $usePassword: whether the password field is configured	
	 * @param boolean $usePasswordAgain: whether the password again field is configured
	 * @return void
	 */
	public function addPasswordTransmissionMarkers($usePassword, $usePasswordAgain)
	{
		$markerArray = $this->getMarkerArray();
 		if ($usePassword) {
 			TransmissionSecurity::getMarkers($markerArray, $usePasswordAgain);
 		}
 		$this->setMarkerArray($markerArray);
	}

	/**
	 * Adds additional markers to a marker array by invoking hooks
	 *
	 * @param string $infoFields: the list of field names
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param array $dataArray: the record array
	 * @param bool $viewOnly: whether the fields are presented for view only or for input/update
	 * @return void
	 */
	public function addAdditionalMarkers($infoFields, $cmd, $cmdKey, $dataArray = array(), $viewOnly = false, $activity = '', $bHtml = true)
	{
		$markerArray = $this->getMarkerArray();
		$fieldArray = GeneralUtility::trimExplode(',', $infoFields, true);
		$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['model'] : array();
		foreach ($hookClassArray as $classRef) {
			$hookObj = GeneralUtility::makeInstance($classRef);
			if (is_object($hookObj) && method_exists($hookObj, 'addMarkers')) {
				foreach ($fieldArray as $theField) {
					$additionalMarkerArray = $hookObj->addMarkers($this->theTable, $theField, $cmd, $cmdKey, $dataArray, $viewOnly, $activity, $bHtml, $this->extensionName, $this->prefixId, $this->conf);
					$hiddenFieldsMarker = $markerArray['###HIDDENFIELDS###'];
					$markerArray = array_merge($markerArray, $additionalMarkerArray);
					$markerArray['###HIDDENFIELDS###'] = $hiddenFieldsMarker . $additionalMarkerArray['###HIDDENFIELDS###'];
				}
			}
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Add hidden fields to the marker array
	 *
	 * @param string $cmd: the command to be processed
	 * @param string $authCode: the authCode
	 * @param string $backUrl: the back URL
	 * @return void
	 */
	public function addGeneralHiddenFieldsMarkers($cmd, $authCode = '', $backUrl = '')
	{
		$markerArray = $this->getMarkerArray();
		$markerArray['###HIDDENFIELDS###'] .= ($cmd ? '<input type="hidden" name="' . $this->prefixId . '[cmd]" value="' . $cmd . '" />' . LF : '');
		$markerArray['###HIDDENFIELDS###'] .= ($authCode ? '<input type="hidden" name="' . $this->prefixId . '[aC]" value="' . $authCode . '" />' . LF : '');
		$markerArray['###HIDDENFIELDS###'] .= ($backUrl ? '<input type="hidden" name="' . $this->prefixId . '[backURL]" value="' . htmlspecialchars($backUrl) . '" />' . LF : '');
		$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[submit]" value="1" />' . LF;
		$this->addFormToken($markerArray, $this->prefixId);
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Inserts a token for the form and stores it
	 *
	 * @param array $markerArray: the token is added to the '###HIDDENFIELDS###' marker
	 */
	protected function addFormToken(array &$markerArray)
	{
		$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[token]" value="' . $this->token . '" />' . LF;
	}

	/**
	 * Add hidden fields markers specific to the edit form
	 *
	 * @param int $uid: uid of the current record
	 * @param string $authCode: the authcode
	 * @return void
	 */
	public function addEditFormHiddenFieldsMarkers($uid, $authCode, $cmd = 'edit', $pid = 0)
	{
		$markerArray = $this->getMarkerArray();
		$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][uid]" value="' . $uid . '" />' . LF;
		$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[cmd]" value="' . $cmd . '" />' . LF;
		$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[submit]" value="1" />' . LF;
		if ($pid) {
			$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][pid]" value="' . $pid . '" />' . LF;
		}
		$this->addFormToken($markerArray);
		// Deletion of user is allowed when authentified by authCode
		if ($this->theTable !== 'fe_users' || ($authCode && $cmd === 'delete')) {
			$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId . '[aC]" value="' . $authCode . '" />' . LF;
		}
		$this->setMarkerArray($markerArray);
	}

	public function addHiddenFieldsMarkers(
		$cmdKey,
		$mode,
		$bUseEmailAsUsername,
		$cmdKeyFields,
		$dataArray = array()
	) {
		$markerArray = $this->getMarkerArray();
		if ($this->conf[$cmdKey.'.']['preview'] && $mode !== AbstractView::MODE_PREVIEW) {
			$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $this->prefixId .  '[preview]" value="1" />' . LF;
			if ($this->theTable === 'fe_users' && $cmdKey === 'edit' && $bUseEmailAsUsername
			) {
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][username]" value="' . htmlspecialchars($dataArray['username']) . '" />' . LF;
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][email]" value="' . htmlspecialchars($dataArray['email']) . '" />' . LF;
			}
		}
		$fieldArray = GeneralUtility::trimExplode(',', $cmdKeyFields, true);

		if ($mode === AbstractView::MODE_PREVIEW) {
			$fieldArray = array_diff($fieldArray, array('hidden', 'disable'));

			if ($this->theTable === 'fe_users') {
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
			$fields = SecuredData::getOpenFields($fields);
			$fieldArray = explode(',', $fields);

			foreach ($fieldArray as $theField) {
				$value = $dataArray[$theField];
				if (is_array($value)) {
					$fieldConfig = $GLOBALS['TCA'][$this->theTable]['columns'][$theField]['config']; 
					if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
						$value = htmlspecialchars(serialize($value));
					} else {
						$value = implode(',', $value);
					}
				} else {
					$value = htmlspecialchars($dataArray[$theField]);
				}
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][' . $theField . ']" value="' . $value . '" />' . LF;
			}
		} else if (
				($this->theTable === 'fe_users')
				&& ($cmdKey === 'edit' || $cmdKey === 'password')
				&& !in_array('email', $fieldArray)
				&& !in_array('username', $fieldArray)
			) {
			// Password change form probably contains neither email nor username
			$theField = 'username';
			$value = htmlspecialchars($dataArray[$theField]);
			$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="FE[' . $this->theTable . '][' . $theField . ']" value="' . $value . '" />' . LF;
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Adds elements to the input $markContentArray based on the values from the fields from $fieldList found in $row
	 *
	 * @param array An array with keys found in the $fieldList (typically a record) which values should be moved to the $markContentArray
	 * @param string A list of fields from the $row array to add to the $markContentArray array. If empty all fields from $row will be added (unless they are integers)
	 * @param boolean If set, all values added to $markContentArray will be nl2br()'ed
	 * @param string Prefix string to the fieldname before it is added as a key in the $markContentArray. Notice that the keys added to the $markContentArray always start and end with "###"
	 * @param boolean If set, all values are passed through htmlspecialchars() - RECOMMENDED to avoid most obvious XSS and maintain XHTML compliance.
	 * @return array The modified $markContentArray
	 */
	public function fillInMarkerArray(array $row, array $securedArray, $fieldList = '', $nl2br = true, $prefix = 'FIELD_', $hsc = true) {
		$markerArray = $this->getMarkerArray();
		if (is_array($securedArray)) {
			foreach ($securedArray as $field => $value) {
				$row[$field] = $securedArray[$field];
			}
		}
		if (!empty($fieldList)) {
			$fieldArray = GeneralUtility::trimExplode(',', $fieldList, true);
			foreach ($fieldArray as $field) {
				$markerArray['###' . $prefix . $field . '###'] = $nl2br ? nl2br($row[$field]) : $row[$field];
			}
		} else {
			foreach ($row as $field => $value) {
				if (is_array($value)) {
					$value = implode(',', $value);
				}
				if ($hsc) {
					$value = htmlspecialchars($value);
				}
				$markerArray['###' . $prefix . $field . '###'] = $nl2br ? nl2br($value) : $value;
			}
		}
		// Add global markers
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'] as $classRef) {
				$hookObj= GeneralUtility::makeInstance($classRef);
				if (method_exists($hookObj, 'addGlobalMarkers')) {
					$hookObj->addGlobalMarkers($markerArray, $this, $this->parameters->getCmdKey(), $this->conf);
				}
			}
		}
		// Add captcha markers
		if (CaptchaManager::useCaptcha($this->parameters->getCmdKey(), $this->conf, $this->extensionKey)) {
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey]['captcha'] as $classRef) {
					$hookObj = GeneralUtility::makeInstance($classRef);
					$hookObj->addGlobalMarkers($markerArray, $this->parameters->getCmdKey(), $this->conf);
				}
			}
		}
		$this->setMarkerArray($markerArray);
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
	 * @param string $failure: list of field with errors
	 * @return string the template with susbstituted parts
	 */
	public function removeRequired(
		$templateCode,
		$failure,
		array $requiredArray,
		$fieldList,
		$specialFieldList,
		$cmdKey,
		$isPreview
	) {
		$includedFields = GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true);
		if ($isPreview && !in_array('username', $includedFields)) {
			$includedFields[] = 'username';
		}
		$infoFields = GeneralUtility::trimExplode(',', $fieldList, true);
		$specialFields = explode(',', $specialFieldList);
		if (is_array($specialFields) && count($specialFields)) {
			$infoFields = array_merge($infoFields, $specialFields);
		}
		if (!ExtensionManagementUtility::isLoaded('direct_mail')) {
			$infoFields = array_merge($infoFields, array('module_sys_dmail_category', 'module_sys_dmail_newsletter', 'module_sys_dmail_html'));
			$includedFields = array_diff($includedFields, array('module_sys_dmail_category', 'module_sys_dmail_newsletter'));
		}
		if (!CaptchaManager::useCaptcha($cmdKey, $this->conf, $this->extensionKey)) {
			$templateCode = $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_captcha_response###', '');
		}
		// Honour Address List (tt_address) configuration setting
		if ($this->theTable === 'tt_address' && ExtensionManagementUtility::isLoaded('tt_address')) {
			$settings = \TYPO3\TtAddress\Utility\SettingsUtility::getSettings();
				if (!$settings->isStoreBackwardsCompatName()) {
				$templateCode = $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_name###', '');
			}
		}
		foreach ($infoFields as $k => $theField) {
			// Remove field required subpart, if field is not missing
			if (in_array(trim($theField), $requiredArray)) {
				if (!GeneralUtility::inList($failure, $theField)) {
					$templateCode = $this->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_' . $theField . '###', '');
					$templateCode = $this->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_' . $theField . '###', '');
				}
			} else {
				// Remove field included subpart, if field is not included and is not in failure list
				if (!in_array(trim($theField), $includedFields) && !GeneralUtility::inList($failure, $theField)) {
					$templateCode = $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_' . $theField . '###', '');
				} else {
					$templateCode = $this->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_' . $theField . '###', '');
					if (!GeneralUtility::inList($failure, $theField)) {
						$templateCode = $this->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_' . $theField . '###', '');
					}
					if (is_array($this->conf['parseValues.']) && strstr($this->conf['parseValues.'][$theField], 'checkArray')) {
						$listOfCommands = GeneralUtility::trimExplode(',', $this->conf['parseValues.'][$theField], true);
						foreach ($listOfCommands as $cmd) {
							 // Enable parameters after each command enclosed in brackets [..].
							$cmdParts = preg_split('/\[|\]/', $cmd);
							$theCmd = trim($cmdParts[0]);
							switch ($theCmd) {
								case 'checkArray':
									$positions = GeneralUtility::trimExplode(';', $cmdParts[1]);
									for ($i = 0; $i < 10; $i++) {
										if (!in_array($i, $positions)) {
											$templateCode = $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_' . $theField . '_' . $i . '###', '');
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

	public function removeNonIncluded($templateCode, array $keepFields, $cmdKey) {
		$includedFields = GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true);
		foreach ($includedFields as $field) {
			if (!in_array($field, $keepFields)) {
				$templateCode = $this->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_' . $field . '###', '');
			}
		}
		return $templateCode;
	}

	/**
	 * Adds CSS styles marker to a marker array for substitution in an HTML email message
	 *
	 * @return void
	 */
	public function addCSSStyleMarkers()
	{
		$markerArray = $this->getMarkerArray();
		$markerArray['###CSS_STYLES###'] = '	/*<![CDATA[*/
';
		$fileResource = $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
		$markerArray['###CSS_STYLES###'] .= $fileResource;
		$markerArray['###CSS_STYLES###'] .= '
/*]]>*/';
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Add form element markers from the Table Configuration Array to a marker array
	 *
	 * @param array $row: the updated record
	 * @param array $origRow: the original record as before the updates
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param boolean $viewOnly: whether the fields are presented for view only or for input/update
	 * @param array $requiredFields: required fields names
	 * @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	 * @param boolean $bChangesOnly: whether only updated fields should be presented
	 * @param boolean $HSC: whether content should be htmlspecialchar'ed or not
	 * @return void
	 */
	public function addTcaMarkers(
		$row,
		$origRow,
		$cmd,
		$cmdKey,
		$viewOnly = false,
		array $requiredFields,
		$activity = '',
		$bChangesOnly = false,
		$HSC = true
	) {
		$markerArray = $this->getMarkerArray();
		$charset = $GLOBALS['TSFE']->renderCharset ? $GLOBALS['TSFE']->renderCharset : 'utf-8';
		$languageUid = (int) $GLOBALS['TSFE']->config['config']['sys_language_uid'];

		if ($bChangesOnly && is_array($origRow)) {
			$mrow = array();
			foreach ($origRow as $k => $v) {
				if ($v != $row[$k]) {
					$mrow[$k] = $row[$k];
				}
			}
			$mrow['uid'] = $row['uid'];
			$mrow['pid'] = $row['pid'];
			$mrow['tstamp'] = $row['tstamp'];
			$mrow['username'] = $row['username'];
		} else {
			$mrow = $row;
		}

		$fields = $this->conf[$cmdKey . '.']['fields'];

		if ($activity !== 'email') {
			$activity = $viewOnly ? 'preview' : 'input';
		}

		foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $colName => $colSettings) {
			if (GeneralUtility::inList($fields, $colName)) {
				$colConfig = $colSettings['config'];
				$colContent = '';
				if (!$bChangesOnly || isset($mrow[$colName])) {
					$type = $colConfig['type'];

					// check for a setup of wraps:
					$stdWrap = array();
					$bNotLast = false;
					$bStdWrap = false;
					// any item wraps set?
					if (
						is_array($this->conf[$type . '.']) &&
						is_array($this->conf[$type . '.'][$activity . '.']) &&
						is_array($this->conf[$type . '.'][$activity . '.'][$colName . '.']) &&
						is_array($this->conf[$type . '.'][$activity . '.'][$colName . '.']['item.'])
					) {
						$stdWrap = $this->conf[$type. '.'][$activity . '.'][$colName . '.']['item.'];
						$bStdWrap = true;
						if ($this->conf[$type . '.'][$activity . '.'][$colName . '.']['item.']['notLast']) {
							$bNotLast = true;
						}
					}
					$listWrap = array();
					$bListWrap = false;

					// any list wraps set?
					if (
						is_array($this->conf[$type . '.'])
						&& is_array($this->conf[$type . '.'][$activity.'.'])
						&& is_array($this->conf[$type . '.'][$activity . '.'][$colName . '.'])
						&& is_array($this->conf[$type . '.'][$activity . '.'][$colName . '.']['list.'])
					) {
						$listWrap = $this->conf[$type . '.'][$activity . '.'][$colName . '.']['list.'];
						$bListWrap = true;
					} else {
						$listWrap['wrap'] = '<ul class="tx-srfeuserregister-multiple-checked-values">|</ul>';
					}
					if ($viewOnly) {
						// Configure preview or email based on input type
						switch ($type) {
							//case 'input':
							case 'text':
								$colContent = ($HSC ? nl2br(htmlspecialchars($mrow[$colName], ENT_QUOTES, $charset)) : $mrow[$colName]);
								break;
							case 'check':
								if (is_array($colConfig['items'])) {

									if (!$bStdWrap) {
										$stdWrap['wrap'] = '<li>|</li>';
									}

									if (!$bListWrap) {
										$listWrap['wrap'] = '<ul class="tx-srfeuserregister-multiple-checked-values">|</ul>';
									}
									$bCheckedArray = array();
									foreach($colConfig['items'] as $key => $value) {
										$checked = ($mrow[$colName] & (1 << $key));
										if ($checked) {
											$bCheckedArray[$key] = true;
										}
									}

									$count = 0;
									$checkedCount = 0;
									foreach($colConfig['items'] as $key => $value) {
										$count++;
										$label = LocalizationUtility::translate($colConfig['items'][$key][0], $this->extensionName);
										if ($HSC) {
											$label =
												htmlspecialchars(
													$label,
													ENT_QUOTES,
													$charset
												);
										}
										$checked = ($bCheckedArray[$key]);

										if ($checked) {
											$checkedCount++;
											$label = ($checked ? $label : '');
											$colContent .= ((!$bNotLast || $checkedCount < count($bCheckedArray)) ?  $this->cObj->stdWrap($label,$stdWrap) : $label);
										}
									}
									$this->cObj->alternativeData = $colConfig['items'];
									$colContent = $this->cObj->stdWrap($colContent, $listWrap);
								} else {
									if ($mrow[$colName]) {
										$label = LocalizationUtility::translate('yes', $this->extensionName);
									} else {
										$label = LocalizationUtility::translate('no', $this->extensionName);
									}
									if ($HSC) {
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
									}
									$colContent = $label;
								}
								break;
							case 'radio':
								if ($mrow[$colName] !== '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',', $mrow[$colName]);
									if ($colConfig['itemsProcFunc']) {
										$itemArray = GeneralUtility::callUserFunction($colConfig['itemsProcFunc'], $colConfig, $this, '');
									}
									$itemArray = $colConfig['items'];
									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);
										if (!$bStdWrap) {
											$stdWrap['wrap'] = '| ';
										}
										for ($i = 0; $i < count($valuesArray); $i++) {
											$item = $itemKeyArray[$valuesArray[$i]][0] ?: $itemKeyArray[(int)$valuesArray[$i]][0];
											$label = LocalizationUtility::translate(substr(strrchr($item, ':'), 1), $this->extensionName);
											$label = $label ?: LocalizationUtility::translate($item, $this->extensionName);
											$label = $label ?: $item;
											if ($HSC) {
												$label = htmlspecialchars($label, ENT_QUOTES, $charset);
											}
											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $this->cObj->stdWrap($label, $stdWrap) : $label);
										}
									}
								}
								break;
							case 'select':
								if ($mrow[$colName] != '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',', $mrow[$colName]);
									if ($colConfig['itemsProcFunc']) {
										$itemArray = GeneralUtility::callUserFunction($colConfig['itemsProcFunc'], $colConfig, $this, '');
									}
									$itemArray = $colConfig['items'];
									if (!$bStdWrap) {
										$stdWrap['wrap'] = '|<br />';
									}
									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);
										for ($i = 0; $i < count($valuesArray); $i++) {
											$label = LocalizationUtility::translate(substr(strrchr($itemKeyArray[$valuesArray[$i]][0], ':'), 1), $this->extensionName);
											$label = $label ?: LocalizationUtility::translate($itemKeyArray[$valuesArray[$i]][0], $this->extensionName);
											$label = $label ?: $itemKeyArray[$valuesArray[$i]][0];
											if ($HSC) {
												$label = htmlspecialchars($label, ENT_QUOTES, $charset);
											}
											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $this->cObj->stdWrap($label,$stdWrap) : $label);
										}
									}
									if ($colConfig['foreign_table']) {
										if ($colName === 'usergroup' && is_object($this->userGroupObj)) {
											$valuesArray = $this->userGroupObj->restrictToSelectableValues($valuesArray, $this->conf, $cmdKey);
										}
										reset($valuesArray);
										$firstValue = current($valuesArray);
										if (!empty($firstValue) || count($valuesArray) > 1) {
											$titleField = $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['label'];
											if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
												$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
													->getQueryBuilderForTable($colConfig['foreign_table']);
												$queryBuilder
													->getRestrictions()
													->removeAll();
												$foreignRows = $queryBuilder
													->select('*')
													->from($colConfig['foreign_table'])
													->where(
														$queryBuilder->expr()->in('uid', array_map('intval', $valuesArray))
													)
													->execute()
													->fetchAll();
											} else {
												// TYPO3 CMS 7 LTS
												$where = 'uid IN (' . implode(',', $valuesArray) . ')';
												$foreignRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $colConfig['foreign_table'], $where);
											}
											if (is_array($foreignRows) && count($foreignRows) > 0) {
												for ($i = 0; $i < count($foreignRows); $i++) {
													if ($this->theTable === 'fe_users' && $colName === 'usergroup') {
														$foreignRows[$i] = $this->getUsergroupOverlay($foreignRows[$i]);
													} else if ($localizedRow = $GLOBALS['TSFE']->sys_page->getRecordOverlay($colConfig['foreign_table'], $foreignRows[$i], $languageUid)) {
														$foreignRows[$i] = $localizedRow;
													}
													$text = $foreignRows[$i][$titleField];
													if ($HSC) {
														$text = htmlspecialchars($text, ENT_QUOTES, $charset);
													}
													$colContent .= (($bNotLast || $i < count($foreignRows) - 1) ?  $this->cObj->stdWrap($text, $stdWrap) : $text);
												}
											}
										}
									}
								}
								break;
							default:
								// unsupported input type
								$label = LocalizationUtility::translate('unsupported', $this->extensionName);
								if ($HSC)	{
									$label = htmlspecialchars($label, ENT_QUOTES, $charset);
								}
								$colContent .= $colConfig['type'] . ':' . $label;
								break;
						}
					} else {
						$itemArray = '';
						// Configure inputs based on TCA type
						if (in_array($type, array('check', 'radio', 'select')))	{
								$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',', $mrow[$colName]);
								if (!$valuesArray[0] && $colConfig['default']) {
									$valuesArray[] = $colConfig['default'];
								}
								if (in_array($type, array('radio', 'select')) && $colConfig['itemsProcFunc']) {
									$itemArray = GeneralUtility::callUserFunction($colConfig['itemsProcFunc'], $colConfig, $this, '');
								}
								$itemArray = $colConfig['items'];
						}
						switch ($type) {
							case 'input':
								$colContent = '<input type="input" name="FE[' . $this->theTable . '][' . $colName . ']"' .
									' title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_' : '') . mb_strtoupper($colName, 'utf-8') . '###"' .
									' size="' . ($colConfig['size'] ? $colConfig['size'] : 30) . '"';
								if ($colConfig['max']) {
									$colContent .= ' maxlength="' . $colConfig['max'] . '"';
								}
								if ($colConfig['default']) {
									$label = LocalizationUtility::translate($colConfig['default'], $this->extensionName);
									$label = htmlspecialchars($label,ENT_QUOTES,$charset);
									$colContent .= ' value="' . $label . '"';
								}
								$colContent .= ' />';
								$markerArray['###MAXLENGTH_' . $colName . '###'] = $colConfig['max'] ?: ($colConfig['size'] ?: 20);
								break;
							case 'text':
								$label = LocalizationUtility::translate($colConfig['default'], $this->extensionName);
								$label = htmlspecialchars($label, ENT_QUOTES, $charset);
								$colContent = '<textarea id="' .  CssUtility::getClassName($this->prefixId, $colName) . '" name="FE[' . $this->theTable . '][' . $colName . ']"' .
									' title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_':'') . mb_strtoupper($colName, 'utf-8') . '###"' .
									' cols="' . ($colConfig['cols'] ? $colConfig['cols'] : 30) . '"' .
									' rows="' . ($colConfig['rows'] ? $colConfig['rows'] : 5) . '"' .
									'>' . ($colConfig['default'] ? $label : '') . '</textarea>';
								$markerArray['###MAXLENGTH_' . $colName . '###'] = $colConfig['max'] ?: ($colConfig['cols'] ? $colConfig['cols'] : 30)*($colConfig['rows'] ? $colConfig['rows'] : 5);
								break;
							case 'check':
								$label = LocalizationUtility::translate('tooltip_' . $colName, $this->extensionName);
								$label = htmlspecialchars($label, ENT_QUOTES, $charset);

								if (isset($itemArray) && is_array($itemArray)) {
									$uidText = CssUtility::getClassName($this->prefixId, $colName);
									if (isset($mrow) && is_array($mrow) && $mrow['uid']) {
										$uidText .= '-' . $mrow['uid'];
									}
									$colContent = '<ul id="' . $uidText . '" class="tx-srfeuserregister-multiple-checkboxes">';
									if ($this->parameters->getFeUserData('submit') || $this->parameters->getFeUserData('doNotSave') ||  $cmd === 'edit') {
										$startVal = $mrow[$colName];
									} else {
										$startVal = $colConfig['default'];
									}
									foreach ($itemArray as $key => $value) {
										$checked = ($startVal & (1 << $key)) ? ' checked="checked"' : '';
										$label = LocalizationUtility::translate(substr(strrchr($value[0], ':'), 1), $this->extensionName);
										$label = $label ?: LocalizationUtility::translate($value[0], $this->extensionName);
										$label = $label ?: $value[0];
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
										$colContent .= '<li><input type="checkbox"' .
										CssUtility::classParam($this->prefixId, 'checkbox') .
										' id="' . $uidText . '-' . $key .  '" name="FE[' . $this->theTable . '][' . $colName . '][]" value="' . $key . '"' . $checked . ' /><label for="' . $uidText . '-' . $key . '">' . $label . '</label></li>';
									}
									$colContent .= '</ul>';
								} else {
									$colContent = '<input type="checkbox"' .
									CssUtility::classParam($this->prefixId, 'checkbox') .
									' id="' . CssUtility::getClassName($this->prefixId, $colName) .
									'" name="FE[' . $this->theTable . '][' . $colName . ']" title="' . $label . '"' . ($mrow[$colName] ? ' checked="checked"' : '') . ' />';
								}
								break;
							case 'radio':
								if ($this->parameters->getFeUserData('submit') || $this->parameters->getFeUserData('doNotSave') ||  $cmd === 'edit') {
									$startVal = $mrow[$colName];
								} else {
									$startVal = $colConfig['default'];
								}
								if (!isset($startVal)) {
									reset($colConfig['items']);
									list($startConf) = $colConfig['items'];
									$startVal = $startConf[1];
								}

								if (!$bStdWrap) {
									$stdWrap['wrap'] = '| ';
								}

								if (isset($itemArray) && is_array($itemArray)) {
									$i = 0;
									foreach ($itemArray as $key => $confArray) {
										$value = $confArray[1];
										$label = LocalizationUtility::translate(substr(strrchr($confArray[0], ':'), 1), $this->extensionName);
										$label = $label ?: LocalizationUtility::translate($confArray[0], $this->extensionName);
										$label = $label ?: $confArray[0];
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
										$itemOut = '<input type="radio"'
											. CssUtility::classParam($this->prefixId, 'radio')
											. ' id="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $i . '"'
											. ' name="FE[' . $this->theTable . '][' . $colName . ']"'
											. ' value="' . $value . '" ' . ($value == $startVal ? ' checked="checked"' : '') . ' />' .
											'<label for="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $i . '">' . $label . '</label>';
										$i++;
										$colContent .= ((!$bNotLast || $i < count($itemArray) - 1 ) ?  $this->cObj->stdWrap($itemOut, $stdWrap) : $itemOut);
									}
								}
								break;
							case 'select':
								$colContent ='';
								if ($colConfig['maxitems'] > 1) {
									$multiple = '[]" multiple="multiple';
								} else {
									$multiple = '';
								}

								if ($this->theTable === 'fe_users' && $colName == 'usergroup' && !$this->conf['allowMultipleUserGroupSelection']) {
									$multiple = '';
								}

								if ($colConfig['renderMode'] === 'checkbox') {
									$colContent .= '
										<input id="' . CssUtility::getClassName($this->prefixId, $colName) .
										'" name="FE[' . $this->theTable . '][' . $colName . ']" value="" type="hidden" />';
									$colContent .= '
										<dl class="' .
										CssUtility::getClassName($this->prefixId, 'multiple-checkboxes') .
										'" title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_' : '') . mb_strtoupper($colName, 'utf-8') . '###">';
								} else {
									$colContent .= '<select class="'.
									CssUtility::getClassName($this->prefixId, 'multiple-checkboxes') .
									'" id="' . CssUtility::getClassName($this->prefixId, $colName) .
									'" name="FE[' . $this->theTable . '][' . $colName . ']' . $multiple . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . mb_strtoupper($colName, 'utf-8') . '###">';
								}

								if (is_array($itemArray)) {
									$itemArray = $this->getItemKeyArray($itemArray);
									$i = 0;
									foreach ($itemArray as $k => $item)	{
										$label = LocalizationUtility::translate(substr(strrchr($item[0], ':'), 1), $this->extensionName);
										$label = LocalizationUtility::translate($item[0], $this->extensionName);
										$label = $label ?: $item[0];
										$label = $label ? htmlspecialchars($label, ENT_QUOTES, $charset) : '';
										if ($colConfig['renderMode'] === 'checkbox') {
											$colContent .= '<dt><input class="' .
											CssUtility::getClassName($this->prefixId, 'checkbox-checkboxes') .
											 '" id="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $i . '" name="FE[' . $this->theTable . '][' . $colName . '][' . $k . ']" value="' . $k . '" type="checkbox"  ' . (in_array($k, $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
												<dd><label for="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $i . '">' . $label . '</label></dd>';
										} else {
											$colContent .= '<option value="' . ($k || !$colConfig['foreign_table'] ? $k : '') . '" ' . (in_array($k, $valuesArray) ? 'selected="selected"' : '') . '>' . $label . '</option>';
										}
										$i++;
									}
								}

								if ($colConfig['foreign_table'] && isset($GLOBALS['TCA'][$colConfig['foreign_table']])) {
									$titleField = $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['label'];
									$reservedValues = array();
									if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
										$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
											->getQueryBuilderForTable($colConfig['foreign_table'])
											->select('*')
											->from($colConfig['foreign_table']);
										$queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
										if ($colName === 'usergroup' && is_object($this->userGroupObj)) {
											$reservedValues = $this->userGroupObj->getReservedValues($this->conf);
											$this->userGroupObj->getAllowedWhereClause($colConfig['foreign_table'], $this->parameters->getPid(), $this->conf, $cmdKey, true, $queryBuilder);
										}
										if (
											$this->conf['useLocalization']
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['languageField']
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField']
										) {
											if (empty($queryBuilder->getQueryPart('where'))) {
												$queryBuilder->where($queryBuilder->expr()->eq($GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField'], $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)));
											} else {
												$queryBuilder->andWhere($queryBuilder->expr()->eq($GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField'], $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)));
											}
										}
										if (
											$colName === 'module_sys_dmail_category'
											&& $colConfig['foreign_table'] === 'sys_dmail_category'
											&& $this->conf['module_sys_dmail_category_PIDLIST']
										) {
											$pidArray = array_map('intval', GeneralUtility::trimExplode(',', $this->conf['module_sys_dmail_category_PIDLIST']));
											if (empty($queryBuilder->getQueryPart('where'))) {
												$queryBuilder->where($queryBuilder->expr()->in('sys_dmail_category.pid', $pidArray));
											} else {
												$queryBuilder->andWhere($queryBuilder->expr()->in('sys_dmail_category.pid', $pidArray));
											}
											if ($this->conf['useLocalization']) {
												$queryBuilder->andWhere($queryBuilder->expr()->eq('sys_dmail_category.sys_language_uid', $queryBuilder->createNamedParameter((int)$languageUid, \PDO::PARAM_INT)));
											}
										}
										if ($GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['sortby']) {
											$queryBuilder->orderBy($GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['sortby']);
										}
										$foreignWhereClause = trim($this->replaceForeignWhereMarker('',  $colName, $colConfig));
										if ($foreignWhereClause) {
											$foreignWhereClause = \TYPO3\CMS\Core\Database\Query\QueryHelper::stripLogicalOperatorPrefix($foreignWhereClause);
											if (empty($queryBuilder->getQueryPart('where'))) {
												$queryBuilder->where($foreignWhereClause);
											} else {
												$queryBuilder->andWhere($foreignWhereClause);
											}
										}
										$rows = $queryBuilder
											->execute()
											->fetchAll();
									} else {
										// TYPO3 CMS 7 LTS
										$whereClause = '1=1';
										if ($colName === 'usergroup' && is_object($this->userGroupObj)) {
											$reservedValues = $this->userGroupObj->getReservedValues($this->conf);
											$whereClause = $this->userGroupObj->getAllowedWhereClause($colConfig['foreign_table'], $this->parameters->getPid(), $this->conf, $cmdKey);
										}
										if (
											$this->conf['useLocalization']
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['languageField']
											&& $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField']
										) {
											$whereClause .= ' AND ' . $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField'] . '=0';
										}
										if (
											$colName === 'module_sys_dmail_category'
											&& $colConfig['foreign_table'] === 'sys_dmail_category'
											&& $this->conf['module_sys_dmail_category_PIDLIST']
										) {
											$tmpArray = GeneralUtility::trimExplode(',', $this->conf['module_sys_dmail_category_PIDLIST']);
											$pidArray = array();
											foreach ($tmpArray as $v) {
												if (is_numeric($v))	{
													$pidArray[] = $v;
												}
											}
											$whereClause .= ' AND sys_dmail_category.pid IN (' . implode(',',$pidArray) . ')' . ($this->conf['useLocalization'] ? ' AND sys_language_uid=' . (int) $languageUid : '');
										}
										$whereClause .= $this->cObj->enableFields($colConfig['foreign_table']);
										$whereClause = $this->replaceForeignWhereMarker($whereClause,  $colName, $colConfig);
										$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $colConfig['foreign_table'], $whereClause, '', $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['sortby']);
									}

									if (!in_array($colName, $requiredFields)) {
										if ($colConfig['renderMode'] === 'checkbox' || $colContent) {
											// nothing
										} else {
											if (!empty($itemArray)) {
												$label = LocalizationUtility::translate(substr(strrchr($itemArray[0][0], ':'), 1), $this->extensionName);
												$label = $label ?: LocalizationUtility::translate($itemArray[0][0], $this->extensionName);
												$label = $label ?: $itemArray[0][0];
											}
											$label = $label ? htmlspecialchars($label, ENT_QUOTES, $charset) : '';
											$colContent .= '<option value="" ' . ($valuesArray[0] ? '' : 'selected="selected"') . '>' . $label . '</option>';
										}
									}

									$selectedValue = false;
									foreach ($rows as $row2) {
										// Handle usergroup case
										if ($colName === 'usergroup' && is_object($this->userGroupObj)) {
											if (!in_array($row2['uid'], $reservedValues)) {
												$row2 = $this->getUsergroupOverlay($row2);
												$titleText = htmlspecialchars($row2[$titleField], ENT_QUOTES, $charset);
												$selected = (in_array($row2['uid'], $valuesArray) ? ' selected="selected"' : '');
												if (!$this->conf['allowMultipleUserGroupSelection'] && $selectedValue) {
													$selected = '';
												}
												$selectedValue = ($selected ? true: $selectedValue);
												if ($colConfig['renderMode'] === 'checkbox') {
													$colContent .= '<dt><input  class="' .
													CssUtility::getClassName($this->prefixId, 'checkbox') .
													'" id="'. CssUtility::getClassName($this->prefixId, $colName) . '-' . $row2['uid'] . '" name="FE[' . $this->theTable . '][' . $colName . '][' . $row2['uid'] . ']" value="'.$row2['uid'] . '" type="checkbox"' . ($selected ? ' checked="checked"':'') . ' /></dt>
													<dd><label for="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $row2['uid'] . '">' . $titleText . '</label></dd>';
												} else {
													$colContent .= '<option value="' . $row2['uid'] . '"' . $selected . '>' . $titleText . '</option>';
												}
											}
										} else {
											if ($localizedRow = $GLOBALS['TSFE']->sys_page->getRecordOverlay($colConfig['foreign_table'], $row2, $languageUid)) {
												$row2 = $localizedRow;
											}
											$titleText = htmlspecialchars($row2[$titleField], ENT_QUOTES, $charset);

											if ($colConfig['renderMode'] === 'checkbox') {
												$colContent .= '<dt><input class="' .
												CssUtility::getClassName($this->prefixId, 'checkbox') .
												'" id="'. CssUtility::getClassName($this->prefixId, $colName) . '-' . $row2['uid'] . '" name="FE[' . $this->theTable . '][' . $colName . '][' . $row2['uid'] . ']" value="' . $row2['uid'] . '" type="checkbox"' . (in_array($row2['uid'],  $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
												<dd><label for="' . CssUtility::getClassName($this->prefixId, $colName) . '-' . $row2['uid'] . '">' . $titleText . '</label></dd>';

											} else {
												$colContent .= '<option value="' . $row2['uid'] . '"' . (in_array($row2['uid'], $valuesArray) ? 'selected="selected"' : '') . '>' . $titleText . '</option>';
											}
										}
									}
								}

								if ($colConfig['renderMode'] === 'checkbox') {
									$colContent .= '</dl>';
								} else {
									$colContent .= '</select>';
								}
								break;

							default:
								$colContent .= $colConfig['type'] . ':' . LocalizationUtility::translate('unsupported', $this->extensionName);
								break;
						}
					}
				} else {
					$colContent = '';
				}

				if ($viewOnly) {
					$markerArray['###TCA_INPUT_VALUE_' . $colName . '###'] = $colContent;
				}
				$markerArray['###TCA_INPUT_' . $colName . '###'] = $colContent;
			} else {
				// field not in form fields list
			}
		}
		$this->setMarkerArray($markerArray);
	}

	/**
	 * Transfers the item array to one where the key corresponds to the value
	 * @param array	array of selectable items like found in TCA
	 * @return array array of selectable items with correct key
	 */
	public function getItemKeyArray(array $itemArray)
	{
		$rc = array();
		if (is_array($itemArray)) {
			foreach ($itemArray as $k => $row) {
				$key = $row[1];
				$rc[$key] = $row;
			}
		}
		return $rc;
	}

	/**
	 * Return the relevant usergroup overlay record fields
	 * Adapted from t3lib_page.php
	 *
	 * @param mixed If $usergroup is an integer, it's the uid of the usergroup overlay record and thus the usergroup overlay record is returned. If $usergroup is an array, it's a usergroup record and based on this usergroup record the language overlay record is found and overlaid before the usergroup record is returned.
	 * @param integer Language UID. Should be >=0
	 * @return array usergroup row which is overlayed with language_overlay record (or the overlay record alone)
	 */
	public function getUsergroupOverlay($usergroup, $languageUid = 0)
	{
		// Initialize:
		if (!$languageUid) {
			$languageUid = (int) $GLOBALS['TSFE']->config['config']['sys_language_uid'];
		}

		// If language UID is different from zero, do overlay:
		if ($languageUid > 0) {
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
				if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
					$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
						->getQueryBuilderForTable('fe_groups_language_overlay');
					$queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
					$queryBuilder
						->select(array_shift($fieldArr));
					foreach ($fieldArr as $field) {
						$queryBuilder
							->addSelect($field);	
					}
					$rows = $queryBuilder
						->from('fe_groups_language_overlay')
						->where(
							$queryBuilder->expr()->eq('fe_group', $queryBuilder->createNamedParameter((int)$fe_groups_uid, \PDO::PARAM_INT)),
							$queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter((int)$languageUid, \PDO::PARAM_INT))
						)
						->execute()
						->fetchAll();
					if (is_array($rows)) {
						$row = $rows[0];
					}
				} else {
					// TYPO3 CMS 7 LTS
					$whereClause = 'fe_group=' . (int) $fe_groups_uid . ' ' .
						'AND sys_language_uid=' . (int) $languageUid . ' ' .
						$this->cObj->enableFields('fe_groups_language_overlay');
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', $fieldArr), 'fe_groups_language_overlay', $whereClause);
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
						$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					}
				}
			}
		}

		// Create output:
		if (is_array($usergroup)) {
			// If the input was an array, simply overlay the newfound array and return...
			return is_array($row) ? array_merge($usergroup, $row) : $usergroup;
		} else {
			// always an array in return
			return is_array($row) ? $row : array();
		}
	}

	/**
	 * Replaces the markers in the foreign table where clause
	 *
	 * @param string $whereClause: foreign table where clause
	 * @param string $colName: column name
	 * @param array $colConfig: $TCA column configuration
	 * @return string foreign table where clause with replaced markers
	 */
	protected function replaceForeignWhereMarker($whereClause, $colName, array $colConfig)
	{
		$foreignWhere = $colConfig['foreign_table_where'];
		if ($foreignWhere) {
			$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();	
			$TSconfig = $pageTSConfig['TCEFORM.'][$this->theTable . '.'][$colName . '.'];
			if ($TSconfig) {
				// substitute whereClause
				$foreignWhere = str_replace('###PAGE_TSCONFIG_ID###', intval($TSconfig['PAGE_TSCONFIG_ID']), $foreignWhere);
				$foreignWhere =
					str_replace(
						'###PAGE_TSCONFIG_IDLIST###',
						implode(',', GeneralUtility::intExplode(',', $TSconfig['PAGE_TSCONFIG_IDLIST'])),
						$foreignWhere
					);
			}

			// have all markers in the foreign where been replaced?
			if (strpos($foreignWhere, '###') === false) {
				$orderbyPos = stripos($foreignWhere, 'ORDER BY');
				if ($orderbyPos !== false) {
					$whereClause .= ' ' . substr($foreignWhere, 0, $orderbyPos);
				} else {
					$whereClause .= ' ' . $foreignWhere;
				}
			}
		}
		return $whereClause;
	}

	public function getSubpart($content, $marker)
	{
		return $this->markerBasedTemplateService->getSubpart($content, $marker);
	}

	public function substituteSubpart($content, $marker, $subpartContent, $recursive = true, $keepMarker = false)
	{
		return $this->markerBasedTemplateService->substituteSubpart($content, $marker, $subpartContent, $recursive, $keepMarker);
	}

	public function substituteMarkerArray($content, $markContentArray, $wrap = '', $uppercase = false, $deleteUnused = false)
	{
		return $this->markerBasedTemplateService->substituteMarkerArray($content, $markContentArray, $wrap, $uppercase, $deleteUnused);
	}
}