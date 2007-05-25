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
 * display functions
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



class tx_srfeuserregister_display {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $data;
	var $marker;
	var $tca;
	var $control;
	var $auth;
	
	var $extKey;  // The extension key.
	var $setfixedEnabled;
	var $prefixId;
	var $cObj;


	function init(&$pibase, &$conf, &$config, &$data, &$marker, &$tca, &$control, &$auth)	{
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->tca = &$tca;
		$this->control = &$control;
		$this->auth = &$auth;

		$this->extKey = $pibase->extKey;
		$this->setfixedEnabled = $pibase->setfixedEnabled;
		$this->prefixId = $pibase->prefixId;
		$this->cObj = &$pibase->cObj;
	}


	/**
	* Displays the record update form
	*
	* @param array  $origArr: the array coming from the database
	* @return string  the template with substituted markers
	*/
	function editForm($origArr,$cmd,$cmdKey) {
		global $TSFE;

		$dataArray = $this->data->getDataArray();
		$theTable = $this->data->getTable();
		$currentArr = array_merge($origArr, $dataArray);
		foreach ($currentArr AS $key => $value) {
			// If the type is check, ...
			if (($this->tca->TCA['columns'][$key]['config']['type'] == 'check') && is_array($this->tca->TCA['columns'][$key]['config']['items'])) {
				if(isset($dataArray[$key]) && !$dataArray[$key]) {
					$currentArr[$key] = 0;
				}
			}
		}
		$templateCode = $this->cObj->getSubpart($this->data->templateCode, '###TEMPLATE_EDIT'.$this->marker->getPreviewLabel().'###');
		if (!$this->conf['linkToPID'] || !$this->conf['linkToPIDAddButton'] || !($this->control->getMode() == MODE_PREVIEW || !$this->conf['edit.']['preview'])) {
			$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_LINKTOPID_ADD_BUTTON###', '');
		}

		$failure = t3lib_div::_GP('noWarnings') ? '': $this->data->getFailure();
		if (!$failure) {
			$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
		}
		$markerArray = $this->marker->getArray();
		$templateCode = $this->removeRequired($templateCode, $failure);
		$markerArray = $this->cObj->fillInMarkerArray($markerArray, $currentArr, '',TRUE, 'FIELD_', TRUE);
		$this->marker->addStaticInfoMarkers($markerArray, $currentArr);
		$this->tca->addTcaMarkers($markerArray, $currentArr, true);
		$this->tca->addTcaMarkers($markerArray, $currentArr);
		$this->marker->addLabelMarkers($markerArray, $currentArr, $this->control->getRequiredArray());
		$this->marker->addFileUploadMarkers('image', $markerArray, $cmd, $cmdKey, $currentArr);
		$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
		$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][uid]" value="'.$currentArr['uid'].'" />';
		if ($theTable != 'fe_users') {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->auth->authCode($origArr).'" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="'.$this->prefixId.'[cmd]" value="edit" />';
		} elseif ($this->conf[$cmdKey.'.']['useEmailAsUsername'] && $this->conf['templateStyle'] != 'css-styled') {
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][username]" value="'.$currentArr['username'].'" />';
			$markerArray['###HIDDENFIELDS###'] .= chr(10) . '<input type="hidden" name="FE['.$theTable.'][email]" value="'.$currentArr['email'].'" />';
		}
		$this->marker->addHiddenFieldsMarkers($markerArray, $currentArr);
		$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
		if ($this->conf['templateStyle'] != 'css-styled' || !$this->control->getMode() == MODE_PREVIEW) {
			if ($this->conf['templateStyle'] == 'css-styled') {
				$form = $this->pibase->pi_getClassName($theTable.'_form');
			} else {
				$form = $theTable.'_form';
			}
			$modData = $this->data->modifyDataArrForFormUpdate($currentArr);
			$updateJS = $this->cObj->getUpdateJS($modData, $form, 'FE['.$theTable.']', $this->data->fieldList.$this->data->additionalUpdateFields);
			$content .= $updateJS; 
			if ($this->conf['templateStyle'] == 'css-styled') {
				$TSFE->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $TSFE->absRefPrefix . t3lib_extMgm::siteRelPath('sr_feuser_register') .'scripts/jsfunc.updateform.js"></script>';
			}
		}
		return $content;
	}	// editForm


	/**
	* Generates the record creation form
	*
	* @return string  the template with substituted markers
	*/
	function createScreen($cmd = 'create') {
		global $TSFE;
		
		if ($this->conf['create']) {
			$cmdKey = $this->control->getCmdKey();
			$theTable = $this->data->getTable();
			$dataArray = $this->data->getDataArray();

				// <Pieter Verstraelen added registrationProcess hooks>
				// Call all beforeConfirmCreate hooks before the record has been shown and confirmed
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['registrationProcess'] as $classRef) {
					$hookObj= &t3lib_div::getUserObj($classRef);
					if (method_exists($hookObj,'registrationProcess_beforeConfirmCreate')) {
						$hookObj->registrationProcess_beforeConfirmCreate($dataArray, $this);
					}
				}
			}
				// </Pieter Verstraelen added registrationProcess hooks>

			$key = ($cmd == 'invite') ? 'INVITE': 'CREATE';
			$markerArray = $this->marker->getArray();
			$this->marker->addMd5EventsMarkers($markerArray, 'create');
			// $this->marker->setArray($markerArray);
			$templateCode = $this->cObj->getSubpart($this->data->templateCode, ((!($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $cmd == 'invite') ? '###TEMPLATE_'.$key.$this->marker->getPreviewLabel().'###':'###TEMPLATE_CREATE_LOGIN###'));

			$failure = t3lib_div::_GP('noWarnings') ? FALSE: $this->data->getFailure();
			if (!$failure) $templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELDS_WARNING###', '');
			$templateCode = $this->removeRequired($templateCode, $failure);
			$markerArray = $this->cObj->fillInMarkerArray($markerArray, $dataArray, '',TRUE, 'FIELD_', TRUE);
			$this->marker->addStaticInfoMarkers($markerArray, $dataArray);
			$this->tca->addTcaMarkers($markerArray, $dataArray);
			$this->marker->addFileUploadMarkers('image', $markerArray, $cmd, $cmdKey, $dataArray);
			$this->marker->addLabelMarkers($markerArray, $dataArray, $this->control->getRequiredArray());
			$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
			$this->marker->addHiddenFieldsMarkers($markerArray, $dataArray);
			$content = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
			if ($this->conf['templateStyle'] != 'css-styled' || !$this->control->getMode() == MODE_PREVIEW) {
				if ($this->conf['templateStyle'] == 'css-styled') {
					$form = $this->pibase->pi_getClassName($theTable.'_form');
				} else {
					$form = $theTable.'_form';
				}
				$content .= $this->cObj->getUpdateJS($this->data->modifyDataArrForFormUpdate($dataArray), $form, 'FE['.$theTable.']', $this->data->fieldList.$this->data->additionalUpdateFields);
				if ($this->conf['templateStyle'] == 'css-styled') {
					$TSFE->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . $TSFE->absRefPrefix . t3lib_extMgm::siteRelPath('sr_feuser_register') .'scripts/jsfunc.updateform.js"></script>';
				}
			}
		}
		return $content;
	} // createScreen

	
	/**
	* Checks if the edit form may be displayed; if not, a link to login
	*
	* @return string  the template with substituted markers
	*/
	function editScreen($cmd, $cmdKey) {
		global $TSFE;

		if ($this->conf['edit']) {
			$theTable = $this->data->getTable();
			$dataArray = $this->data->getDataArray();
			// If editing is enabled
			$origArr = $TSFE->sys_page->getRawRecord($theTable, $dataArray['uid']?$dataArray['uid']:$this->data->getRecUid());
			if( $theTable != 'fe_users' && $this->conf['setfixed.']['edit.']['_FIELDLIST']) {
				$fD = t3lib_div::_GP('fD', 1);
				$fieldArr = array();
				if (is_array($fD)) {
					reset($fD);
					while (list($field, $value) = each($fD)) {
						$origArr[$field] = rawurldecode($value);
						$fieldArr[] = $field;
					}
				}
				$theCode = $this->auth->setfixedHash($origArr, $origArr['_FIELDLIST']);
			}
			if (is_array($origArr))	{
				$origArr = $this->data->parseIncomingData($origArr);
			}

			if (is_array($origArr) && ( ($theTable == 'fe_users' && $TSFE->loginUser) || $this->auth->aCAuth($origArr) || !strcmp($this->auth->authCode, $theCode) ) ) {
				// Must be logged in OR be authenticated by the aC code in order to edit
				// If the recUid selects a record.... (no check here)
				$markerArray = '';
				$this->marker->addMd5EventsMarkers($markerArray, 'edit');
				$this->marker->setArray($markerArray);
				if ( !strcmp($this->auth->authCode, $theCode) || $this->auth->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
					// Display the form, if access granted.
					$content = $this->editForm($origArr, $cmd, $cmdKey);
				} else {
					// Else display error, that you could not edit that particular record...
					$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');
				}
			} else {
				// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				$content = $this->getPlainTemplate('###TEMPLATE_AUTH###');
			}
		} else {
			$content .= 'Edit-option is not set in TypoScript';
		}
		return $content;
	}	// editScreen



	/**
		* This is basically the preview display of delete
		*
		* @return string  the template with substituted markers
		*/
	function deleteScreen() {
		if ($this->conf['delete']) {
			$theTable = $this->data->getTable();

			// If deleting is enabled
			$origArr = $GLOBALS['TSFE']->sys_page->getRawRecord($theTable, $this->data->getRecUid());
			if ( ($theTable == 'fe_users' && $GLOBALS['TSFE']->loginUser) || $this->auth->aCAuth($origArr)) {
				// Must be logged in OR be authenticated by the aC code in order to delete

				// If the recUid selects a record.... (no check here)
				if (is_array($origArr)) {
					if ($this->auth->aCAuth($origArr) || $this->cObj->DBmayFEUserEdit($theTable, $origArr, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						$markerArray = $this->marker->getArray();
						// Display the form, if access granted.
						$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="rU" value="'.$this->data->getRecUid().'" />';
						if ( $theTable != 'fe_users' ) {
							$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$this->prefixId.'[aC]" value="'.$this->auth->authCode($origArr).'" />';
							$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="'.$this->prefixId.'[cmd]" value="delete" />';
						}
						$this->marker->setArray($markerArray);
						$content = $this->getPlainTemplate('###TEMPLATE_DELETE_PREVIEW###', $origArr);
					} else {
						// Else display error, that you could not edit that particular record...
						$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');

					}
				}
			} else {
				// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
				if ( $theTable == 'fe_users' ) {
					$content = $this->getPlainTemplate('###TEMPLATE_AUTH###');

				} else {
					$content = $this->getPlainTemplate('###TEMPLATE_NO_PERMISSIONS###');

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
	* @param string  $key: the template key
	* @param array  $r: the data array, if any
	* @return string  the template with substituted parts and markers
	*/
	function getPlainTemplate($key, $r = '') {
		$templateCode = $this->cObj->getSubpart($this->data->templateCode, $key);
		$markerArray = $this->marker->getArray();
		if (is_array($r))	{
			$markerArray = $this->cObj->fillInMarkerArray($markerArray, $r, '',TRUE, 'FIELD_', TRUE);
		}
		$this->marker->addStaticInfoMarkers($markerArray, $r);
		$this->tca->addTcaMarkers($markerArray, $r, true);
		$this->marker->addLabelMarkers($markerArray, $r, $this->control->getRequiredArray());
		$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $markerArray);
		$rc = $this->cObj->substituteMarkerArray($templateCode, $markerArray);
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
		* @param string  $failure: the list of fields with errors
		* @return string  the template with susbstituted parts
		*/
	function removeRequired($templateCode, $failure = '') {
		$cmdKey = $this->control->getCmdKey();
		$requiredArray = $this->control->getRequiredArray();
		$includedFields = t3lib_div::trimExplode(',', $this->conf[$cmdKey.'.']['fields'], 1);
		if ($this->data->getFeUserData('preview') && !in_array('username', $includedFields)) {
			$includedFields[] = 'username';
		}
		$infoFields = explode(',', $this->data->fieldList);
		if (!t3lib_extMgm::isLoaded('direct_mail')) {
			$infoFields[] = 'module_sys_dmail_category';
			$infoFields[] = 'module_sys_dmail_html';
		}
		reset($infoFields);
		while (list(, $fName) = each($infoFields)) {
			if (in_array(trim($fName), $requiredArray) ) {
				if (!t3lib_div::inList($failure, $fName)) {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_REQUIRED_FIELD_'.$fName.'###', '');
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$fName.'###', '');
				} else if (!$this->data->inError[$fName]) {
					$templateCode = $this->cObj->substituteSubpart($templateCode, '###SUB_ERROR_FIELD_'.$fName.'###', '');
				}
			} else {
				if (!in_array(trim($fName), $includedFields)) {
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
	}	// removeRequired

	function removeHTMLComments($content) {
		return preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/','',$content);
	}

	function replaceHTMLBr($content) {
		$rc = preg_replace('/<br\s?\/>/',chr(10),$content);
		return $rc;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/view/class.tx_srfeuserregister_display.php']);
}
?>
