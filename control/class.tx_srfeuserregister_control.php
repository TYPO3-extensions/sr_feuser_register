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



class tx_srfeuserregister_control {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $display;
	var $data;
	var $marker;
	var $cObj;
	var $setfixedEnabled;
	var $thePid;
	var $loginPID;
	var $site_url;
	var $prefixId;
	var $useMd5Password;
	var $auth;
	var $email;
	var $tca;

	function init(&$pibase, &$conf, &$config, &$display, &$data, &$marker, &$auth, &$email, &$tca)	{
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->display = &$display;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->cObj = &$pibase->cObj;
		$this->setfixedEnabled = $pibase->setfixedEnabled;
		$this->thePid = $pibase->thePid;
		$this->loginPID = $pibase->loginPID;
		$this->site_url = $pibase->site_url;
		$this->prefixId = $pibase->prefixId;
		$this->useMd5Password = $pibase->useMd5Password;
		$this->auth = &$auth;
		$this->email = &$email;
		$this->tca = &$tca;
	}


	/**
	* Process the front end user reply to the confirmation request
	*
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return string  the template with substituted markers
	*/ 
	function processSetFixed(&$markContentArray) {
		global $TSFE;
		
		if ($this->setfixedEnabled) {
			$origArr = $TSFE->sys_page->getRawRecord($this->data->theTable, $this->data->recUid);
			$origUsergroup = $origArr['usergroup'];
			$setfixedUsergroup = '';
			$fD = t3lib_div::_GP('fD', 1);
			$fieldArr = array();
			if (is_array($fD)) {
				reset($fD);
				while (list($field, $value) = each($fD)) {
					$origArr[$field] = rawurldecode($value);
					if($field == 'usergroup') {
						$setfixedUsergroup = rawurldecode($value);
					}
					$fieldArr[] = $field;
				}
			}
			
			$theCode = $this->auth->setfixedHash($origArr, $origArr['_FIELDLIST']);
			if (!strcmp($this->auth->authCode, $theCode)) {
				if ($this->data->feUserData['sFK'] == 'DELETE' || $this->data->feUserData['sFK'] == 'REFUSE') {
					if (!$this->tca->TCA['ctrl']['delete'] || $this->conf['forceFileDelete']) {
						// If the record is fully deleted... then remove the image attached.
						$this->data->deleteFilesFromRecord($this->data->recUid);
					}
					$res = $this->cObj->DBgetDelete($this->data->theTable, $this->data->recUid, true);
					$this->data->deleteMMRelations($this->data->theTable, $this->data->recUid, $origArr);
				} else {
					if ($this->data->theTable == 'fe_users') {
						if ($this->conf['create.']['allowUserGroupSelection']) {
							$origArr['usergroup'] = implode(',', array_unique(array_merge(array_diff(t3lib_div::trimExplode(',', $origUsergroup, 1), t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'], 1)), t3lib_div::trimExplode(',', $setfixedUsergroup, 1))));
						} elseif ($this->data->feUserData['sFK'] == 'APPROVE' && $origUsergroup != $this->conf['create.']['overrideValues.']['usergroup']) {
							$origArr['usergroup'] = $origUsergroup;
						}
					}
						// Hook: first we initialize the hooks
					$hookObjectsArr = array();
					if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['confirmRegistrationClass'])) {
						foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['confirmRegistrationClass'] as $classRef) {
							$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
						}
					}
						// Hook: confirmRegistrationClass_preProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_preProcess')) {
							$hookObj->confirmRegistrationClass_preProcess($origArr, $this);
						}
					}
					$newFieldList = implode(array_intersect(t3lib_div::trimExplode(',', $this->data->fieldList), t3lib_div::trimExplode(',', implode($fieldArr, ','), 1)), ',');
					$res = $this->cObj->DBgetUpdate($this->data->theTable, $this->data->recUid, $origArr, $newFieldList, true);
					$this->data->currentArr = $GLOBALS['TSFE']->sys_page->getRawRecord($this->data->theTable,$this->data->recUid);
					$modArray=array();
					$this->data->currentArr = $this->tca->modifyTcaMMfields($this->data->currentArr,$modArray);
					$origArr = array_merge ($origArr, $modArray);
					$this->pibase->userProcess_alt($this->conf['setfixed.']['userFunc_afterSave'],$this->conf['setfixed.']['userFunc_afterSave.'],array('rec'=>$this->data->currentArr, 'origRec'=>$origArr));

						// Hook: confirmRegistrationClass_postProcess
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
							$hookObj->confirmRegistrationClass_postProcess($origArr, $this);
						}
					} 
				}

				// Outputting template
				if ($this->data->theTable == 'fe_users' && ($this->data->feUserData['sFK'] == 'APPROVE' || $this->data->feUserData['sFK'] == 'ENTER')) {
					$this->marker->setArray($this->marker->addMd5LoginMarkers($this->marker->getArray()));
					if($this->useMd5Password) {
						$origArr['password'] = '';
					}
				}
				$setfixedSufffix = $this->data->feUserData['sFK'];
				if ($this->conf['enableAdminReview'] && $this->data->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
					$setfixedSufffix .= '_REVIEW';
				}
				$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX . 'OK_' . $setfixedSufffix . '###', $origArr);
				if (!$content) {
					$content = $this->display->getPlainTemplate('###TEMPLATE_' . SETFIXED_PREFIX .'OK###', $origArr);
				}
				// Compiling email
				$this->data->dataArr = $origArr;
				$this->email->compile(
					SETFIXED_PREFIX.$setfixedSufffix,
					array($origArr),
					$origArr[$this->conf['email.']['field']],
					$markContentArray,
					$this->conf['setfixed.']
				);

				if ($this->data->theTable == 'fe_users') { 
						// If applicable, send admin a request to review the registration request
					if ($this->conf['enableAdminReview'] && $this->data->feUserData['sFK'] == 'APPROVE' && !$origArr['by_invitation']) {
						$this->email->compile(
							SETFIXED_PREFIX.'REVIEW',
							array($origArr),
							$this->conf['email.']['admin'],
							$this->conf['setfixed.'] );
					}
						// Auto-login on confirmation
					if ($this->conf['enableAutoLoginOnConfirmation'] && ($this->data->feUserData['sFK'] == 'APPROVE' || $this->feUserData['sFK'] == 'ENTER')) {
						$loginVars = array();
						$loginVars['user'] = $origArr['username'];
						$loginVars['pass'] = $origArr['password'];
						$loginVars['pid'] = $this->thePid;
						$loginVars['logintype'] = 'login';
						$loginVars['redirect_url'] = htmlspecialchars(trim($this->conf['autoLoginRedirect_url']));
						header('Location: '.t3lib_div::locationHeaderUrl(($TSFE->absRefPrefix ? '' : $this->site_url).$this->cObj->getTypoLink_URL($this->loginPID.','.$GLOBALS['TSFE']->type, $loginVars)));
						exit;
					}
				}
			} else {
				$content = $this->display->getPlainTemplate('###TEMPLATE_SETFIXED_FAILED###');
			}
		}
		return $content;
	}	// processSetFixed

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_control.php']);
}
?>
