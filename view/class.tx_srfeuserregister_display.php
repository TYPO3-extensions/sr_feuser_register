<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2014 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
	
	protected $pibaseObj;

	public function init($pibaseObj) {
		$this->pibaseObj = $pibaseObj;
	}

	/**
	* Displays the record update form
	*
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param array  $origArray: the array coming from the database
	* @param array  $errorFieldArray: array of field with errors (former $dataObj->inError[$theField])
	* @return string  the template with substituted markers
	*/
	protected function editForm (
		&$markerArray,
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
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
		$prefixId = $controlData->getPrefixId();

		if (isset($dataArray) && is_array($dataArray)) {
			$currentArray = array_merge($origArray, $dataArray);
		} else {
			$currentArray = $origArray;
		}
		if ($cmdKey == 'password') {
			$subpart = '###TEMPLATE_SETFIXED_OK_APPROVE_INVITE###';
		} else {
			$subpart = '###TEMPLATE_EDIT' . $markerObj->getPreviewLabel() . '###';
		}
		$templateCode = $cObj->getSubpart($dataObj->getTemplateCode(), $subpart);

		if (
			!$conf['linkToPID'] ||
			!$conf['linkToPIDAddButton'] ||
			!($mode == MODE_PREVIEW ||
			!$conf[$cmd . '.']['preview'])
		) {
			$templateCode =
				$cObj->substituteSubpart(
					$templateCode,
					'###SUB_LINKTOPID_ADD_BUTTON###',
					''
				);
		}
		$failure = t3lib_div::_GP('noWarnings') ? '': $controlData->getFailure();

		if (!$failure) {
			$templateCode =
				$cObj->substituteSubpart(
					$templateCode,
					'###SUB_REQUIRED_FIELDS_WARNING###',
					''
				);
		}
		$markerObj->addPasswordTransmissionMarkers($markerArray);
		$templateCode =
			$this->removeRequired(
				$conf,
				$cObj,
				$controlData,
				$dataObj,
				$templateCode,
				$errorFieldArray,
				$failure
			);
		$markerArray =
			$markerObj->fillInMarkerArray(
				$markerArray,
				$currentArray,
				$securedArray,
				'',
				TRUE
			);
		$markerObj->addStaticInfoMarkers(
			$markerArray,
			$prefixId,
			$currentArray
		);
		$tcaObj->addTcaMarkers(
			$markerArray,
			$conf,
			$cObj,
			$langObj,
			$controlData,
			$currentArray,
			$origArray,
			$cmd,
			$cmdKey,
			$theTable,
			$prefixId,
			TRUE
		);
		$tcaObj->addTcaMarkers(
			$markerArray,
			$conf,
			$cObj,
			$langObj,
			$controlData,
			$currentArray,
			$origArray,
			$cmd,
			$cmdKey,
			$theTable,
			$prefixId,
			FALSE
		);

		$markerObj->addLabelMarkers(
			$markerArray,
			$conf,
			$cObj,
			$controlData->getExtKey(),
			$theTable,
			$currentArray,
			$origArray,
			$securedArray,
			array(),
			$controlData->getRequiredArray(),
			$dataObj->getFieldList(),
			$GLOBALS['TCA'][$theTable]['columns'],
			FALSE
		);

		foreach ($GLOBALS['TCA'][$theTable]['columns'] as $theField => $fieldConfig) {

			if (
				$fieldConfig['config']['internal_type'] == 'file' &&
				$fieldConfig['config']['allowed'] != '' &&
				$fieldConfig['config']['uploadfolder'] != ''
			) {
				$markerObj->addFileUploadMarkers(
					$theTable,
					$theField,
					$fieldConfig,
					$markerArray,
					$cmd,
					$cmdKey,
					$currentArray,
					$controlData->getMode() == MODE_PREVIEW
				);
			}
		}

		$templateCode =
			$markerObj->removeStaticInfoSubparts(
				$templateCode,
				$markerArray
			);
		$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE[' . $theTable . '][uid]" value="' . $currentArray['uid'] . '" />';

		if ($theTable != 'fe_users') {
			$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId . '[aC]" value="' . $authObj->authCode($origArray, $conf['setfixed.']['EDIT.']['_FIELDLIST']) . '" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="' . $prefixId . '[cmd]" value="edit" />';
		}

		$markerObj->addHiddenFieldsMarkers(
			$markerArray,
			$theTable,
			$controlData->getExtKey(),
			$prefixId,
			$cmdKey,
			$mode,
			$token,
			$conf[$cmdKey . '.']['useEmailAsUsername'],
			$conf[$cmdKey . '.']['fields'],
			$currentArray
		);

			// Avoid cleartext password in HTML source
		$markerArray['###FIELD_password###'] = '';
		$markerArray['###FIELD_password_again###'] = '';
		$deleteUnusedMarkers = TRUE;
		$content =
			$cObj->substituteMarkerArray(
				$templateCode,
				$markerArray,
				'',
				FALSE,
				$deleteUnusedMarkers
			);

		if ($mode != MODE_PREVIEW) {
			$form = $this->pibaseObj->pi_getClassName($theTable . '_form');
			$modData = $dataObj->modifyDataArrForFormUpdate($currentArray, $cmdKey);
			$fields = $dataObj->getFieldList() . ',' . $dataObj->getAdditionalUpdateFields();
			$fields = implode(',', array_intersect(explode(',', $fields), t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1)));
			$fields = $controlData->getOpenFields($fields);
			$updateJS =
				$cObj->getUpdateJS(
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
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted markers
	*/
	public function createScreen (
		&$markerArray,
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
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
		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

		$templateCode = $dataObj->getTemplateCode();
		$prefixId = $controlData->getPrefixId();
		$extKey = $controlData->getExtKey();
		$currentArray = array_merge($origArray, $dataArray);

		if ($theTable == 'fe_users') {
			if (!isset($currentArray['password'])) {
				$currentArray['password'] = '';
			}
			$currentArray['password_again'] = $currentArray['password'];
		}

		if ($conf['create']) {

				// Call all beforeConfirmCreate hooks before the record has been shown and confirmed
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey][$prefixId]['registrationProcess'] as $classRef) {
					$hookObj= t3lib_div::getUserObj($classRef);
					if (method_exists($hookObj,'registrationProcess_beforeConfirmCreate')) {
						$hookObj->registrationProcess_beforeConfirmCreate($dataArray, $controlData);
					}
				}
			}
			$currentArray = array_merge($currentArray, $dataArray);
			$key = ($cmd == 'invite') ? 'INVITE': 'CREATE';

			$bNeedUpdateJS = TRUE;
			if ($cmd == 'create' || $cmd == 'invite') {
				$subpartKey = '###TEMPLATE_' . $key . $markerObj->getPreviewLabel() . '###';
			} else {
				$bNeedUpdateJS = FALSE;
				if ($GLOBALS['TSFE']->loginUser) {
					$subpartKey = '###TEMPLATE_CREATE_LOGIN###';
				} else {
					$subpartKey = '###TEMPLATE_AUTH###';
				}
			}

			if ($bNeedUpdateJS) {
				$markerObj->addPasswordTransmissionMarkers($markerArray);
			}

			$templateCode = $cObj->getSubpart($templateCode, $subpartKey);
			$failure = t3lib_div::_GP('noWarnings') ? FALSE: $controlData->getFailure();

			if ($failure == FALSE) {
				$templateCode = $cObj->substituteSubpart(
					$templateCode,
					'###SUB_REQUIRED_FIELDS_WARNING###',
					''
				);
			}

			$templateCode =
				$this->removeRequired(
					$conf,
					$cObj,
					$controlData,
					$dataObj,
					$templateCode,
					$errorFieldArray,
					$failure
				);
			$markerArray =
				$markerObj->fillInMarkerArray(
					$markerArray,
					$currentArray,
					$securedArray,
					'',
					TRUE
				);
			$markerObj->addStaticInfoMarkers(
				$markerArray,
				$prefixId,
				$dataArray
			);
			$tcaObj->addTcaMarkers(
				$markerArray,
				$conf,
				$cObj,
				$langObj,
				$controlData,
				$dataArray,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable,
				$prefixId
			);

			foreach ($GLOBALS['TCA'][$theTable]['columns'] as $theField => $fieldConfig) {
				if (
					$fieldConfig['config']['internal_type'] == 'file' &&
					$fieldConfig['config']['allowed'] != '' &&
					$fieldConfig['config']['uploadfolder'] != ''
				) {
					$markerObj->addFileUploadMarkers(
						$theTable,
						$theField,
						$fieldConfig,
						$markerArray,
						$cmd,
						$cmdKey,
						$dataArray,
						$controlData->getMode() == MODE_PREVIEW
					);
				}
			}

			$markerObj->addLabelMarkers(
				$markerArray,
				$conf,
				$cObj,
				$controlData->getExtKey(),
				$theTable,
				$dataArray,
				$origArray,
				$securedArray,
				array(),
				$controlData->getRequiredArray(),
				$infoFields,
				$GLOBALS['TCA'][$theTable]['columns'],
				FALSE
			);

			$templateCode =
				$markerObj->removeStaticInfoSubparts(
					$templateCode,
					$markerArray
				);

			$markerObj->addHiddenFieldsMarkers(
				$markerArray,
				$theTable,
				$extKey,
				$prefixId,
				$cmdKey,
				$mode,
				$token,
				$conf[$cmdKey . '.']['useEmailAsUsername'],
				$conf[$cmdKey . '.']['fields'],
				$dataArray
			);

				// Avoid cleartext password in HTML source
			$markerArray['###FIELD_password###'] = '';
			$markerArray['###FIELD_password_again###'] = '';
			$deleteUnusedMarkers = TRUE;
			$content =
				$cObj->substituteMarkerArray(
					$templateCode,
					$markerArray,
					'',
					FALSE,
					$deleteUnusedMarkers
				);

			if ($mode != MODE_PREVIEW && $bNeedUpdateJS) {
				$fields = $dataObj->getFieldList() . ',' . $dataObj->getAdditionalUpdateFields();
				$fields = implode(',', array_intersect(explode(',', $fields), t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1)));	
				$fields = $controlData->getOpenFields($fields);
				$modData = $dataObj->modifyDataArrForFormUpdate($dataArray, $cmdKey);
				$form = $this->pibaseObj->pi_getClassName($theTable . '_form');
				$updateJS =
					$cObj->getUpdateJS(
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
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted markers
	*/
	public function editScreen (
		&$markerArray,
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
		$theTable,
		$prefixId,
		$dataArray,
		$origArray,
		$securedArray,
		$cmd,
		$cmdKey,
		$mode,
		$errorFieldArray,
		$token
	) {
		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

			// If editing is enabled
		if ($conf['edit']) {
			$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			if(
				$theTable != 'fe_users' &&
				$conf['setfixed.']['EDIT.']['_FIELDLIST']
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
				$origArray = $dataObj->parseIncomingData($origArray);
			}
			$aCAuth = $authObj->aCAuth($origArray, $conf['setfixed.']['EDIT.']['_FIELDLIST']);
			if (
				is_array($origArray) &&
				(
					($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) ||
					$aCAuth ||
					($theCode && !strcmp($authObj->authCode, $theCode))
				)
			) {
				$markerObj->setArray($markerArray);
				// Must be logged in OR be authenticated by the aC code in order to edit
				// If the recUid selects a record.... (no check here)
				if (
					!strcmp($authObj->authCode, $theCode) ||
					$aCAuth ||
					$cObj->DBmayFEUserEdit(
						$theTable,
						$origArray,
						$GLOBALS['TSFE']->fe_user->user,
						$conf['allowedGroups'],
						$conf['fe_userEditSelf']
					)
				) {
					// Display the form, if access granted.
					$content = $this->editForm(
						$markerArray,
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$tcaObj,
						$markerObj,
						$dataObj,
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
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$tcaObj,
						$markerObj,
						$dataObj,
						$dataObj->getTemplateCode(),
						'###TEMPLATE_NO_PERMISSIONS###',
						$markerArray,
						$dataArray,
						$theTable,
						$prefixId,
						$origArray,
						$securedArray
					);
				}
			} else {
				// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				$content = $this->getPlainTemplate(
					$conf,
					$cObj,
					$langObj,
					$controlData,
					$tcaObj,
					$markerObj,
					$dataObj,
					$dataObj->getTemplateCode(),
					'###TEMPLATE_AUTH###',
					$markerArray,
					$dataArray,
					$theTable,
					$prefixId,
					$dataObj->getOrigArray(),
					$securedArray
				);
			}
		} else {
			$content .= $langObj->getLL('internal_edit_option');
		}

		return $content;
	}	// editScreen

	/**
	* This is basically the preview display of delete
	*
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @return string  the template with substituted markers
	*/
	public function deleteScreen (
		$markerArray,
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
		$theTable,
		$dataArray,
		$origArray,
		$securedArray,
		$token
	) {
		if ($conf['delete']) {

			$extKey = $controlData->getExtKey();
			$prefixId = $controlData->getPrefixId();
			$templateCode = $dataObj->getTemplateCode();
			$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			// If deleting is enabled
			$origArray =
				$GLOBALS['TSFE']->sys_page->getRawRecord(
					$theTable,
					$dataObj->getRecUid()
				);
			$aCAuth = FALSE;
			if (is_array($origArray)) {
				$aCAuth =
					$authObj->aCAuth(
						$origArray,
						$conf['setfixed.']['DELETE.']['_FIELDLIST']
					);
			}
			if (
				($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) ||
				$aCAuth
			) {
				// Must be logged in OR be authenticated by the aC code in order to delete

				// If the recUid selects a record.... (no check here)
				if (is_array($origArray)) {
					$bMayEdit =
						$cObj->DBmayFEUserEdit(
							$theTable,
							$origArray,
							$GLOBALS['TSFE']->fe_user->user,
							$conf['allowedGroups'],
							$conf['fe_userEditSelf']
						);

					if ($aCAuth || $bMayEdit) {
						$markerArray = $markerObj->getArray();
						// Display the form, if access granted.
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $prefixId . '[rU]" value="' .  $dataObj->getRecUid() . '" />';
						$markerArray['###BACK_URL###'] = ($controlData->getBackURL() ? $controlData->getBackURL() : $cObj->getTypoLink_URL($conf['loginPID'] . ',' . $GLOBALS['TSFE']->type));
						$markerObj->addGeneralHiddenFieldsMarkers($markerArray, 'delete', $token);

						$markerObj->setArray($markerArray);
						$content = $this->getPlainTemplate(
							$conf,
							$cObj,
							$langObj,
							$controlData,
							$tcaObj,
							$markerObj,
							$dataObj,
							$templateCode,
							'###TEMPLATE_DELETE_PREVIEW###',
							$markerArray,
							$dataArray,
							$theTable,
							$prefixId,
							$origArray,
							$securedArray
						);
					} else {
						// Else display error, that you could not edit that particular record...
						$content = $this->getPlainTemplate(
							$conf,
							$cObj,
							$langObj,
							$controlData,
							$tcaObj,
							$markerObj,
							$dataObj,
							$templateCode,
							'###TEMPLATE_NO_PERMISSIONS###',
							$markerArray,
							$dataArray,
							$theTable,
							$prefixId,
							$origArray,
							$securedArray
						);
					}
				}
			} else {
				// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				if ( $theTable == 'fe_users' ) {
					$content = $this->getPlainTemplate(
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$tcaObj,
						$markerObj,
						$dataObj,
						$templateCode,
						'###TEMPLATE_AUTH###',
						$markerArray,
						$origArray,
						$theTable,
						$prefixId,
						'',
						$securedArray
					);
				} else {
					$content = $this->getPlainTemplate(
						$conf,
						$cObj,
						$langObj,
						$controlData,
						$tcaObj,
						$markerObj,
						$dataObj,
						$templateCode,
						'###TEMPLATE_NO_PERMISSIONS###',
						$markerArray,
						$origArray,
						$theTable,
						$prefixId,
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
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param string  $subpartMarker: the template subpart marker
	* @param array  $row: the data array, if any
	* @return string  the template with substituted parts and markers
	*/
	public function getPlainTemplate (
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
		$templateCode,
		$subpartMarker,
		$markerArray,
		$origArray,
		$theTable,
		$prefixId,
		$row = '',
		$securedArray,
		$bCheckEmpty = TRUE,
		$failure = ''
	) {
		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

		$templateCode = $cObj->getSubpart($templateCode, $subpartMarker);
		$row = is_array($row) ? $row : array();
		if ($templateCode != '') {
				// Remove non-included fields
			$templateCode =
				$this->removeRequired(
					$conf,
					$cObj,
					$controlData,
					$dataObj,
					$templateCode,
					explode(',', $failure),
					$failure
				);
			$markerArray =
				$markerObj->fillInMarkerArray(
					$markerArray,
					$row,
					$securedArray,
					''
				);
			$markerObj->addStaticInfoMarkers(
				$markerArray,
				$prefixId,
				$row
			);
			$cmd = $controlData->getCmd();
			$cmdKey = $controlData->getCmdKey();
			$theTable = $controlData->getTable();
			$tcaObj->addTcaMarkers(
				$markerArray,
				$conf,
				$cObj,
				$langObj,
				$controlData,
				$row,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable,
				$prefixId,
				TRUE
			);
			$markerObj->addLabelMarkers(
				$markerArray,
				$conf,
				$cObj,
				$controlData->getExtKey(),
				$theTable,
				$row,
				$origArray,
				$securedArray,
				array(),
				$controlData->getRequiredArray(),
				$dataObj->getFieldList(),
				$GLOBALS['TCA'][$theTable]['columns'],
				FALSE
			);
			$templateCode =
				$markerObj->removeStaticInfoSubparts(
					$templateCode,
					$markerArray
				);

				// Avoid cleartext password in HTML source
			$markerArray['###FIELD_password###'] = '';
			$markerArray['###FIELD_password_again###'] = '';
			$deleteUnusedMarkers = TRUE;

			$result =
				$cObj->substituteMarkerArray(
					$templateCode,
					$markerArray,
					'',
					FALSE,
					$deleteUnusedMarkers
				);
		} else if ($bCheckEmpty) {
			$langObj = t3lib_div::getUserObj('&tx_srfeuserregister_lang');
			$errorText = $langObj->getLL('internal_no_subtemplate');
			$result = sprintf($errorText, $subpartMarker);
		}
		return $result;
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
	* @param array $controlData: the object of the control data
	* @param string  $templateCode: the content of the HTML template
	* @param array  $errorFieldArray: array of field with errors (former $dataObj->inError[$theField])
	* @param string  $failure: the list of fields with errors
	* @return string  the template with susbstituted parts
	*/
	public function removeRequired (
		$conf,
		$cObj,
		$controlData,
		$dataObj,
		$templateCode,
		$errorFieldArray,
		$failure = ''
	) {
		$cmdKey = $controlData->getCmdKey();
		$requiredArray = $controlData->getRequiredArray();
		$includedFields = t3lib_div::trimExplode(',', $conf[$cmdKey . '.']['fields'], 1);

		if (
			$controlData->getFeUserData('preview') &&
			!in_array('username', $includedFields)
		) {
			$includedFields[] = 'username';
		}

		$infoFields = explode(',', $dataObj->getFieldList());
		if (!is_array($infoFields)) {
			return FALSE;
		}
		$specialFields = explode(',', $dataObj->getSpecialFieldList());
		if (is_array($specialFields) && count($specialFields)) {
			$infoFields = array_merge($infoFields, $specialFields);
		}

		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$infoFields = array_merge(
				$infoFields,
				array(
					'module_sys_dmail_category',
					'module_sys_dmail_newsletter',
					'module_sys_dmail_html',
				)
			);
			$includedFields = array_diff(
				$includedFields,
				array(
					'module_sys_dmail_category',
					'module_sys_dmail_newsletter',
				)
			);
		}

		if (!$controlData->useCaptcha($conf, $cmdKey)) {
			$templateCode = $cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_captcha_response###', '');
		}
			// Honour Address List (tt_address) configuration setting
		if (
			$controlData->getTable() == 'tt_address' &&
			t3lib_extMgm::isLoaded('tt_address') &&
			isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address'])
		) {
			$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_address']);
			if (is_array($extConf) && $extConf['disableCombinedNameField'] == '1') {
				$templateCode = $cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_name###', '');
			}
		}

		foreach ($infoFields as $k => $theField) {

				// Remove field required subpart, if field is not required
			if (in_array(trim($theField), $requiredArray) ) {
				if (!t3lib_div::inList($failure, $theField)) {
					$templateCode =
						$cObj->substituteSubpart(
							$templateCode,
							'###SUB_REQUIRED_FIELD_' . $theField . '###',
							''
						);
					$templateCode =
						$cObj->substituteSubpart(
							$templateCode,
							'###SUB_ERROR_FIELD_' . $theField . '###',
							''
						);
				} else if (!$errorFieldArray[$theField]) {
					$templateCode =
						$cObj->substituteSubpart(
							$templateCode,
							'###SUB_ERROR_FIELD_' . $theField . '###',
							''
						);
				}
			} else {
					// Remove field included subpart, if field is not included and is not in failure list
				if (
					!in_array(trim($theField), $includedFields) &&
					!t3lib_div::inList($failure, $theField)
				) {
					$templateCode =
						$cObj->substituteSubpart(
							$templateCode,
							'###SUB_INCLUDED_FIELD_' . $theField . '###',
							''
						);
				} else {
					$templateCode = $cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_' . $theField . '###', '');
					if (!t3lib_div::inList($failure, $theField)) {
						$templateCode = $cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_' . $theField . '###', '');
					}

					if (
						is_array($conf['parseValues.']) &&
						strstr($conf['parseValues.'][$theField], 'checkArray')
					) {
						$listOfCommands = t3lib_div::trimExplode(',', $conf['parseValues.'][$theField], 1);
						foreach($listOfCommands as $cmd) {
							$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
							$theCmd = trim($cmdParts[0]);
							switch($theCmd) {
								case 'checkArray':
									$positions = t3lib_div::trimExplode(';', $cmdParts[1]);
									for($i = 0; $i < 10; $i++) {
										if(!in_array($i, $positions)) {
											$templateCode = $cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_' . $theField . '_' . $i . '###', '');
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
	 * Determine what template subpart should be used atfer the last save operation
	 *
	 * @param string $cmd: the cmd that was executed
	 * @param string $cmdKey: the command key that was use
	 * @param boolean $bCustomerConfirmsMode =
	 * 			($cmd == '' || $cmd == 'create') &&
	 *			($cmdKey != 'edit') &&
	 *			$conf['enableAdminReview'] &&
	 *			($conf['enableEmailConfirmation'] || $conf['infomail'])
	 * @param boolean $bSetfixed =
	 *				This is the case where the user or admin has to confirm
	 *			$conf['enableEmailConfirmation'] ||
	 *			($this->theTable == 'fe_users' && $conf['enableAdminReview']) ||
	 *			$conf['setfixed']
	 * @param boolean $bCreateReview =
	 *				This is the case where the user does not have to confirm, but has to wait for admin review
	 *				This applies only on create ($bDefaultMode) and to fe_users
	 *				$bCreateReview implies $bSetfixed
	 *			!$conf['enableEmailConfirmation'] &&
	 *			$conf['enableAdminReview']
	 * @return boolean or string
	 */
	public function getKeyAfterSave (
		$cmd,
		$cmdKey,
		$bCustomerConfirmsMode,
		$bSetfixed,
		$bCreateReview
	) {
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
				} else if ($bSetfixed) {
					$result = SETFIXED_PREFIX . 'CREATE';

					if ($bCreateReview) {
						$result = 'CREATE' . SAVED_SUFFIX . '_REVIEW';
					} else if ($bCustomerConfirmsMode) {
						$result .= '_REVIEW';
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
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param string $theTable: the table in use
	* @param string  $subpartMarker: the template subpart marker
	* @param array  $row: the data array, if any
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @return string  the template with substituted parts and markers
	*/
	public function afterSave (
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
		$setfixedObj,
		$theTable,
		$prefixId,
		$dataArray,
		$origArray,
		$securedArray,
		$cmd,
		$cmdKey,
		$key,
		$templateCode,
		&$markerArray,
		$errorFieldArray,
		&$content
	) {
		$errorContent = '';

			// Display confirmation message
		$subpartMarker = '###TEMPLATE_' . $key . '###';
		$templateCode = $cObj->getSubpart($templateCode, $subpartMarker);
		$dataArray = is_array($dataArray) ? $$dataArray : array();
		if ($templateCode) {
				// Remove non-included fields
			$templateCode =
				$this->removeRequired(
					$conf,
					$cObj,
					$controlData,
					$dataObj,
					$templateCode,
					$errorFieldArray
				);
			$markerArray =
				$markerObj->fillInMarkerArray(
					$markerArray,
					$dataArray,
					$securedArray,
					'',
					TRUE,
					'FIELD_',
					TRUE
				);
			$markerObj->addStaticInfoMarkers(
				$markerArray,
				$prefixId,
				$dataArray
			);

			$tcaObj->addTcaMarkers(
				$markerArray,
				$conf,
				$cObj,
				$langObj,
				$controlData,
				$dataArray,
				$origArray,
				$cmd,
				$cmdKey,
				$theTable,
				$prefixId,
				TRUE
			);

			$markerObj->addLabelMarkers(
				$markerArray,
				$conf,
				$cObj,
				$controlData->getExtKey(),
				$theTable,
				$dataArray,
				$origArray,
				$securedArray,
				array(),
				$controlData->getRequiredArray(),
				$dataObj->getFieldList(),
				$GLOBALS['TCA'][$theTable]['columns'],
				FALSE
			);

			if (
				$cmdKey == 'create' &&
				!$conf['enableEmailConfirmation'] &&
				!$conf['enableAutoLoginOnCreate']
			) {
				$markerObj->addPasswordTransmissionMarkers($markerArray);
			}

			if (isset($conf[$cmdKey . '.']['marker.'])) {
				if ($conf[$cmdKey . '.']['marker.']['computeUrl'] == '1') {
					$this->setfixedObj->computeUrl(
						$cmdKey,
						$prefixId,
						$cObj,
						$controlData,
						$markerArray,
						$conf['setfixed.'],
						$dataArray,
						$theTable,
						$conf['useShortUrls'],
						$conf['edit.']['setfixed'],
						$conf['confirmType']
					);
				}
			}

			$uppercase = FALSE;
			$deleteUnusedMarkers = TRUE;
			$content = $cObj->substituteMarkerArray(
				$templateCode,
				$markerArray,
				'',
				$uppercase,
				$deleteUnusedMarkers
			);
		} else {
			$errorText = $langObj->getLL('internal_no_subtemplate');
			$errorContent = sprintf($errorText, $subpartMarker);
		}
		return $errorContent;
	}

	/**
	 * Removes HTML comments contained in input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with HTML comment removed
	 */
	public function removeHTMLComments ($content) {
		$result = preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
		return $result;
	}

	/**
	 * Replaces HTML br tags with line feeds in input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with HTML br tags replaced
	 */
	public function replaceHTMLBr ($content) {
		$result = preg_replace('/<br\s?\/?>/', LF, $content);
		return $result;
	}

	/**
	 * Removes all HTML tags from input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with HTML tags removed
	 */
	public function removeHtmlTags ($content) {
			// Preserve <http://...> constructs
		$result = str_replace('<http', '###http', $content);
		$result = strip_tags($result);
		$result = str_replace('###http', '<http', $result);
		return $result;
	}

	/**
	 * Removes superfluous line feeds from input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with superfluous fine feeds removed
	 */
	public function removeSuperfluousLineFeeds ($content) {
		$result = preg_replace('/[' . preg_quote(LF) . ']{3,}/', LF . LF, $content);
		return $result;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php']);
}

?>