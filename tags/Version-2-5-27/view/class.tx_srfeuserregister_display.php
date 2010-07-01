<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
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
 * @author	Kasper Skaarhoj <kasper2008@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_display {
	var $conf = array();
	var $config = array();
	var $data;
	var $marker;
	var $tca;
	var $control;
	var $controlData;
	var $cObj;


	function init (&$cObj, &$conf, &$config, &$data, &$marker, &$tca, &$control)	{
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
	function editForm (&$markerArray, $theTable, $dataArray, $origArray, $cmd, $cmdKey, $mode, $errorFieldArray, $token) {
		global $TSFE;

		$currentArray = array_merge($origArray, $dataArray);
		$subpart = '###TEMPLATE_EDIT'.$this->marker->getPreviewLabel().'###';
		$templateCode = $this->cObj->getSubpart($this->data->getTemplateCode(),$subpart);

		if (!$this->conf['linkToPID'] || !$this->conf['linkToPIDAddButton'] || !($mode == MODE_PREVIEW || !$this->conf['edit.']['preview'])) {
			$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_LINKTOPID_ADD_BUTTON###', '');
		}

		$failure = t3lib_div::_GP('noWarnings') ? '': $this->controlData->getFailure();
		if (!$failure) {
			$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
		}
		$this->marker->addMd5EventsMarkers($markerArray, TRUE, $this->controlData->getUseMd5Password());
		$templateCode = $this->removeRequired($templateCode, $errorFieldArray, $failure);
		$currentArray['password_again'] = $currentArray['password'];
		$markerArray = $this->marker->fillInMarkerArray($markerArray, $currentArray, '', TRUE);

		$this->marker->addStaticInfoMarkers($markerArray, $currentArray);
		$this->tca->addTcaMarkers($markerArray, $currentArray, $origArray, $cmd, $cmdKey, $theTable, TRUE);
		$this->tca->addTcaMarkers($markerArray, $currentArray, $origArray, $cmd, $cmdKey, $theTable);

		$this->marker->addLabelMarkers($markerArray, $theTable, $currentArray, $origArray, array(), $this->controlData->getRequiredArray(), $this->data->getFieldList(), $this->tca->TCA['columns'], FALSE);
		$this->marker->addFileUploadMarkers('image', $markerArray, $cmd, $cmdKey, $currentArray, $this->controlData->getMode() == MODE_PREVIEW);
		$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
		$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][uid]" value="'.$currentArray['uid'].'" />';

		if ($theTable != 'fe_users') {
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$prefixId.'[aC]" value="'.$authObj->authCode($origArray,$this->conf['setfixed.']['EDIT.']['_FIELDLIST']).'" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$prefixId . '[cmd]" value="edit" />';
		} elseif ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $this->conf['templateStyle'] != 'css-styled') {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][username]" value="'.$currentArray['username'].'" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][email]" value="'.$currentArray['email'].'" />';
		}
		$this->marker->addHiddenFieldsMarkers($markerArray, $cmdKey, $mode, $token, $currentArray);
		$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		if ($this->conf['templateStyle'] != 'css-styled' || $mode != MODE_PREVIEW) {

			$form = tx_div2007_alpha::getClassName($theTable.'_form',$this->controlData->getPrefixId());
			$modData = $this->data->modifyDataArrForFormUpdate($currentArray, $cmdKey);
			$fields = $this->data->getFieldList().$this->data->getAdditionalUpdateFields();
			$fields = $this->controlData->getOpenFields($fields);
			$updateJS = $this->cObj->getUpdateJS($modData, $form, 'FE['.$theTable.']', $fields);
			$content .= $updateJS;
			$TSFE->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $TSFE->absRefPrefix . t3lib_extMgm::siteRelPath('sr_feuser_register') .'scripts/jsfunc.updateform.js"></script>';
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
	function createScreen (&$markerArray, $cmd, $cmdKey, $mode, $theTable, $dataArray, $origArray, $infoFields, $errorFieldArray, $token) {
		global $TSFE;

		$templateCode = $this->data->getTemplateCode();
		$prefixId = $this->controlData->getPrefixId();
		$extKey = $this->controlData->getExtKey();
		$currentArray = array_merge($origArray, $dataArray);

		if ($theTable == 'fe_users')	{
			if (!isset($currentArray['password']))	{
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
			$this->marker->addMd5EventsMarkers($markerArray, FALSE, $this->controlData->getUseMd5Password());

			$bNeedUpdateJS = TRUE;
			if ($cmd == 'create' || $cmd == 'invite')	{
				$subpartKey = '###TEMPLATE_' . $key . $this->marker->getPreviewLabel() . '###';
			} else {
				$bNeedUpdateJS = FALSE;
				if ($GLOBALS['TSFE']->loginUser)	{
					$subpartKey = '###TEMPLATE_CREATE_LOGIN###';
				} else {
					$subpartKey = '###TEMPLATE_AUTH###';
				}
			}
			$templateCode = $this->cObj->getSubpart($templateCode, $subpartKey);
			$failure = t3lib_div::_GP('noWarnings') ? FALSE: $this->controlData->getFailure();
			if ($failure == FALSE)	{
				$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
			}
			$templateCode = $this->removeRequired($templateCode, $errorFieldArray, $failure);
			$markerArray = $this->marker->fillInMarkerArray($markerArray, $currentArray, '',TRUE);
			$this->marker->addStaticInfoMarkers($markerArray, $dataArray);
			$this->tca->addTcaMarkers($markerArray, $dataArray, $origArray, $cmd, $cmdKey, $theTable);
			$this->marker->addFileUploadMarkers('image', $markerArray, $cmd, $cmdKey, $dataArray, $this->controlData->getMode() == MODE_PREVIEW);
			$this->marker->addLabelMarkers($markerArray, $theTable, $dataArray, $origArray, array(), $this->controlData->getRequiredArray(), $infoFields, $this->tca->TCA['columns'], FALSE);
			$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
			$this->marker->addHiddenFieldsMarkers($markerArray, $cmdKey, $mode, $token, $dataArray);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);

			if ($mode != MODE_PREVIEW && $bNeedUpdateJS) {
				$fields = $this->data->fieldList . $this->data->additionalUpdateFields;
				$fields = $this->controlData->getOpenFields($fields);
				$modData = $this->data->modifyDataArrForFormUpdate($dataArray, $cmdKey);
				$form = tx_div2007_alpha::getClassName($theTable.'_form',$this->controlData->getPrefixId());
				$updateJS = $this->cObj->getUpdateJS($modData, $form, 'FE['.$theTable.']', $fields);
				$content .= $updateJS;
				$TSFE->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $TSFE->absRefPrefix . t3lib_extMgm::siteRelPath('sr_feuser_register') .'scripts/jsfunc.updateform.js"></script>';
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
	function editScreen (&$markerArray, $theTable, $dataArray, $origArray, $cmd, $cmdKey, $mode, $errorFieldArray, $token) {
		global $TSFE;

			// If editing is enabled
		if ($this->conf['edit']) {
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			if($theTable != 'fe_users' && $this->conf['setfixed.']['EDIT.']['_FIELDLIST']) {
				$fD = t3lib_div::_GP('fD', 1);
				$fieldArr = array();
				if (is_array($fD)) {
					foreach($fD as $field => $value) {
						$origArray[$field] = rawurldecode($value);
						$fieldArr[] = $field;
					}
				}
				$theCode = $authObj->setfixedHash($origArray, $origArray['_FIELDLIST']);
			}
			if (is_array($origArray))	{
				$origArray = $this->data->parseIncomingData($origArray);
			}

			$aCAuth = $authObj->aCAuth($origArray,$this->conf['setfixed.']['EDIT.']['_FIELDLIST']);
			if (
				is_array($origArray) &&
				( ($theTable == 'fe_users' && $TSFE->loginUser) || $aCAuth || $theCode && !strcmp($authObj->authCode, $theCode) )
			) {
				$this->marker->addMd5EventsMarkers($markerArray, TRUE, $this->controlData->getUseMd5Password());
				$this->marker->setArray($markerArray);
				// Must be logged in OR be authenticated by the aC code in order to edit
				// If the recUid selects a record.... (no check here)
				if ( !strcmp($authObj->authCode, $theCode) || $aCAuth || $this->cObj->DBmayFEUserEdit($theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
					// Display the form, if access granted.
					$content = $this->editForm(
						$markerArray,
						$theTable,
						$dataArray,
						$origArray,
						$cmd,
						$cmdKey,
						$mode,
						$errorFieldArray,
						$token
					);
				} else {
					// Else display error, that you could not edit that particular record...
					$content = $this->getPlainTemplate($this->data->getTemplateCode(), '###TEMPLATE_NO_PERMISSIONS###', $markerArray, $dataArray, $origArray);
				}
			} else {
				// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				$content = $this->getPlainTemplate($this->data->getTemplateCode(), '###TEMPLATE_AUTH###', $markerArray, $dataArray, $this->data->getOrigArray());
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
	function deleteScreen ($markerArray, $theTable, $dataArray, $origArray, $token) {

		if ($this->conf['delete']) {
			$prefixId = $this->controlData->getPrefixId();
			$templateCode = $this->data->getTemplateCode();
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			// If deleting is enabled
			$origArray = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $this->data->getRecUid());
			$aCAuth = $authObj->aCAuth($origArray,$this->conf['setfixed.']['DELETE.']['_FIELDLIST']);

			if ( ($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $aCAuth) {
				// Must be logged in OR be authenticated by the aC code in order to delete

				// If the recUid selects a record.... (no check here)
				if (is_array($origArray)) {
					$bMayEdit = $this->cObj->DBmayFEUserEdit($theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf']);

					if ($aCAuth || $bMayEdit) {
						$markerArray = $this->marker->getArray();
						// Display the form, if access granted.
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="rU" value="'.$this->data->getRecUid().'" />';
						if ($theTable != 'fe_users') {
							$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$prefixId .'[aC]" value="'.$authObj->authCode($origArray, $this->conf['setfixed.']['DELETE.']['_FIELDLIST']).'" />';
						}
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$prefixId .'[cmd]" value="delete" />';
						$this->marker->addFormToken($markerArray, $token);

						$this->marker->setArray($markerArray);
						$content = $this->getPlainTemplate($templateCode, '###TEMPLATE_DELETE_PREVIEW###', $markerArray, $dataArray, $origArray);
					} else {
						// Else display error, that you could not edit that particular record...
						$content = $this->getPlainTemplate($templateCode, '###TEMPLATE_NO_PERMISSIONS###', $markerArray, $dataArray, $origArray);
					}
				}
			} else {
				// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				if ( $theTable == 'fe_users' ) {
					$content = $this->getPlainTemplate($templateCode, '###TEMPLATE_AUTH###', $markerArray, $origArray);
				} else {
					$content = $this->getPlainTemplate($templateCode, '###TEMPLATE_NO_PERMISSIONS###', $markerArray, $origArray);
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
	function getPlainTemplate ($templateCode, $subpartMarker, $markerArray, $origArray, $row='', $bCheckEmpty=TRUE) {

		$templateCode = $this->cObj->getSubpart($templateCode, $subpartMarker);

		if ($templateCode != '')	{
			if (is_array($row))	{
				$markerArray = $this->marker->fillInMarkerArray($markerArray, $row, '');
			}
			$this->marker->addStaticInfoMarkers($markerArray, $row);
			$cmd = $this->controlData->getCmd();
			$cmdKey = $this->controlData->getCmdKey();
			$theTable = $this->controlData->getTable();
			$this->tca->addTcaMarkers($markerArray, $row, $origArray, $cmd, $cmdKey, $theTable, TRUE);
			$this->marker->addLabelMarkers($markerArray, $theTable, $row, $origArray, array(), $this->controlData->getRequiredArray(), $this->data->getFieldList(), $this->tca->TCA['columns'], FALSE);
			$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
			$rc = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
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
	function removeRequired ($templateCode, $errorFieldArray, $failure='') {
		$cmdKey = $this->controlData->getCmdKey();
		$requiredArray = $this->controlData->getRequiredArray();
		$includedFields = t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1);
		if ($this->controlData->getFeUserData('preview') && !in_array('username', $includedFields)) {
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

		foreach($infoFields as $k => $theField) {
			if (in_array(trim($theField), $requiredArray) ) {
				if (!t3lib_div::inList($failure, $theField)) {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$theField.'###', '');
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$theField.'###', '');
				} else if (!$errorFieldArray[$theField]) {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$theField.'###', '');
				}
			} else {
				if (!in_array(trim($theField), $includedFields)) {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_'.$theField.'###', '');
				} else {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$theField.'###', '');
					if (!t3lib_div::inList($failure, $theField)) {
						$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$theField.'###', '');
					}
					if (is_array($this->conf['parseValues.']) && strstr($this->conf['parseValues.'][$theField],'checkArray')) {
						$listOfCommands = t3lib_div::trimExplode(',', $this->conf['parseValues.'][$theField], 1);
						foreach($listOfCommands as $cmd) {
							$cmdParts = preg_split('/\[|\]/', $cmd); // Point is to enable parameters after each command enclosed in brackets [..]. These will be in position 1 in the array.
							$theCmd = trim($cmdParts[0]);
							switch($theCmd) {
								case 'checkArray':
									$positions = t3lib_div::trimExplode(';', $cmdParts[1]);
									for($i=0; $i<10; $i++) {
										if(!in_array($i, $positions)) {
											$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_INCLUDED_FIELD_'.$theField.'_'.$i.'###', '');
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


	function &removeHTMLComments ($content) {
		$rc = preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/','',$content);
		return $rc;
	}


	function replaceHTMLBr ($content) {
		$rc = preg_replace('/<br\s?\/>/',chr(10),$content);
		return $rc;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php']);
}
?>
