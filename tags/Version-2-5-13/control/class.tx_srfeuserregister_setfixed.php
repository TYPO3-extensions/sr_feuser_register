<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2008 Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca)>
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
 * setfixed functions
 *
 * $Id$
 *
 * @author Kasper Skaarhoj <kasperXXXX@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author Franz Holzinger <contact@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_setfixed {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $control;
	var $controlData;
	var $tca;
	var $display;
	var $email;
	var $marker;
	var $previewLabel;
	var $setfixedEnabled;
	var $cObj;
	var $buttonLabelsList;
	var $otherLabelsList;


	function init(&$cObj, &$conf, &$config, &$controlData, &$tca, &$display, &$email, &$marker)	{
		global $TSFE;

		$this->cObj = &$cObj;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->controlData = &$controlData;
		$this->tca = &$tca;
		$this->display = &$display;
		$this->email = &$email;
		$this->marker = &$marker;
	}


	/**
	* Process the front end user reply to the confirmation request
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return string  the template with substituted markers
	*/ 
	function processSetFixed($theTable, $uid, &$markerArray, &$templateCode, &$dataArray, &$origArray, &$pObj, &$dataObj) {
		global $TSFE;

		if ($this->controlData->getSetfixedEnabled()) {
			// $origArray = $TSFE->sys_page->getRawRecord($theTable, $uid);
			$row = $origArray;
			$origUsergroup = $row['usergroup'];
			$setfixedUsergroup = '';
			$fD = t3lib_div::_GP('fD', 1);
			$fieldArr = array();
			if (is_array($fD)) {
				foreach($fD as $field => $value) {
					$row[$field] = rawurldecode($value);
					if($field == 'usergroup') {
						$setfixedUsergroup = rawurldecode($value);
					}
					$fieldArr[] = $field;
				}
			}
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
			$theCode = $authObj->setfixedHash($row, $row['_FIELDLIST']);
			$feuData = $this->controlData->getFeUserData();

			if (!strcmp($authObj->getAuthCode(), $theCode)) {
				if ($feuData['sFK'] == 'DELETE' || $feuData['sFK'] == 'REFUSE') {
					if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
						// If the record is fully deleted... then remove the image attached.
						$dataObj->deleteFilesFromRecord($uid);
					}
					$res = $this->cObj->DBgetDelete($theTable, $uid, true);
					$dataObj->deleteMMRelations($theTable, $uid, $row);
				} else {
					if ($theTable == 'fe_users') {
						if ($this->conf['create.']['allowUserGroupSelection']) {
							$row['usergroup'] = implode(',', array_unique(array_merge(array_diff(t3lib_div::trimExplode(',', $origUsergroup, 1), t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'], 1)), t3lib_div::trimExplode(',', $setfixedUsergroup, 1))));
						} elseif ($feuData['sFK'] == 'APPROVE' && $origUsergroup != $this->conf['create.']['overrideValues.']['usergroup']) {
							$row['usergroup'] = $origUsergroup;
						}
					}
						// Hook: first we initialize the hooks
					$hookObjectsArr = array();
					if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->controlData->getExtKey()][$this->controlData->getPrefixId()]['confirmRegistrationClass'])) {
						foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->controlData->getExtKey()][$this->controlData->getPrefixId()]['confirmRegistrationClass'] as $classRef) {
							$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
						}
					}
						// Hook: confirmRegistrationClass_preProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_preProcess')) {
							$hookObj->confirmRegistrationClass_preProcess($row, $this);
						}
					}
					$newFieldList = implode(array_intersect(t3lib_div::trimExplode(',', $dataObj->fieldList), t3lib_div::trimExplode(',', implode($fieldArr, ','), 1)), ',');
					$res = $this->cObj->DBgetUpdate($theTable, $uid, $row, $newFieldList, true);
					$currArr = $origArray;
					$modArray=array();
					$currArr = $this->tca->modifyTcaMMfields($currArr,$modArray);
//					$this->data->setCurrentArray ($currArr);
					$row = array_merge ($row, $modArray);

					$pObj->userProcess_alt($this->conf['setfixed.']['userFunc_afterSave'],$this->conf['setfixed.']['userFunc_afterSave.'],array('rec'=>$currArr, 'origRec'=>$origArray));

						// Hook: confirmRegistrationClass_postProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
							$hookObj->confirmRegistrationClass_postProcess($row, $this);
						}
					}
				}

				// Outputting template
				if (
					$theTable == 'fe_users' && 
					in_array($feuData['sFK'], array('APPROVE','ENTER','LOGIN'))	// LOGIN is here only for an error case
				)	{
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, 'login');
					$this->marker->addMd5LoginMarkers($markerArray, $dataArray, $this->controlData->getUseMd5Password());
					$this->marker->setArray($markerArray);
				} else {
					$this->marker->addGeneralHiddenFieldsMarkers($markerArray, 'setfixed');
				}
				$setfixedSuffix = $feuData['sFK'];

				if ($this->conf['enableAdminReview'] && $setfixedSuffix == 'APPROVE' && !$row['by_invitation']) {
					$setfixedSuffix .= '_REVIEW';
				}
				$subpartMarker = '###TEMPLATE_' . SETFIXED_PREFIX . 'OK_' . $setfixedSuffix . '###';
				$content = $this->display->getPlainTemplate($templateCode, $subpartMarker, $origArray, $row);
				if (!$content) {
					$subpartMarker = '###TEMPLATE_' . SETFIXED_PREFIX .'OK###';
					$content = $this->display->getPlainTemplate($templateCode, $subpartMarker, $origArray, $row);
				}

				if ($this->conf['enableEmailConfirmation'] || $this->conf['infomail'])	{

					// Compiling email
					$this->email->compile(
						SETFIXED_PREFIX.$setfixedSuffix,
						$theTable,
						array($row),
						array($origArray),
						$origArray[$this->conf['email.']['field']],
						$markerArray,
						'setfixed',
						$this->controlData->getCmdKey(),
						$templateCode,
						$this->conf['setfixed.']
					);
				}

				if ($theTable == 'fe_users') { 
						// If applicable, send admin a request to review the registration request
					if ($this->conf['enableAdminReview'] && $feuData['sFK'] == 'APPROVE' && !$row['by_invitation']) {
						$this->email->compile(
							SETFIXED_PREFIX.'REVIEW',
							$theTable,
							array($row),
							array($origArray),
							$this->conf['email.']['admin'],
							$markerArray,
							'setfixed',
							$this->controlData->getCmdKey(),
							$templateCode,
							$this->conf['setfixed.']
						);
					}

						// Auto-login on confirmation
					if ($this->conf['enableAutoLoginOnConfirmation'] && ($feuData['sFK'] == 'APPROVE' || $feuData['sFK'] == 'ENTER')) {
						$pObj->login($currArr);
						exit;
					}
				}
			} else {
				$content = $this->display->getPlainTemplate($templateCode, '###TEMPLATE_SETFIXED_FAILED###', $origArray);
			}
		}
		return $content;
	}	// processSetFixed


	/**
	* Computes the setfixed url's
	*
	* @param array  $markerArray: the input marker array
	* @param array  $setfixed: the TS setup setfixed configuration
	* @param array  $r: the record
	* @return void
	*/
	function computeUrl(&$markerArray, $setfixed, $r, $theTable) {
		global $TSFE;

		$prefixId = $this->controlData->getPrefixId();
		if ($this->controlData->getSetfixedEnabled() && is_array($setfixed) ) {
			$setfixedpiVars = array();
			$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			foreach($setfixed as $theKey => $data) {
				if (strstr($theKey, '.') ) {
					$theKey = substr($theKey, 0, -1);
				}
				unset($setfixedpiVars);
				$recCopy = $r;
				$setfixedpiVars[$prefixId .'[rU]'] = $r['uid'];

				if ($theTable != 'fe_users' && $theKey == 'EDIT' ) {
					if (is_array($data) ) {
						reset($data);
						foreach($data as $fieldName => $fieldValue) {
							$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
							$recCopy[$fieldName] = $fieldValue;
						}
					}
					if( $this->conf['edit.']['setfixed'] ) {
						$setfixedpiVars[$prefixId.'[aC]'] = $authObj->setfixedHash($recCopy, $data['_FIELDLIST']);
					} else {
						$setfixedpiVars[$prefixId.'[aC]'] = $authObj->authCode($r);
					}
					$linkPID = $this->controlData->getPID('edit');
				} else {
					$setfixedpiVars[$prefixId.'[cmd]'] = 'setfixed';
					$setfixedpiVars[$prefixId.'[sFK]'] = $theKey;

					if (isset($r['chalvalue']))	{
						$setfixedpiVars[$prefixId.'[cv]'] = $r['chalvalue'];
					}

					if (is_array($data) ) {
						foreach($data as $fieldName => $fieldValue) {
							$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
							$recCopy[$fieldName] = $fieldValue;
						}
					}
					$setfixedpiVars[$prefixId.'[aC]'] = $authObj->setfixedHash($recCopy, $data['_FIELDLIST']);
					$linkPID = $this->controlData->getPID('confirm');
					if ($this->controlData->getCmd() == 'invite') {
						$linkPID = $this->controlData->getPID('confirmInvitation');

					}
				}
				if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = t3lib_div::_GP('L');
				}

				if ($this->conf['useShortUrls']) {

					$thisHash = $this->storeFixedPiVars($setfixedpiVars);
					$setfixedpiVars = array($prefixId.'[regHash]' => $thisHash);
				}
				$conf = array();
				$conf['disableGroupAccessCheck'] = TRUE;
				$confirmType = (t3lib_div::testInt($this->conf['confirmType']) ? intval($this->conf['confirmType']) : $TSFE->type);
				$url = $this->cObj->getTypoLink_URL($linkPID.','.$confirmType, $setfixedpiVars, '', $conf);
				$bIsAbsoluteURL = ($TSFE->absRefPrefix || (strncmp($url,'http',4) == 0) || (strncmp($url,'https',5) == 0));
				$markerKey = '###SETFIXED_'.$this->cObj->caseshift($theKey,'upper').'_URL###';
				$markerArray[$markerKey] = ($bIsAbsoluteURL ? '' : $this->controlData->getSiteUrl()) . $url;
			}
		}
	}	// computeUrl


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
		$TYPO3_DB->sql_free_result($res);
		return $regHash_calc;
	}	// storeFixedPiVars
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php']);
}
?>
