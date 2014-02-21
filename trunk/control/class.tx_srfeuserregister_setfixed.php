<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2013 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * setfixed functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasperXXXX@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_setfixed {
	public $previewLabel;
	public $setfixedEnabled;
	public $cObj;
	public $buttonLabelsList;
	public $otherLabelsList;


	/**
	* Process the front end user reply to the confirmation request
	*
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param string $theTable: the table in use
	* @param string $prefixId: the extension prefix id
	* @param array  Array with key/values being marker-strings/substitution values.
	* @return string  the template with substituted markers
	*/
	public function processSetFixed (
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$tcaObj,
		$markerObj,
		$dataObj,
		$theTable,
		$prefixId,
		$uid,
		$cmdKey,
		$markerArray,
		$displayObj,
		$emailObj,
		$templateCode,
		$dataArray,
		$origArray,
		$securedArray,
		$pObj,
		$feuData,
		$token
	) {
		$row = $origArray;

		if ($controlData->getSetfixedEnabled()) {
			$autoLoginIsRequested = FALSE;
			$origUsergroup = $row['usergroup'];
			$setfixedUsergroup = '';
			$setfixedSuffix = $sFK = $feuData['sFK'];
			$fD = t3lib_div::_GP('fD', 1);
			$fieldArr = array();

			if (is_array($fD)) {
				foreach ($fD as $field => $value) {
					$row[$field] = rawurldecode($value);
					if ($field == 'usergroup') {
						$setfixedUsergroup = rawurldecode($value);
					}
					$fieldArr[] = $field;
				}
			}

			if ($theTable == 'fe_users') {
					// Determine if auto-login is requested
				$autoLoginIsRequested = $controlData->getStorageSecurity()->getAutoLoginIsRequested($feuData, $row);
			}

			$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');
				// Calculate the setfixed hash from incoming data
			$fieldList = $row['_FIELDLIST'];
			$codeLength = strlen($authObj->getAuthCode());
				// Let's try with a code length of 8 in case this link is coming from direct mail
			if ($codeLength == 8 && in_array($sFK, array('DELETE', 'EDIT', 'UNSUBSCRIBE'))) {
				$theCode = $authObj->setfixedHash($row, $fieldList, $codeLength);
			} else {
				$theCode = $authObj->setfixedHash($row, $fieldList);
			}

			if (
				!strcmp($authObj->getAuthCode(), $theCode) &&
				!($sFK == 'APPROVE' && count($origArray) && $origArray['disable'] == '0')
			) {
				if ($sFK == 'EDIT') {
					$markerObj->addGeneralHiddenFieldsMarkers($markerArray, $cmd, $token);
					$content = $displayObj->editScreen(
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
						'setfixed',
						$cmdKey,
						$controlData->getMode(),
						$dataObj->inError,
						$token
					);
				} else if (
					$sFK == 'DELETE' ||
					$sFK == 'REFUSE'
				) {
					if (
						!$GLOBALS['TCA'][$theTable]['ctrl']['delete'] ||
						$conf['forceFileDelete']
					) {
						// If the record is fully deleted... then remove the image attached.
						$dataObj->deleteFilesFromRecord($theTable, $uid);
					}
					$res = $cObj->DBgetDelete(
						$theTable,
						$uid,
						TRUE
					);
					$dataObj->deleteMMRelations(
						$theTable,
						$uid,
						$row
					);
				} else {
					if ($theTable == 'fe_users') {
						if ($conf['create.']['allowUserGroupSelection']) {
							$originalGroups = is_array($origUsergroup)
								? $origUsergroup
								: t3lib_div::trimExplode(',', $origUsergroup, TRUE);
							$overwriteGroups = t3lib_div::trimExplode(
								',',
								$conf['create.']['overrideValues.']['usergroup'],
								TRUE
							);

							$remainingGroups = array_diff($originalGroups, $overwriteGroups);
							$groupsToAdd = t3lib_div::trimExplode(',', $setfixedUsergroup, TRUE);
							$finalGroups = array_merge(
								$remainingGroups, $groupsToAdd
							);
							$row['usergroup'] = implode(',', array_unique($finalGroups));
						}
					}

						// Hook: first we initialize the hooks
					$hookObjectsArr = array();
					if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$controlData->getExtKey()][$prefixId]['confirmRegistrationClass'])) {
						foreach  ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$controlData->getExtKey()][$prefixId]['confirmRegistrationClass'] as $classRef) {
							$hookObjectsArr[] = t3lib_div::getUserObj($classRef);
						}
					}
						// Hook: confirmRegistrationClass_preProcess
					foreach($hookObjectsArr as $hookObj) {
						if (method_exists($hookObj, 'confirmRegistrationClass_preProcess')) {
							$hookObj->confirmRegistrationClass_preProcess($row, $this);
						}
					}
					$newFieldList = implode(',', array_intersect(
						t3lib_div::trimExplode(',', $dataObj->fieldList, 1),
						t3lib_div::trimExplode(',', implode($fieldArr, ','), 1)
					));

					if ($sFK == 'UNSUBSCRIBE') {
						$newFieldList = implode(',', array_intersect(
							t3lib_div::trimExplode(',', $newFieldList),
							t3lib_div::trimExplode(',', $conf['unsubscribeAllowedFields'], 1)
						));
					}

					if ($sFK != 'ENTER' && $newFieldList != '') {
						$res = $cObj->DBgetUpdate(
							$theTable,
							$uid,
							$row,
							$newFieldList,
							TRUE
						);
					}
					$currArr = $origArray;
					if ($autoLoginIsRequested) {
						$controlData->getStorageSecurity()->decryptPasswordForAutoLogin($currArr, $row);
					}
					$modArray = array();
					$currArr =
						$tcaObj->modifyTcaMMfields(
							$theTable,
							$currArr,
							$modArray
						);

					$row = array_merge($row, $modArray);
					if ($conf['setfixed.']['userFunc_afterSave'] && is_array($conf['setfixed.']['userFunc_afterSave.'])) {
						$funcConf = $conf['setfixed.']['userFunc_afterSave.'];
						$funcConf['parentObj'] = $pObj;
						$GLOBALS['TSFE']->cObj->callUserFunction($conf['setfixed.']['userFunc_afterSave'], $funcConf, array('rec' => $currArr, 'origRec' => $origArray));
					}
						// Hook: confirmRegistrationClass_postProcess
					foreach($hookObjectsArr as $hookObj) {
						if (method_exists($hookObj, 'confirmRegistrationClass_postProcess')) {
							$hookObj->confirmRegistrationClass_postProcess($row, $this);
						}
					}
				}

					// Outputting template
				if (
					$theTable == 'fe_users' &&
						// LOGIN is here only for an error case  ???
					in_array($sFK, array('APPROVE', 'ENTER', 'LOGIN'))
				) {
					$markerObj->addGeneralHiddenFieldsMarkers($markerArray, $row['by_invitation'] ? 'password' : 'login', $token);
					if (!$row['by_invitation']) {
						$markerObj->addPasswordTransmissionMarkers($markerArray);
						$markerObj->setArray($markerArray);
					}
				} else {
					$markerObj->addGeneralHiddenFieldsMarkers($markerArray, 'setfixed', $token);
				}

				if ($sFK == 'EDIT') {
					// Nothing to do
				} else {
					if (
						$theTable == 'fe_users' &&
						($sFK == 'APPROVE' || $sFK == 'ENTER') &&
						$row['by_invitation']
					) {
							// Auto-login
						$loginSuccess =
							$pObj->login(
								$conf,
								$langObj,
								$controlData,
								$currArr,
								FALSE
							);

						if ($loginSuccess) {
							$content =
								$displayObj->editScreen(
									$markerArray,
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
									'password',
									'password',
									$controlData->getMode(),
									$dataObj->inError,
									$token
								);
						} else {
								// Login failed
							$content =
								$displayObj->getPlainTemplate(
									$conf,
									$cObj,
									$langObj,
									$controlData,
									$tcaObj,
									$markerObj,
									$dataObj,
									$templateCode,
									'###TEMPLATE_SETFIXED_FAILED###',
									$markerArray,
									$origArray,
									$theTable,
									$prefixId,
									'',
									''
								);
						}
					}

					if (
						$conf['enableAdminReview'] &&
						$sFK == 'APPROVE'
					) {
						$setfixedSuffix .= '_REVIEW';
					}
					if (!$content) {
						$subpartMarker = '###TEMPLATE_' . SETFIXED_PREFIX . 'OK_' . $setfixedSuffix . '###';
						$content =
							$displayObj->getPlainTemplate(
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
								$row,
								$securedArray,
								FALSE
							);
					}

					if (!$content) {
						$subpartMarker = '###TEMPLATE_' . SETFIXED_PREFIX .'OK###';
						$content =
							$displayObj->getPlainTemplate(
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
								$row,
								$securedArray
							);
					}

					if (
						$conf['email.']['SETFIXED_REFUSE'] ||
						$conf['enableEmailConfirmation'] ||
						$conf['infomail']
					) {
						$errorCode = '';
							// Compiling email
						$errorContent = $emailObj->compile(
							SETFIXED_PREFIX . $setfixedSuffix,
							$conf,
							$cObj,
							$langObj,
							$controlData,
							$tcaObj,
							$markerObj,
							$dataObj,
							$displayObj,
							$this,
							$theTable,
							$prefixId,
							array($row),
							array($origArray),
							$securedArray,
							$origArray[$conf['email.']['field']],
							$markerArray,
							'setfixed',
							$cmdKey,
							$templateCode,
							$this->data->inError,
							$conf['setfixed.'],
							$errorCode
						);

						if (is_array($errorCode)) {
							$errorText = $this->langObj->getLL($errorCode['0']);
							$errorContent = sprintf($errorText, $errorCode['1']);
						}
					}

					if ($errorContent) {
						$content = $errorContent;
					} else if ($theTable == 'fe_users') {
							// If applicable, send admin a request to review the registration request
						if (
							$conf['enableAdminReview'] &&
							$sFK == 'APPROVE' &&
							!$row['by_invitation']
						) {
							$errorCode = '';
							$errorContent = $emailObj->compile(
								SETFIXED_PREFIX . 'REVIEW',
								$conf,
								$cObj,
								$langObj,
								$controlData,
								$tcaObj,
								$markerObj,
								$dataObj,
								$displayObj,
								$this,
								$theTable,
								$prefixId,
								array($row),
								array($origArray),
								$securedArray,
								$origArray[$conf['email.']['field']],
								$markerArray,
								'setfixed',
								$cmdKey,
								$templateCode,
								$this->data->inError,
								$conf['setfixed.']
							);

							if (is_array($errorCode)) {
								$errorText = $this->langObj->getLL($errorCode['0']);
								$errorContent = sprintf($errorText, $errorCode['1']);
							}
						}
						if ($errorContent) {
							$content = $errorContent;
						} else if (
								// Auto-login on confirmation
							$conf['enableAutoLoginOnConfirmation'] &&
							!$row['by_invitation'] &&
							(($sFK == 'APPROVE' && !$conf['enableAdminReview']) || $sFK == 'ENTER') &&
							$autoLoginIsRequested
						) {
							$loginSuccess =
								$pObj->login(
									$conf,
									$langObj,
									$controlData,
									$currArr
								);
							if ($loginSuccess) {
									// Login was successful
								exit;
							} else {
									// Login failed
								$content = $displayObj->getPlainTemplate(
									$conf,
									$cObj,
									$langObj,
									$controlData,
									$tcaObj,
									$markerObj,
									$dataObj,
									$templateCode,
									'###TEMPLATE_SETFIXED_FAILED###',
									$markerArray,
									$origArray,
									$theTable,
									$prefixId,
									'',
									''
								);
							}
						}
					}
				}
			} else {
				$content = $displayObj->getPlainTemplate(
					$conf,
					$cObj,
					$langObj,
					$controlData,
					$tcaObj,
					$markerObj,
					$dataObj,
					$templateCode,
					'###TEMPLATE_SETFIXED_FAILED###',
					$markerArray,
					$origArray,
					$theTable,
					$prefixId,
					'',
					''
				);
			}
		}
		return $content;
	}	// processSetFixed


	/**
	* Computes the setfixed url's
	*
	* @param array  $markerArray: the input marker array
	* @param array  $setfixed: the TS setup setfixed configuration
	* @param array  $record: the record row
	* @param array $controlData: the object of the control data
	* @return void
	*/
	public function computeUrl (
		$cmdKey,
		$prefixId,
		$cObj,
		$controlData,
		&$markerArray,
		$setfixed,
		array $record,
		$theTable,
		$useShortUrls,
		$editSetfixed,
		$confirmType
	) {
		if ($controlData->getSetfixedEnabled() && is_array($setfixed)) {
			$authObj = t3lib_div::getUserObj('&tx_srfeuserregister_auth');

			foreach($setfixed as $theKey => $data) {
				if (strstr($theKey, '.')) {
					$theKey = substr($theKey, 0, -1);
				}
				$setfixedpiVars = array();

				if ($theTable != 'fe_users' && $theKey == 'EDIT') {
					$noFeusersEdit = TRUE;
				} else {
					$noFeusersEdit = FALSE;
				}

				$setfixedpiVars[$prefixId . '%5BrU%5D'] = $record['uid'];
				$fieldList = $data['_FIELDLIST'];
				$fieldListArray = t3lib_div::trimExplode(',', $fieldList);

				foreach ($fieldListArray as $fieldname) {
					if (isset($data[$fieldname])) {
						$fieldValue = $data[$fieldname];

						if ($fieldname == 'usergroup' && $data['usergroup.']) {
							$tablesObj = t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
							$addressObj = $tablesObj->get('address');
							$userGroupObj = $addressObj->getFieldObj('usergroup');

							if (is_object($userGroupObj)) {
								$fieldValue =
									$userGroupObj->getExtendedValue(
										$controlData->getExtKey(),
										$fieldValue,
										$data['usergroup.'],
										$record
									);

								$data[$fieldname] = $fieldValue;
							}
						}
						$record[$fieldname] = $fieldValue;
					}
				}

				if ($noFeusersEdit) {
					$cmd = $pidCmd = 'edit';
					if($editSetfixed) {
						$bSetfixedHash = TRUE;
					} else {
						$bSetfixedHash = FALSE;
						$setfixedpiVars[$prefixId . '%5BaC%5D'] =
							$authObj->authCode(
								$record,
								$fieldList
							);
					}
				} else {
					$cmd = 'setfixed';
					$pidCmd = ($controlData->getCmd() == 'invite' ? 'confirmInvitation' : 'confirm');
					$setfixedpiVars[$prefixId . '%5BsFK%5D'] = $theKey;
					$bSetfixedHash = TRUE;
					if (isset($record['auto_login_key'])) {
						$setfixedpiVars[$prefixId . '%5Bkey%5D'] = $record['auto_login_key'];
					}
				}

				if ($bSetfixedHash) {
					$setfixedpiVars[$prefixId . '%5BaC%5D'] = $authObj->setfixedHash($record, $fieldList);
				}
				$setfixedpiVars[$prefixId . '%5Bcmd%5D'] = $cmd;

				if (is_array($data) ) {
					foreach($data as $fieldname => $fieldValue) {
						if (strpos($fieldname, '.') !== FALSE) {
							continue;
						}
						$setfixedpiVars['fD%5B' . $fieldname . '%5D'] = rawurlencode($fieldValue);
					}
				}

				$linkPID = $controlData->getPid($pidCmd);

				if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = t3lib_div::_GP('L');
				}

				if ($useShortUrls) {
					$thisHash = $this->storeFixedPiVars($setfixedpiVars);
					$setfixedpiVars = array($prefixId . '%5BregHash%5D' => $thisHash);
				}
				$urlConf = array();
				$urlConf['disableGroupAccessCheck'] = TRUE;
				$bconfirmTypeIsInt = (
					class_exists('t3lib_utility_Math') ?
						t3lib_utility_Math::canBeInterpretedAsInteger($confirmType) :
						t3lib_div::testInt($confirmType)
				);

				$confirmType = ($bconfirmTypeIsInt ? intval($confirmType) : $GLOBALS['TSFE']->type);
				$url =
					tx_div2007_alpha5::getTypoLink_URL_fh003(
						$cObj,
						$linkPID . ',' . $confirmType,
						$setfixedpiVars,
						'',
						$urlConf
					);

				$bIsAbsoluteURL = ((strncmp($url, 'http://', 7) == 0) || (strncmp($url, 'https://', 8) == 0));
				$markerKey = '###SETFIXED_' . $cObj->caseshift($theKey, 'upper') . '_URL###';
				$url = ($bIsAbsoluteURL ? '' : $controlData->getSiteUrl()) . ltrim($url, '/');
				$markerArray[$markerKey] = str_replace(array('[',']'), array('%5B', '%5D'), $url);
			}	// foreach
		}
	}	// computeUrl


	/**
	 *  Store the setfixed vars and return a replacement hash
	 */
	public function storeFixedPiVars ($vars) {

			// Create a unique hash value
		if (class_exists('t3lib_cacheHash')) {
			$cacheHash = t3lib_div::makeInstance('t3lib_cacheHash');
			$regHash_calc = $cacheHash->calculateCacheHash($vars);
			$regHash_calc = substr($regHash_calc, 0, 20);
		} else {
				// t3lib_div::cHashParams is deprecated in TYPO3 4.7
			$regHash_array = t3lib_div::cHashParams(t3lib_div::implodeArrayForUrl('', $vars));
			$regHash_calc = t3lib_div::shortMD5(serialize($regHash_array), 20);
		}
			// and store it with a serialized version of the array in the DB
		$res =
			$GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'md5hash',
				'cache_md5params',
				'md5hash=' .
					$GLOBALS['TYPO3_DB']->fullQuoteStr(
						$regHash_calc,
						'cache_md5params'
					)
				);

		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$insertFields = array (
				'md5hash' => $regHash_calc,
				'tstamp' => time(),
				'type' => 99,
				'params' => serialize($vars)
			);

			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'cache_md5params',
				$insertFields
			);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $regHash_calc;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php']);
}
?>