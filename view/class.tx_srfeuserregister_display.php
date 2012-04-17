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
 * @author	Kasper Skaarhoj <kasper2010@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_display {
	public $conf = array();
	public $config = array();
	public $data;
	public $marker;
	public $tca;
	public $control;
	public $controlData;
	public $cObj;


	public function init (
		&$cObj,
		&$conf,
		&$config,
		&$data,
		&$marker,
		&$tca,
		&$control
	) {
		$this->conf = &$conf;
		$this->config = &$config;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->tca = &$tca;
		$this->control = &$control;
		$this->controlData = &$control->controlData;
		$this->cObj = &$cObj;
	}


	/**
	* Displays the record update form
	*
	* @param array  $origArray: the array coming from the database
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted markers
	*/
	public function editForm (
		&$markerArray,
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$cmd,
		$cmdKey,
		$mode,
		$errorFieldArray,
		$token
	) {
		global $TSFE;

		$prefixId = $this->controlData->getPrefixId();

		if (isset($dataArray) && is_array($dataArray))	{
			$currentArray = array_merge($origArray, $dataArray);
		} else {
			$currentArray = $origArray;
		}
		if ($cmdKey === 'password') {
			$subpart = '###TEMPLATE_SETFIXED_OK_APPROVE_INVITE###';
		} else {
			$subpart = '###TEMPLATE_EDIT' . $this->marker->getPreviewLabel() . '###';
		}
		$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(), $subpart);

		if (!$this->conf['linkToPID'] || !$this->conf['linkToPIDAddButton'] || !($mode == MODE_PREVIEW || !$this->conf[$cmd . '.']['preview'])) {
			$templateCode =
				$this->cObj->substituteSubpart(
					$templateCode,
					'###SUB_LINKTOPID_ADD_BUTTON###',
					''
				);
		}
		$failure = t3lib_div::_GP('noWarnings') ? '': $this->controlData->getFailure();

		if (!$failure) {
			$templateCode =
				$this->cObj->substituteSubpart(
					$templateCode,
					'###SUB_REQUIRED_FIELDS_WARNING###',
					''
				);
		}
		$this->marker->addPasswordTransmissionMarkers($markerArray);
		$templateCode =
			$this->removeRequired(
				$templateCode,
				$errorFieldArray,
				$failure
			);
		$markerArray =
			$this->marker->fillInMarkerArray(
				$markerArray,
				$currentArray,
				$securedArray,
				'',
				TRUE
			);
		$this->marker->addStaticInfoMarkers($markerArray, $currentArray);
		$this->tca->addTcaMarkers(
			$markerArray,
			$currentArray,
			$origArray,
			$cmd,
			$cmdKey,
			$theTable,
			TRUE
		);
		$this->tca->addTcaMarkers(
			$markerArray,
			$currentArray,
			$origArray,
			$cmd,
			$cmdKey,
			$theTable
		);

		$this->marker->addLabelMarkers(
			$markerArray,
			$theTable,
			$currentArray,
			$origArray,
			$securedArray,
			array(),
			$this->controlData->getRequiredArray(),
			$this->data->getFieldList(),
			$this->tca->TCA['columns'],
			FALSE
		);

		foreach ($this->tca->TCA['columns'] as $theField => $fieldConfig)	{

			if (
				$fieldConfig['config']['internal_type'] == 'file' &&
				$fieldConfig['config']['allowed'] != '' &&
				$fieldConfig['config']['uploadfolder'] != ''
			) {
				$this->marker->addFileUploadMarkers(
					$theTable,
					$theField,
					$fieldConfig,
					$markerArray,
					$cmd,
					$cmdKey,
					$currentArray,
					$this->controlData->getMode() == MODE_PREVIEW
				);
			}
		}

		$templateCode =
			$this->marker->removeStaticInfoSubparts(
				$templateCode,
				$markerArray
			);
		$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE[' . $theTable . '][uid]" value="' . $currentArray['uid'] . '" />';

		if ($theTable != 'fe_users') {
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId . '[aC]" value="' . $authObj->authCode($origArray, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']) . '" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId . '[cmd]" value="edit" />';
		}

		$this->marker->addHiddenFieldsMarkers(
			$markerArray,
			$cmdKey,
			$mode,
			$token,
			$currentArray
		);
			// Avoid cleartext password in HTML source
		$markerArray['###FIELD_password###'] = '';
		$markerArray['###FIELD_password_again###'] = '';
		$deleteUnusedMarkers = TRUE;
		$content =
			$this->cObj->substituteMarkerArray(
				$templateCode,
				$markerArray,
				'',
				FALSE,
				$deleteUnusedMarkers
			);

		if ($mode != MODE_PREVIEW) {
			$form =
				tx_div2007_alpha::getClassName(
					$theTable . '_form',
					$this->controlData->getPrefixId()
				);
			$modData = $this->data->modifyDataArrForFormUpdate($currentArray, $cmdKey);
			$fields = $this->data->getFieldList() . $this->data->getAdditionalUpdateFields();
			$fields = $this->controlData->getOpenFields($fields);
			$updateJS =
				$this->cObj->getUpdateJS(
					$modData,
					$form,
					'FE[' . $theTable . ']',
					$fields
				);

			$content .= $updateJS;
			$GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . t3lib_div::createVersionNumberedFilename(t3lib_extMgm::siteRelPath('sr_feuser_register')  . 'scripts/jsfunc.updateform.js') . '"></script>';
		}
		return $content;
	}	// editForm


	/**
	* Generates the record creation form
	* or the first link display to create or edit someone's data
	*
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted markers
	*/
	public function createScreen (
		&$markerArray,
		$cmd,
		$cmdKey,
		$mode,
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$infoFields,
		$errorFieldArray,
		$token
	) {
		global $TSFE;

		$templateCode = $this->data->getTemplateCode();
		$prefixId = $this->controlData->getPrefixId();
		$extKey = $this->controlData->getExtKey();
		$currentArray = array_merge($origArray, $dataArray);

		if ($theTable == 'fe_users') {
			if (!isset($currentArray['password'])) {
				$currentArray['password'] = '';
			}
			$currentArray['password_again'] = $currentArray['password'];
		}

		if ($this->conf['create']) {

				// Call all beforeConfirmCreate hooks before the record has been shown and confirmed
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'] as $classRef) {
					$hookObj= &t3lib_div::getUserObj($classRef);
					if (method_exists($hookObj,'registrationProcess_beforeConfirmCreate')) {
						$hookObj->registrationProcess_beforeConfirmCreate($dataArray, $this->controlData);
					}
				}
			}
			$key = ($cmd == 'invite') ? 'INVITE': 'CREATE';

			$bNeedUpdateJS = TRUE;
			if ($cmd == 'create' || $cmd == 'invite') {
				$subpartKey = '###TEMPLATE_' . $key . $this->marker->getPreviewLabel() . '###';
			} else {
				$bNeedUpdateJS = FALSE;
				if ($GLOBALS['TSFE']->loginUser) {
					$subpartKey = '###TEMPLATE_CREATE_LOGIN###';
				} else {
					$subpartKey = '###TEMPLATE_AUTH###';
				}
			}
			
			if ($bNeedUpdateJS) {
				$this->marker->addPasswordTransmissionMarkers($markerArray);
			}

			$templateCode = $this->cObj->getSubpart($templateCode, $subpartKey);
			$failure = t3lib_div::_GP('noWarnings') ? FALSE: $this->controlData->getFailure();

			if ($failure == FALSE) {
				$templateCode = $this->cObj->substituteSubpart(
					$templateCode,
					'###SUB_REQUIRED_FIELDS_WARNING###',
					''
				);
			}

			$templateCode =
				$this->removeRequired(
					$templateCode,
					$errorFieldArray,
					$failure
				);
			$markerArray =
				$this->marker->fillInMarkerArray(
					$markerArray,
					$currentArray,
					$securedArray,
					'',
					TRUE
				);
			$this->marker->addStaticInfoMarkers($markerArray, $dataArray);
			$this->tca->addTcaMarkers(
				$markerArray,
				$dataArray,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable
			);

			foreach ($this->tca->TCA['columns'] as $theField => $fieldConfig) {
				if (
					$fieldConfig['config']['internal_type'] == 'file' &&
					$fieldConfig['config']['allowed'] != '' &&
					$fieldConfig['config']['uploadfolder'] != ''
				) {
					$this->marker->addFileUploadMarkers(
						$theTable,
						$theField,
						$fieldConfig,
						$markerArray,
						$cmd,
						$cmdKey,
						$dataArray,
						$this->controlData->getMode() == MODE_PREVIEW
					);
				}
			}

			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$dataArray,
				$origArray,
				$securedArray,
				array(),
				$this->controlData->getRequiredArray(),
				$infoFields,
				$this->tca->TCA['columns'],
				FALSE
			);

			$templateCode =
				$this->marker->removeStaticInfoSubparts(
					$templateCode,
					$markerArray
				);
			$this->marker->addHiddenFieldsMarkers(
				$markerArray,
				$cmdKey,
				$mode,
				$token,
				$dataArray
			);
				// Avoid cleartext password in HTML source
			$markerArray['###FIELD_password###'] = '';
			$markerArray['###FIELD_password_again###'] = '';
			$deleteUnusedMarkers = TRUE;
			$content =
				$this->cObj->substituteMarkerArray(
					$templateCode,
					$markerArray,
					'',
					FALSE,
					$deleteUnusedMarkers
				);
			if ($mode != MODE_PREVIEW && $bNeedUpdateJS) {
				$fields = $this->data->fieldList . $this->data->additionalUpdateFields;
				$fields = $this->controlData->getOpenFields($fields);
				$modData = $this->data->modifyDataArrForFormUpdate($dataArray, $cmdKey);
				$form =
					tx_div2007_alpha::getClassName(
						$theTable . '_form',
						$this->controlData->getPrefixId()
					);
				$updateJS =
					$this->cObj->getUpdateJS(
						$modData,
						$form,
						'FE[' . $theTable . ']',
						$fields
					);
				$content .= $updateJS;
				$GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $GLOBALS['TSFE']->absRefPrefix . t3lib_div::createVersionNumberedFilename(t3lib_extMgm::siteRelPath('sr_feuser_register')  . 'scripts/jsfunc.updateform.js') . '"></script>';
			}
		}

		return $content;
	} // createScreen


	/**
	* Checks if the edit form may be displayed; if not, a link to login
	*
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted markers
	*/
	public function editScreen (
		&$markerArray,
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$cmd,
		$cmdKey,
		$mode,
		$errorFieldArray,
		$token
	) {
		global $TSFE;

			// If editing is enabled
		if ($this->conf['edit']) {
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			if(
				$theTable != 'fe_users' &&
				$this->conf['setfixed.']['EDIT.']['_FIELDLIST']
			) {
				$fD = t3lib_div::_GP('fD', 1);
				$fieldArr = array();
				if (is_array($fD)) {
					foreach($fD as $field => $value) {
						$origArray[$field] = rawurldecode($value);
						$fieldArr[] = $field;
					}
				}
				$theCode =
					$authObj->setfixedHash(
						$origArray,
						$origArray['_FIELDLIST']
					);
			}

			if (is_array($origArray)) {
				$origArray = $this->data->parseIncomingData($origArray);
			}
			$aCAuth = $authObj->aCAuth($origArray, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']);
			if (
				is_array($origArray) &&
				(
					($theTable === 'fe_users' && $GLOBALS['TSFE']->loginUser) ||
					$aCAuth ||
					$theCode && !strcmp($authObj->authCode, $theCode)
				)
			) {
				$this->marker->setArray($markerArray);
				// Must be logged in OR be authenticated by the aC code in order to edit
				// If the recUid selects a record.... (no check here)
				if (
					!strcmp($authObj->authCode, $theCode) ||
					$aCAuth ||
					$this->cObj->DBmayFEUserEdit(
						$theTable,
						$origArray,
						$GLOBALS['TSFE']->fe_user->user,
						$this->conf['allowedGroups'],
						$this->conf['fe_userEditSelf']
					)
				) {
					// Display the form, if access granted.
					$content = $this->editForm(
						$markerArray,
						$theTable,
						$dataArray,
						$origArray,
						$securedArray,
						$cmd,
						$cmdKey,
						$mode,
						$errorFieldArray,
						$token
					);
				} else {
					// Else display error, that you could not edit that particular record...
					$content = $this->getPlainTemplate(
						$this->data->getTemplateCode(),
						'###TEMPLATE_NO_PERMISSIONS###',
						$markerArray,
						$dataArray,
						$origArray,
						$securedArray
					);
				}
			} else {
				// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				$content = $this->getPlainTemplate(
					$this->data->getTemplateCode(),
					'###TEMPLATE_AUTH###',
					$markerArray,
					$dataArray,
					$this->data->getOrigArray(),
					$securedArray
				);
			}
		} else {
			$langObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lang');
			$content .= $langObj->getLL('internal_edit_option');
		}

		return $content;
	}	// editScreen


	/**
	* This is basically the preview display of delete
	*
	* @return string  the template with substituted markers
	*/
	public function deleteScreen (
		$markerArray,
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$token
	) {
		if ($this->conf['delete']) {

			$prefixId = $this->controlData->getPrefixId();
			$templateCode = $this->data->getTemplateCode();
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			// If deleting is enabled
			$origArray =
				$GLOBALS['TSFE']->sys_page->getRawRecord(
					$theTable,
					$this->data->getRecUid()
				);
			$aCAuth =
				$authObj->aCAuth(
					$origArray,
					$this->conf['setfixed.']['DELETE.']['_FIELDLIST']
				);

			if (
				($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) ||
				$aCAuth
			) {
				// Must be logged in OR be authenticated by the aC code in order to delete

				// If the recUid selects a record.... (no check here)
				if (is_array($origArray)) {
					$bMayEdit =
						$this->cObj->DBmayFEUserEdit(
							$theTable,
							$origArray,
							$GLOBALS['TSFE']->fe_user->user,
							$this->conf['allowedGroups'],
							$this->conf['fe_userEditSelf']
						);

					if ($aCAuth || $bMayEdit) {
						$markerArray = $this->marker->getArray();
						// Display the form, if access granted.
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="rU" value="'.$this->data->getRecUid().'" />';

						if ($theTable != 'fe_users') {
							$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $prefixId . '[aC]" value="' . $authObj->authCode($origArray, $this->conf['setfixed.']['DELETE.']['_FIELDLIST']) . '" />';
						}
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $prefixId . '[cmd]" value="delete" />';
						$this->marker->addFormToken($markerArray, $token);

						$this->marker->setArray($markerArray);
						$content = $this->getPlainTemplate(
							$templateCode,
							'###TEMPLATE_DELETE_PREVIEW###',
							$markerArray,
							$dataArray,
							$origArray,
							$securedArray
						);
					} else {
						// Else display error, that you could not edit that particular record...
						$content = $this->getPlainTemplate(
							$templateCode,
							'###TEMPLATE_NO_PERMISSIONS###',
							$markerArray,
							$dataArray,
							$origArray,
							$securedArray
						);
					}
				}
			} else {
				// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				if ( $theTable == 'fe_users' ) {
					$content = $this->getPlainTemplate(
						$templateCode,
						'###TEMPLATE_AUTH###',
						$markerArray,
						$origArray,
						'',
						$securedArray
					);
				} else {
					$content = $this->getPlainTemplate(
						$templateCode,
						'###TEMPLATE_NO_PERMISSIONS###',
						$markerArray,
						$origArray,
						'',
						$securedArray
					);
				}
			}
		} else {
			$content .= 'Delete-option is not set in TypoScript';
		}
		return $content;
	}	// deleteScreen


	/**
	* Initializes a template, filling values for data and labels
	*
	* @param string  $subpartMarker: the template subpart marker
	* @param array  $row: the data array, if any
	* @return string  the template with substituted parts and markers
	*/
	public function getPlainTemplate (
		$templateCode,
		$subpartMarker,
		$markerArray,
		$origArray,
		$row = '',
		$securedArray,
		$bCheckEmpty = TRUE
	) {
		$templateCode = $this->cObj->getSubpart($templateCode, $subpartMarker);

		if ($templateCode != '') {
			$markerArray =
				$this->marker->fillInMarkerArray(
					$markerArray,
					is_array($row) ? $row : array(),
					$securedArray,
					''
				);
			$this->marker->addStaticInfoMarkers($markerArray, $row);
			$cmd = $this->controlData->getCmd();
			$cmdKey = $this->controlData->getCmdKey();
			$theTable = $this->controlData->getTable();
			$this->tca->addTcaMarkers(
				$markerArray,
				$row,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable,
				TRUE
			);
			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$row,
				$origArray,
				$securedArray,
				array(),
				$this->controlData->getRequiredArray(),
				$this->data->getFieldList(),
				$this->tca->TCA['columns'],
				FALSE
			);
			$templateCode =
				$this->marker->removeStaticInfoSubparts(
					$templateCode,
					$markerArray
				);
				// Avoid cleartext password in HTML source
			$markerArray['###FIELD_password###'] = '';
			$markerArray['###FIELD_password_again###'] = '';
			$deleteUnusedMarkers = TRUE;
			$rc =
				$this->cObj->substituteMarkerArray(
					$templateCode,
					$markerArray,
					'',
					FALSE,
					$deleteUnusedMarkers
				);
		} else if ($bCheckEmpty) {
			$langObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lang');
			$errorText = $langObj->getLL('internal_no_subtemplate');
			$rc = sprintf($errorText, $subpartMarker);
		}
		return $rc;
	}	// getPlainTemplate


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
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @param string  $failure: the list of fields with errors
	* @return string  the template with susbstituted parts
	*/
	public function removeRequired (
		$templateCode,
		$errorFieldArray,
		$failure = ''
	) {
		$cmdKey = $this->controlData->getCmdKey();
		$requiredArray = $this->controlData->getRequiredArray();
		$includedFields = t3lib_div::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1);

		if (
			$this->controlData->getFeUserData('preview') &&
			!in_array('username', $includedFields)
		) {
			$includedFields[] = 'username';
		}

		$infoFields = explode(',', $this->data->fieldList);
		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$infoFields[] = 'module_sys_dmail_category';
			$infoFields[] = 'module_sys_dmail_html';
		}

		if (!$this->controlData->useCaptcha($cmdKey)) {
			$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_captcha_response###', '');
		}
			// Honour Address List (tt_address) configuration setting
		if ($this->controlData->getTable() == 'tt_address') {
			$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address']);
			if ($extConf['disableCombinedNameField'] == '1') {
				$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_name###', '');
			}
		}

		foreach($infoFields as $k => $theField) {

			if (in_array(trim($theField), $requiredArray) ) {
				if (!t3lib_div::inList($failure, $theField)) {
					$templateCode =
						$this->cObj->substituteSubpart(
							$templateCode,
							'###SUB_REQUIRED_FIELD_' . $theField . '###',
							''
						);
					$templateCode =
						$this->cObj->substituteSubpart(
							$templateCode,
							'###SUB_ERROR_FIELD_' . $theField . '###',
							''
						);
				} else if (!$errorFieldArray[$theField]) {
					$templateCode =
						$this->cObj->substituteSubpart(
							$templateCode,
							'###SUB_ERROR_FIELD_' . $theField . '###',
							''
						);
				}
			} else {
				if (!in_array(trim($theField), $includedFields) && !t3lib_div::inList($failure,  $theField)) {
					$templateCode =
						$this->cObj->substituteSubpart(
							$templateCode,
							'###SUB_INCLUDED_FIELD_' . $theField . '###',
							''
						);
				} else {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$theField.'###', '');
					if (!t3lib_div::inList($failure, $theField)) {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$theField.'###', '');
					}

					if (
						is_array($this->conf['parseValues.']) &&
						strstr($this->conf['parseValues.'][$theField],'checkArray')
					) {
						$listOfCommands = t3lib_div::trimExplode(',', $this->conf['parseValues.'][$theField], 1);
						foreach($listOfCommands as $cmd) {
							$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
							$theCmd = trim($cmdParts[0]);
							switch($theCmd) {
								case 'checkArray':
									$positions = t3lib_div::trimExplode(';', $cmdParts[1]);
									for($i = 0; $i < 10; $i++) {
										if(!in_array($i, $positions)) {
											$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_' . $theField . '_' . $i . '###', '');
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
	}	// removeRequired


	public function getKeyAfterSave ($cmd, $cmdKey, $bCustomerConfirmsMode)	{

		$result = FALSE;
		switch ($cmd) {
			case 'delete':
				$result = 'DELETE' . SAVED_SUFFIX;
				break;
			case 'edit':
			case 'password':
				$result = 'EDIT' . SAVED_SUFFIX;
				break;
			case 'invite':
				$result = SETFIXED_PREFIX . 'INVITE';
				break;
			case 'create':
			default:
				if ($cmdKey == 'edit') {
					$result = 'EDIT' . SAVED_SUFFIX;
				} else if ($this->controlData->getSetfixedEnabled()) {
					$result = SETFIXED_PREFIX . 'CREATE';

					if ($bCustomerConfirmsMode) {
						$result .= '_REVIEW';
					}
					if (!$this->conf['enableEmailConfirmation'] && $this->conf['enableAdminReview']) {
						$result = 'CREATE' . SAVED_SUFFIX . '_REVIEW';
					}
				} else {
					$result = 'CREATE' . SAVED_SUFFIX;
				}
				break;
		}
		return $result;
	}


	/**
	* Displaying the page here that says, the record has been saved.
	* You're able to include the saved values by markers.
	*
	* @param string  $subpartMarker: the template subpart marker
	* @param array  $row: the data array, if any
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted parts and markers
	*/
	public function afterSave (
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$cmd,
		$cmdKey,
		$key,
		$templateCode,
		$markerArray,
		$errorFieldArray,
		&$content
	) {
		global $TSFE;

		$errorContent = '';

			// Display confirmation message
		$subpartMarker = '###TEMPLATE_' . $key . '###';
		$localTemplateCode = $this->cObj->getSubpart($templateCode, $subpartMarker);

		if ($localTemplateCode) {
			$markerArray =
				$this->marker->fillInMarkerArray(
					$markerArray,
					$dataArray,
					$securedArray,
					'',
					TRUE,
					'FIELD_',
					TRUE
				);
			$this->marker->addStaticInfoMarkers(
				$markerArray,
				$dataArray
			);

			$this->tca->addTcaMarkers(
				$markerArray,
				$dataArray,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable,
				TRUE
			);

			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$dataArray,
				$origArray,
				$securedArray,
				array(),
				$this->controlData->getRequiredArray(),
				$this->data->getFieldList(),
				$this->tca->TCA['columns'],
				FALSE
			);

			if ($cmdKey === 'create' && !$this->conf['enableEmailConfirmation'] && !$this->conf['enableAutoLoginOnCreate']) {
				$this->marker->addPasswordTransmissionMarkers($markerArray);
			}

			if (isset($this->conf[$cmdKey . '.']['marker.'])) {
				if ($this->conf[$cmdKey . '.']['marker.']['computeUrl'] == '1') {
					$this->setfixedObj->computeUrl(
						$cmdKey,
						$markerArray,
						$this->conf['setfixed.'],
						$dataArray,
						$theTable
					);
				}
			}

			$content = $this->cObj->substituteMarkerArray(
				$localTemplateCode,
				$markerArray
			);
		} else {
			$langObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lang');
			$errorText = $langObj->getLL('internal_no_subtemplate');
			$errorContent = sprintf($errorText, $subpartMarker);
		}
		return $errorContent;
	}


	public function &removeHTMLComments ($content) {
		$result = preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/','', $content);
		return $result;
	}


	public function replaceHTMLBr ($content) {
		$result = preg_replace('/<br\s?\/>/', chr(10), $content);
		return $result;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php']);
}

?>