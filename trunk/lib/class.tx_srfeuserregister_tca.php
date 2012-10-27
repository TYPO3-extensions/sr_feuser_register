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
 * TCA front end functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2007@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

class tx_srfeuserregister_tca {

	public function init ($extKey, $theTable) {

			// Get the table definition
		tx_div2007_alpha::loadTcaAdditions_fh001(array($extKey));
		$this->fixAddressFeAdminFieldList();

		if (t3lib_extMgm::isLoaded('direct_mail')) {
			tx_div2007_alpha::loadTcaAdditions_fh001(array('direct_mail'));
			$this->fixAddressFeAdminFieldList();
		}
		tx_div2007_alpha::loadTcaAdditions_fh001($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['extendingTCA']);
		$this->fixAddressFeAdminFieldList();
	}

	public function init2 ($controlData) {

		$this->controlData = $controlData;
	}

	/**
	 * Fix contents of $GLOBALS['TCA']['tt_address']['feInterface']['fe_admin_fieldList']
	 * The list gets broken when EXT:tt_address/tca.php is included twice
	 *
	 * @return void
	 */
	protected function fixAddressFeAdminFieldList () {
		if (
			t3lib_extMgm::isLoaded('tt_address') &&
			isset($GLOBALS['TCA']['tt_address']['feInterface']['fe_admin_fieldList'])
		) {
			$fieldArray = array_unique(t3lib_div::trimExplode(',', $GLOBALS['TCA']['tt_address']['feInterface']['fe_admin_fieldList'], 1));
			$fieldArray = array_diff($fieldArray, array('middle_first_name', 'last_first_name'));
			$fieldList = implode(',', $fieldArray);
			$fieldList = str_replace('first_first_name', 'first_name', $fieldList);
			$GLOBALS['TCA']['tt_address']['feInterface']['fe_admin_fieldList'] = $fieldList;
		}
	}

	public function getForeignTable ($theTable, $colName) {

		$result = FALSE;

		if (
			is_array($GLOBALS['TCA'][$theTable]) &&
			is_array($GLOBALS['TCA'][$theTable]['columns'] &&
			is_array($GLOBALS['TCA'][$theTable]['columns'][$colName])
		) {
			$colSettings = $GLOBALS['TCA'][$theTable]['columns'][$colName];
			$colConfig = $colSettings['config'];
			if ($colConfig['foreign_table']) {
				$result = $colConfig['foreign_table'];
			}
		}
		return $result;
	}

	/**
	* Adds the fields coming from other tables via MM tables
	*
	* @param array  $dataArray: the record array
	* @return array  the modified data array
	*/
	public function modifyTcaMMfields ($theTable, $dataArray, &$modArray) {

		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

		$rcArray = $dataArray;

		foreach ($GLOBALS['TCA'][$theTable]['columns'] as $colName => $colSettings) {
			$colConfig = $colSettings['config'];

			// Configure preview based on input type
			switch ($colConfig['type']) {
				case 'select':
					if ($colConfig['MM'] && $colConfig['foreign_table']) {
						$where = 'uid_local = '.$dataArray['uid'];
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							'uid_foreign',
							$colConfig['MM'],
							$where
						);
						$valueArray = array();

						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$valueArray[] = $row['uid_foreign'];
						}
						$rcArray[$colName] = implode(',', $valueArray);
						$modArray[$colName] = $rcArray[$colName];
					}
					break;
			}
		}

		return $rcArray;
	}


	/**
	* Modifies the incoming data row
	* Adds checkboxes which have been unset. This means that no field will be present for them.
	* Fetches the former values of select boxes
	*
	* @param array  $dataArray: the input data array will be changed
	* @return void
	*/
	public function modifyRow ($theTable, &$dataArray, $bColumnIsCount = TRUE) {

		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

		if (
			is_array($dataArray) &&
		) {
			$fieldsList = array_keys($dataArray);
			foreach ($GLOBALS['TCA'][$theTable]['columns'] as $colName => $colSettings) {
				$colConfig = $colSettings['config'];
				if (!$colConfig || !is_array($colConfig)) {
					continue;
				}

				if ($colConfig['maxitems'] > 1) {
					$bMultipleValues = TRUE;
				} else {
					$bMultipleValues = FALSE;
				}

				switch ($colConfig['type']) {
					case 'group':
						$bMultipleValues = TRUE;
						break;
					case 'select':
						$value = $dataArray[$colName];
						if ($value == 'Array') {	// checkbox from which nothing has been selected
							$dataArray[$colName] = $value = '';
						}
						if (in_array($colName, $fieldsList) && $colConfig['MM'] && isset($value)) {

							if ($value == '' || is_array($value)) {
								// the values from the mm table are already available as an array
							} else if ($bColumnIsCount) {
								$valuesArray = array();
								$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
									'uid_local,uid_foreign,sorting',
									$colConfig['MM'],
									'uid_local=' . intval($dataArray['uid']),
									'',
									'sorting'
								);
								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$valuesArray[] = $row['uid_foreign'];
								}
								$dataArray[$colName] = $valuesArray;
							} else {
								$dataArray[$colName] = t3lib_div::trimExplode (',', $value, 1);
							}
						}
						break;
					case 'check':
						if (is_array($colConfig['items'])) {
							$value = $dataArray[$colName];
							if(is_array($value)) {
								$dataArray[$colName] = 0;
								foreach ($value AS $dec) {  // Combine values to one hexidecimal number
									$dataArray[$colName] |= (1 << $dec);
								}
							}
						} else if (isset($dataArray[$colName])) {
							if ($dataArray[$colName] != '0') {
								$dataArray[$colName] = '1';
							} else {
								$dataArray[$colName] = '0';
							}
						}
						break;
					default:
						// nothing
						break;
				}

				if ($bMultipleValues) {
					$value = $dataArray[$colName];

					if (isset($value) && !is_array($value)) {
						$dataArray[$colName] = t3lib_div::trimExplode (',', $value, 1);
					}
				}
			}

			if (
				t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey) &&
				$dataArray['static_info_country']
			) {
				$staticInfoObj = t3lib_div::getUserObj('&tx_staticinfotables_pi1');
					// empty zone if it does not fit to the provided country
				$zoneArray = $staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);
				if (!isset($zoneArray[$dataArray['zone']])) {
					$dataArray['zone'] = '';
				}
			}
		}
	} // modifyRow

	/**
	* Replaces the markers in the foreign table where clause
	*
	* @param string  $whereClause: foreign table where clause
	* @param array  $colConfig: $TCA column configuration
	* @return string 	foreign table where clause with replaced markers
	*/
	public function replaceForeignWhereMarker ($whereClause, $colConfig) {

		$foreignWhere = $colConfig['foreign_table_where'];

		if ($foreignWhere) {
			$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
			$TSconfig = $pageTSConfig['TCEFORM.'][$theTable . '.'][$colName . '.'];

			if ($TSconfig) {

					// substitute whereClause
				$foreignWhere = str_replace('###PAGE_TSCONFIG_ID###', intval($TSconfig['PAGE_TSCONFIG_ID']), $foreignWhere);
				$foreignWhere =
					str_replace(
						'###PAGE_TSCONFIG_IDLIST###',
						$GLOBALS['TYPO3_DB']->cleanIntList($TSconfig['PAGE_TSCONFIG_IDLIST']),
						$foreignWhere
					);
			}

			// have all markers in the foreign where been replaced?
			if (strpos($foreignWhere, '###') === FALSE) {
				$orderbyPos = stripos($foreignWhere, 'ORDER BY');
				if ($orderbyPos !== FALSE) {
					$whereClause .= ' ' . substr($foreignWhere, 0, $orderbyPos);
				} else {
					$whereClause .= ' ' . $foreignWhere;
				}
			}
		}

		return $whereClause;
	}

	/**
	* Adds form element markers from the Table Configuration Array to a marker array
	*
	* @param array $markerArray: the input marker array
	* @param array $cObj: the cObject
	* @param array $langObj: the language object
	* @param array $controlData: the object of the control data
	* @param array $row: the updated record
	* @param array $origRow: the original record as before the updates
	* @param string $cmd: the command CODE
	* @param string $cmdKey: the command key
	* @param string $theTable: the table in use
	* @param string $prefixId: the extension prefix id
	* @param boolean $viewOnly: whether the fields are presented for view only or for input/update
	* @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	* @param boolean $bChangesOnly: whether only updated fields should be presented
	* @param boolean $HSC: whether content should be htmlspecialchar'ed or not
	* @return void
	*/
	public function addTcaMarkers (
		&$markerArray,
		$conf,
		$cObj,
		$langObj,
		$controlData,
		$row,
		$origRow,
		$cmd,
		$cmdKey,
		$theTable,
		$prefixId,
		$viewOnly = FALSE,
		$activity = '',
		$bChangesOnly = FALSE,
		$HSC = TRUE
	) {
		if (
			!is_array($GLOBALS['TCA'][$theTable]) ||
			!is_array($GLOBALS['TCA'][$theTable]['columns'])
		) {
			return FALSE;
		}

		$charset = $GLOBALS['TSFE']->renderCharset ? $GLOBALS['TSFE']->renderCharset : 'utf-8';
		$mode = $this->controlData->getMode();
		$tablesObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');

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
		$fields = $conf[$cmdKey.'.']['fields'];

		if ($mode == MODE_PREVIEW) {
			if ($activity == '') {
				$activity = 'preview';
			}
		} else if (!$viewOnly && $activity != 'email') {
			$activity = 'input';
		}

		foreach ($GLOBALS['TCA'][$theTable]['columns'] as $colName => $colSettings) {

			if (t3lib_div::inList($fields, $colName)) {

				$colConfig = $colSettings['config'];
				$colContent = '';

				if (!$bChangesOnly || isset($mrow[$colName])) {
					$type = $colConfig['type'];

					// check for a setup of wraps:
					$stdWrap = array();
					$bNotLast = FALSE;
					$bStdWrap = FALSE;
					// any item wraps set?
					if (
						is_array($conf[$type . '.']) &&
						is_array($conf[$type . '.'][$activity . '.']) &&
						is_array($conf[$type . '.'][$activity . '.'][$colName . '.']) &&
						is_array($conf[$type . '.'][$activity . '.'][$colName . '.']['item.'])
					) {
						$stdWrap = $conf[$type. '.'][$activity . '.'][$colName . '.']['item.'];
						$bStdWrap = TRUE;
						if ($conf[$type . '.'][$activity . '.'][$colName . '.']['item.']['notLast']) {
							$bNotLast = TRUE;
						}
					}
					$listWrap = array();
					$bListWrap = FALSE;

					// any list wraps set?
					if (is_array($conf[$type . '.']) && is_array($conf[$type . '.'][$activity.'.']) &&
						is_array($conf[$type . '.'][$activity . '.'][$colName . '.']) &&
						is_array($conf[$type . '.'][$activity . '.'][$colName . '.']['list.']))	{
						$listWrap = $conf[$type . '.'][$activity . '.'][$colName . '.']['list.'];
						$bListWrap = TRUE;
					} else {
						$listWrap['wrap'] = '<ul class="tx-srfeuserregister-multiple-checked-values">|</ul>';
					}

					if ($theTable == 'fe_users' && $colName == 'usergroup') {
						$userGroupObj = $addressObj->getFieldObj('usergroup');
					}

					if ($mode == MODE_PREVIEW || $viewOnly) {
						// Configure preview based on input type

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
											$bCheckedArray[$key] = TRUE;
										}
									}

									$count = 0;
									$checkedCount = 0;
									foreach($colConfig['items'] as $key => $value) {
										$count++;
										$label = $langObj->getLLFromString($colConfig['items'][$key][0]);
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
											$colContent .= ((!$bNotLast || $checkedCount < count($bCheckedArray)) ?  $cObj->stdWrap($label,$stdWrap) : $label);
										}
									}
									$cObj->alternativeData = $colConfig['items'];
									$colContent = $cObj->stdWrap($colContent, $listWrap);
								} else {
									if ($mrow[$colName]) {
										$label = $langObj->getLL('yes');
									} else {
										$label = $langObj->getLL('no');
									}
									if ($HSC) {
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
									}
									$colContent = $label;
								}
								break;

							case 'radio':
								if ($mrow[$colName] != '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',', $mrow[$colName]);
									$textSchema = $theTable . '.' . $colName . '.I.';
									$itemArray = $langObj->getItemsLL($textSchema, TRUE);

									if (!count($itemArray)) {
										if ($colConfig['itemsProcFunc']) {
											$itemArray = t3lib_div::callUserFunction($colConfig['itemsProcFunc'], $colConfig, $this, '');
										}
										$itemArray = $colConfig['items'];
									}

									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);

										if (!$bStdWrap) {
											$stdWrap['wrap'] = '| ';
										}

										for ($i = 0; $i < count ($valuesArray); $i++) {
											$label = $langObj->getLLFromString($itemKeyArray[$valuesArray[$i]][0]);
											if ($HSC) {
												$label = htmlspecialchars($label, ENT_QUOTES, $charset);
											}
											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $cObj->stdWrap($label, $stdWrap) : $label);
										}
									}
								}
								break;

							case 'select':
								if ($mrow[$colName] != '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',', $mrow[$colName]);
									$textSchema = $theTable . '.' . $colName . '.I.';
									$itemArray = $langObj->getItemsLL($textSchema, TRUE);

									if (!count($itemArray)) {
										if ($colConfig['itemsProcFunc']) {
											$itemArray = t3lib_div::callUserFunction($colConfig['itemsProcFunc'], $colConfig, $this, '');
										}
										$itemArray = $colConfig['items'];
									}
									if (!$bStdWrap) {
										$stdWrap['wrap'] = '|<br />';
									}

									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);
										for ($i = 0; $i < count ($valuesArray); $i++) {
											$label = $langObj->getLLFromString($itemKeyArray[$valuesArray[$i]][0]);
											if ($HSC) {
												$label = htmlspecialchars($label, ENT_QUOTES, $charset);
											}
											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $cObj->stdWrap($label,$stdWrap) : $label);
										}
									}

									if ($colConfig['foreign_table']) {
										t3lib_div::loadTCA($colConfig['foreign_table']);
										$reservedValues = array();
										if (isset($userGroupObj) && is_object($userGroupObj)) {
											$reservedValues = $userGroupObj->getReservedValues();
										}
										$valuesArray = array_diff($valuesArray, $reservedValues);
										reset($valuesArray);
										$firstValue = current($valuesArray);

										if (!empty($firstValue) || count($valuesArray) > 1) {
											$titleField = $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['label'];
											$where = 'uid IN (' . implode(',', $valuesArray) . ')';
											$foreignRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
												'*',
												$colConfig['foreign_table'],
												$where
											);
											$languageUid = $this->controlData->getSysLanguageUid(
												$conf,
												'ALL',
												$colConfig['foreign_table']
											);
											if (is_array($foreignRows) && count($foreignRows) > 0) {
												for ($i = 0; $i < count($foreignRows); $i++) {
													if ($theTable == 'fe_users' && $colName == 'usergroup') {
														$foreignRows[$i] = $this->getUsergroupOverlay($conf, $foreignRows[$i]);
													} else if ($localizedRow = $GLOBALS['TSFE']->sys_page->getRecordOverlay($colConfig['foreign_table'], $foreignRows[$i], $languageUid)) {
														$foreignRows[$i] = $localizedRow;
													}
													$text = $foreignRows[$i][$titleField];
													if ($HSC)	{
														$text = htmlspecialchars($text, ENT_QUOTES, $charset);
													}
													$colContent .= (($bNotLast || $i < count($foreignRows) - 1 ) ?  $cObj->stdWrap($text, $stdWrap) : $text);
												}
											}
										}
									}
								}
								break;

							default:
								// unsupported input type
								$label = $langObj->getLL('unsupported');
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
								$textSchema = $theTable . '.' . $colName . '.I.';
								$itemArray = $langObj->getItemsLL($textSchema, TRUE);
								$bUseTCA = FALSE;
								if (!count($itemArray))	{
									if (in_array($type, array('radio', 'select')) && $colConfig['itemsProcFunc']) {
										$itemArray = t3lib_div::callUserFunction(
											$colConfig['itemsProcFunc'],
											$colConfig,
											$this,
											''
										);
									}
									$itemArray = $colConfig['items'];
									$bUseTCA = TRUE;
								}
						}
						switch ($type) {

							case 'input':
								$colContent = '<input type="input" name="FE[' . $theTable . '][' . $colName . ']"' .
									' title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_' : '') . $cObj->caseshift($colName, 'upper') . '###"' .
									' size="' . ($colConfig['size'] ? $colConfig['size'] : 30) . '"';
								if ($colConfig['max']) {
									$colContent .= ' maxlength="' . $colConfig['max'] . '"';
								}
								if ($colConfig['default']) {
									$label = $langObj->getLLFromString($colConfig['default']);
									$label = htmlspecialchars($label,ENT_QUOTES,$charset);
									$colContent .= ' value="' . $label . '"';
								}
								$colContent .= ' />';
								break;

							case 'text':
								$label = $langObj->getLLFromString($colConfig['default']);
								$label = htmlspecialchars($label, ENT_QUOTES, $charset);
								$colContent = '<textarea id="' .  tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '" name="FE[' . $theTable . '][' . $colName . ']"' .
									' title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_':'') . $cObj->caseshift($colName, 'upper') . '###"' .
									' cols="' . ($colConfig['cols'] ? $colConfig['cols'] : 30) . '"' .
									' rows="' . ($colConfig['rows'] ? $colConfig['rows'] : 5) . '"' .
									'>' . ($colConfig['default'] ? $label : '') . '</textarea>';
								break;

							case 'check':
								$label = $langObj->getLL('tooltip_' . $colName);
								$label = htmlspecialchars($label, ENT_QUOTES, $charset);

								if (isset($itemArray) && is_array($itemArray)) {
									$uidText = tx_div2007_alpha5::getClassName_fh001($colName, $prefixId);
									if (isset($mrow) && is_array($mrow) && $mrow['uid']) {
										$uidText .= '-' . $mrow['uid'];
									}
									$colContent = '<ul id="' . $uidText . '" class="tx-srfeuserregister-multiple-checkboxes">';
									if ($this->controlData->getSubmit() || $this->controlData->getDoNotSave() || $cmd == 'edit') {
										$startVal = $mrow[$colName];
									} else {
										$startVal = $colConfig['default'];
									}

									foreach ($itemArray as $key => $value) {
										$checked = ($startVal & (1 << $key)) ? ' checked="checked"' : '';
										$label = $langObj->getLLFromString($itemArray[$key][0]);
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
										$colContent .= '<li><input type="checkbox"' .
										tx_div2007_alpha5::classParam_fh001('checkbox', '', $prefixId) .
										' id="' . $uidText . '-' . $key .  '" name="FE[' . $theTable . '][' . $colName . '][]" value="' . $key . '"' . $checked . ' /><label for="' . $uidText . '-' . $key . '">' . $label . '</label></li>';
									}
									$colContent .= '</ul>';
								} else {
									$colContent = '<input type="checkbox"' .
									tx_div2007_alpha5::classParam_fh001('checkbox', '', $prefixId) .
									' id="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) .
									'" name="FE[' . $theTable . '][' . $colName . ']" title="' . $label . '"' . ($mrow[$colName] ? ' checked="checked"' : '') . ' />';
								}
								break;

							case 'radio':
								if ($this->controlData->getSubmit() || $this->controlData->getDoNotSave() || $cmd == 'edit') {
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
									foreach($itemArray as $key => $confArray) {
										$value = $confArray[1];
										$label = $langObj->getLLFromString($confArray[0]);
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
										$itemOut = '<input type="radio"' .
										tx_div2007_alpha5::classParam_fh001('radio', '', $prefixId) .
										' id="'.
										tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) .
										'-' . $i . '" name="FE[' . $theTable . '][' . $colName . ']"' .
											' value="' . $value . '" ' . ($value == $startVal ? ' checked="checked"' : '') . ' />' .
											'<label for="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) .
											'-' . $i . '">' . $label . '</label>';
										$i++;
										$colContent .= ((!$bNotLast || $i < count($itemArray) - 1 ) ?  $cObj->stdWrap($itemOut, $stdWrap) : $itemOut);
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

								if ($theTable == 'fe_users' && $colName == 'usergroup' && !$conf['allowMultipleUserGroupSelection']) {
									$multiple = '';
								}

								if ($colConfig['renderMode'] == 'checkbox') {
									$colContent .= '
										<input id="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) .
										'" name="FE[' . $theTable . '][' . $colName . ']" value="" type="hidden" />';
									$colContent .= '
										<dl class="' .
										tx_div2007_alpha5::getClassName_fh001('multiple-checkboxes', $prefixId) .
										'" title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_' : '') . $cObj->caseshift($colName,'upper') . '###">';
								} else {
									$colContent .= '<select id="'.
									tx_div2007_alpha5::getClassName_fh001('multiple-checkboxes', $prefixId) .
									'" name="FE[' . $theTable . '][' . $colName . ']' . $multiple . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $cObj->caseshift($colName, 'upper') . '###">';
								}

								if (is_array($itemArray)) {
									$itemArray = $this->getItemKeyArray($itemArray);
									$i = 0;
									foreach ($itemArray as $k => $item)	{
										$label = $langObj->getLLFromString($item[0], TRUE);
										$label = htmlspecialchars($label, ENT_QUOTES, $charset);
										if ($colConfig['renderMode'] == 'checkbox') {

											$colContent .= '<dt><input class="' .
											tx_div2007_alpha5::getClassName_fh001('checkbox-checkboxes', $prefixId) .
											 '" id="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '-' . $i . '" name="FE[' . $theTable . '][' . $colName . '][' . $k . ']" value="' . $k . '" type="checkbox"  ' . (in_array($k, $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
												<dd><label for="' .
												tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) .
												'-' . $i . '">' . $label . '</label></dd>';
										} else {
											$colContent .= '<option value="'.$k. '" ' . (in_array($k, $valuesArray) ? 'selected="selected"' : '') . '>' . $label . '</option>';
										}
										$i++;
									}
								}

								if ($colConfig['foreign_table']) {
									t3lib_div::loadTCA($colConfig['foreign_table']);
									$titleField = $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['label'];
									$reservedValues = array();
									$whereClause = '1=1';

									if (isset($userGroupObj) && is_object($userGroupObj)) {
										$reservedValues = $userGroupObj->getReservedValues();
										$foreignTable = $this->getForeignTable($theTable, $colName);
										$whereClause = $userGroupObj->getAllowedWhereClause(
											$foreignTable,
											$this->controlData->getPid(),
											$conf,
											$cmdKey
										);
									}

									if (
										$conf['useLocalization'] &&
										$GLOBALS['TCA'][$colConfig['foreign_table']] &&
										$GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['languageField'] &&
										$GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField']
									) {
										$whereClause .= ' AND ' . $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['transOrigPointerField'] . '=0';
									}

									if (
										$colName == 'module_sys_dmail_category' &&
										$colConfig['foreign_table'] == 'sys_dmail_category' &&
										$conf['module_sys_dmail_category_PIDLIST']
									) {
										$languageUid =
											$this->controlData->getSysLanguageUid(
												$conf,
												'ALL',
												$colConfig['foreign_table']
											);
										$tmpArray =
											t3lib_div::trimExplode(
												',',
												$conf['module_sys_dmail_category_PIDLIST']
											);
										$pidArray = array();
										foreach ($tmpArray as $v)	{
											if (is_numeric($v))	{
												$pidArray[] = $v;
											}
										}
										$whereClause .= ' AND sys_dmail_category.pid IN (' . implode(',',$pidArray) . ')' . ($conf['useLocalization'] ? ' AND sys_language_uid=' . intval($languageUid) : '');
									}
									$whereClause .= $cObj->enableFields($colConfig['foreign_table']);
									$whereClause = $this->replaceForeignWhereMarker($whereClause,  $colConfig);
									$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $colConfig['foreign_table'], $whereClause, '', $GLOBALS['TCA'][$colConfig['foreign_table']]['ctrl']['sortby']);

									if (!in_array($colName, $this->controlData->getRequiredArray())) {
										if ($colConfig['renderMode'] == 'checkbox' || $colContent) {
											// nothing
										} else {
											$colContent .= '<option value="" ' . ($valuesArray[0] ? '' : 'selected="selected"') . '></option>';
										}
									}

									$selectedValue = FALSE;
									while ($row2 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
											// Handle usergroup case
										if ($colName == 'usergroup' && isset($userGroupObj) && is_object($userGroupObj)) {
											if (!in_array($row2['uid'], $reservedValues)) {
												$row2 = $this->getUsergroupOverlay($conf, $row2);
												$titleText = htmlspecialchars($row2[$titleField], ENT_QUOTES, $charset);
												$selected = (in_array($row2['uid'], $valuesArray) ? ' selected="selected"' : '');
												if (!$conf['allowMultipleUserGroupSelection'] && $selectedValue) {
													$selected = '';
												}
												$selectedValue = ($selected ? TRUE: $selectedValue);
												if ($colConfig['renderMode'] == 'checkbox') {
													$colContent .= '<dt><input  class="' .
													tx_div2007_alpha5::getClassName_fh001('checkbox', $prefixId) .
													'" id="'. tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '-' . $row2['uid'] . '" name="FE[' . $theTable . '][' . $colName . '][' . $row2['uid'] . ']" value="'.$row2['uid'] . '" type="checkbox"' . ($selected ? ' checked="checked"':'') . ' /></dt>
													<dd><label for="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '-' . $row2['uid'] . '">' . $titleText . '</label></dd>';
												} else {
													$colContent .= '<option value="' . $row2['uid'] . '"' . $selected . '>' . $titleText . '</option>';
												}
											}
										} else {
											$languageUid = $this->controlData->getSysLanguageUid(
												$conf,
												'ALL',
												$colConfig['foreign_table']
											);
											if ($localizedRow =
												$GLOBALS['TSFE']->sys_page->getRecordOverlay(
													$colConfig['foreign_table'],
													$row2,
													$languageUid
												)
											) {
												$row2 = $localizedRow;
											}
											$titleText = htmlspecialchars($row2[$titleField], ENT_QUOTES, $charset);

											if ($colConfig['renderMode'] == 'checkbox') {
												$colContent .= '<dt><input class="' .
												tx_div2007_alpha5::getClassName_fh001('checkbox', $prefixId) .
												'" id="'. tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '-' . $row2['uid'] . '" name="FE[' . $theTable . '][' . $colName . '][' . $row2['uid'] . ']" value="' . $row2['uid'] . '" type="checkbox"' . (in_array($row2['uid'],  $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
												<dd><label for="' . tx_div2007_alpha5::getClassName_fh001($colName, $prefixId) . '-' . $row2['uid'] . '">' . $titleText . '</label></dd>';

											} else {
												$colContent .= '<option value="'.$row2['uid'].'"' . (in_array($row2['uid'], $valuesArray) ? 'selected="selected"' : '') . '>' . $titleText . '</option>';
											}
										}
									}
								}

								if ($colConfig['renderMode'] == 'checkbox') {
									$colContent .= '</dl>';
								} else {
									$colContent .= '</select>';
								}
								break;

							default:
								$colContent .= $colConfig['type'] . ':' . $langObj->getLL('unsupported');
								break;
						}
					}

					if (isset($userGroupObj)) {
						unset($userGroupObj);
					}
				} else {
					$colContent = '';
				}

				if ($mode == MODE_PREVIEW || $viewOnly) {
					$markerArray['###TCA_INPUT_VALUE_' . $colName . '###'] = $colContent;
				}
				$markerArray['###TCA_INPUT_' . $colName . '###'] = $colContent;
			} else {
				// field not in form fields list
			}
		}
	}


	/**
	* Transfers the item array to one where the key corresponds to the value
	* @param	array	array of selectable items like found in TCA
	* @ return	array	array of selectable items with correct key
	*/
	public function getItemKeyArray ($itemArray) {
		$rc = array();

		if (is_array($itemArray)) {
			foreach ($itemArray as $k => $row) {
				$key = $row[1];
				$rc[$key] = $row;
			}
		}
		return $rc;
	}	// getItemKeyArray


	/**
		* Returns the relevant usergroup overlay record fields
		* Adapted from t3lib_page.php
		*
		* @param	mixed		If $usergroup is an integer, it's the uid of the usergroup overlay record and thus the usergroup overlay record is returned. If $usergroup is an array, it's a usergroup record and based on this usergroup record the language overlay record is found and gespeichert.OVERLAYED before the usergroup record is returned.
		* @param	integer		Language UID if you want to set an alternative value to $this->controlData->sys_language_content which is default. Should be >=0
		* @return	array		usergroup row which is overlayed with language_overlay record (or the overlay record alone)
		*/
	public function getUsergroupOverlay ($conf, $usergroup, $languageUid = '') {
		// Initialize:
		if ($languageUid == '') {
			$languageUid = $this->controlData->getSysLanguageUid($conf, 'ALL', 'fe_groups_language_overlay');
		}

		// If language UID is different from zero, do overlay:
		if ($languageUid) {
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
				$cObj = t3lib_div::getUserObj('&tx_div2007_cobj');

				$whereClause = 'fe_group=' . intval($fe_groups_uid) . ' ' .
					'AND sys_language_uid=' . intval($languageUid) . ' ' .
					$cObj->enableFields('fe_groups_language_overlay');
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', $fieldArr), 'fe_groups_language_overlay', $whereClause);
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				}
			}
		}

			// Create output:
		if (is_array($usergroup)) {
			return is_array($row) ? array_merge($usergroup, $row) : $usergroup;
			// If the input was an array, simply overlay the newfound array and return...
		} else {
			return is_array($row) ? $row : array(); // always an array in return
		}
	}	// getUsergroupOverlay
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php']);
}
?>