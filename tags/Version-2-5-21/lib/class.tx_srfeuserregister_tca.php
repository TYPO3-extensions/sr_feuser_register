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
 * Part of the sr_feuser_register (Frontend User Registration) extension.
 *
 * TCA functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2007@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */



class tx_srfeuserregister_tca {
	var $pibase;
	var $conf = array();
	var $control;
	var $controlData;
	var $langObj;

	var $TCA = array();
	var $cObj;


	function init (&$pibase, &$conf, &$controlData, &$langObj, $extKey, $theTable)	{
		global $TSFE, $TCA;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->controlData = &$controlData;
		$this->langObj = &$langObj;
		$this->cObj = &$pibase->cObj;

			// get the table definition
		$TSFE->includeTCA();
		$this->TCA = $TCA[$theTable];
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['uploadFolder'])	{
			$this->TCA[$theTable]['columns']['image']['config']['uploadfolder'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extKey]['uploadFolder'];
		}
	}

	function &getTCA ()	{
		return $this->TCA;
	}

	/**
	* Adds the fields coming from other tables via MM tables
	*
	* @param array  $dataArray: the record array
	* @return array  the modified data array
	*/
	function modifyTcaMMfields ($dataArray, &$modArray) {
		global $TYPO3_DB;

		$rcArray = $dataArray;

		foreach ($this->TCA['columns'] as $colName => $colSettings) {
			$colConfig = $colSettings['config'];

			// Configure preview based on input type
			switch ($colConfig['type']) {
				case 'select':
					if ($colConfig['MM'] && $colConfig['foreign_table']) {
						$where = 'uid_local = '.$dataArray['uid'];
						$res = $TYPO3_DB->exec_SELECTquery(
							'uid_foreign',
							$colConfig['MM'],
							$where
						);
						$valueArray = array();

						while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
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
	function modifyRow (&$dataArray, $bColumnIsCount=TRUE)	{
		global $TYPO3_DB;

		if (isset($dataArray) && is_array($dataArray))	{
			$fieldsList = array_keys($dataArray);
			foreach ($this->TCA['columns'] as $colName => $colSettings) {
				$colConfig = $colSettings['config'];
				if (!$colConfig || !is_array($colConfig))	{
					continue;
				}
				if ($colConfig['maxitems'] > 1)	{
					$bMultipleValues = TRUE;
				} else {
					$bMultipleValues = FALSE;
				}
				switch ($colConfig['type'])	{
					case 'group':
						$bMultipleValues = TRUE;
						break;
					case 'select':
						$value = $dataArray[$colName];
						if ($value == 'Array')	{	// checkbox from which nothing has been selected
							$dataArray[$colName] = $value = '';
						}
						if (in_array($colName, $fieldsList) && $colConfig['MM'] && isset($value)) {

							if ($value == '' || is_array($value))	{
								// the values from the mm table are already available as an array
							} else if ($bColumnIsCount) {
								$valuesArray = array();
								$res = $TYPO3_DB->exec_SELECTquery(
									'uid_local,uid_foreign,sorting',
									$colConfig['MM'],
									'uid_local='.intval($dataArray['uid']),
									'',
									'sorting'
								);
								while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
									$valuesArray[$row['uid_foreign']] = $row['uid_foreign'];
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
						} else if (isset($dataArray[$colName]) && $dataArray[$colName]!='0') {
							$dataArray[$colName] = '1';
						} else {
							$dataArray[$colName] = '0';
						}
						break;
					default:
						// nothing
						break;
				}
				if ($bMultipleValues)	{
					$value = $dataArray[$colName];

					if (isset($value) && !is_array($value))	{
						$dataArray[$colName] = t3lib_div::trimExplode (',', $value, 1);
					}
				}
			}
			if (t3lib_extMgm::isLoaded(STATIC_INFO_TABLES_EXTkey) && $dataArray['static_info_country']) {
				$staticInfoObj = &t3lib_div::getUserObj('&tx_staticinfotables_pi1');
					// empty zone if it does not fit to the provided country
				$zoneArray = $staticInfoObj->initCountrySubdivisions($dataArray['static_info_country']);
				if (!isset($zoneArray[$dataArray['zone']]))	{
					$dataArray['zone'] = '';
				}
			}
		}
	} // modifyRow

	/**
	* Adds form element markers from the Table Configuration Array to a marker array
	*
	* @param array  $markerArray: the input marker array
	* @param array  $row: the record
	* @return void
	*/
	function addTcaMarkers (&$markerArray, $row, $origRow, $cmd, $cmdKey, $theTable, $viewOnly=false, $activity='', $bChangesOnly=false) {
		global $TYPO3_DB, $TCA, $TSFE;

		$charset = $TSFE->renderCharset;
		$mode = $this->controlData->getMode();
		$tablesObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_tables');
		$addressObj = $tablesObj->get('address');

		if ($bChangesOnly && is_array($origRow))	{
			$mrow = array();
			foreach ($origRow as $k => $v)	{
				if ($v != $row[$k])	{
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
		$fields = $this->conf[$cmdKey.'.']['fields'];
		foreach ($this->TCA['columns'] as $colName => $colSettings) {

			if (t3lib_div::inList($fields, $colName)) {
				$colConfig = $colSettings['config'];
				$colContent = '';
				if (!$bChangesOnly || isset($mrow[$colName]))	{

					if ($mode == MODE_PREVIEW || $viewOnly) {
						// Configure preview based on input type
						switch ($colConfig['type']) {
							//case 'input':
							case 'text':
								$colContent = nl2br(htmlspecialchars($mrow[$colName],ENT_QUOTES,$charset));
								break;
							case 'check':
								if (is_array($colConfig['items'])) {
									$stdWrap = array();

									$bNotLast = FALSE;
									if (is_array($this->conf['check.']) && is_array($this->conf['check.'][$activity.'.']) &&
										is_array($this->conf['check.'][$activity.'.'][$colName.'.']) &&
										is_array($this->conf['check.'][$activity.'.'][$colName.'.']['item.']))	{
										$stdWrap = $this->conf['check.'][$activity.'.'][$colName.'.']['item.'];
										if ($this->conf['check.'][$activity.'.'][$colName.'.']['item.']['notLast'])	{
											$bNotLast = TRUE;
										}
									} else {
										$stdWrap['wrap'] = '<li>|</li>';
									}

									if (is_array($this->conf['check.']) && is_array($this->conf['check.'][$activity.'.']) &&
										is_array($this->conf['check.'][$activity.'.'][$colName.'.']) &&
										is_array($this->conf['check.'][$activity.'.'][$colName.'.']['list.']))	{
										$listWrap = $this->conf['check.'][$activity.'.'][$colName.'.']['list.'];
									} else {
										$listWrap['wrap'] = '<ul class="tx-srfeuserregister-multiple-checked-values">|</ul>';
									}

									$count = 0;
									foreach ($colConfig['items'] as $key => $value) {
										$count++;
										$label = htmlspecialchars($this->langObj->getLLFromString($colConfig['items'][$key][0]),ENT_QUOTES,$charset);
										$checked = ($mrow[$colName] & (1 << $key));
										$label = ($checked ? $label : '');
										$colContent .= ((!$bNotLast || $count < count($colConfig['items'])) ?  $this->cObj->stdWrap($label,$stdWrap) : $label);
									}
									$this->cObj->alternativeData = $colConfig['items'];
									$colContent = $this->cObj->stdWrap($colContent,$listWrap);
								} else {
									$colContent = $mrow[$colName]?htmlspecialchars($this->langObj->pi_getLL('yes'),ENT_QUOTES,$charset):htmlspecialchars($this->langObj->pi_getLL('no'),ENT_QUOTES,$charset);
								}
								break;
							case 'radio':
								if ($mrow[$colName] != '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',',$mrow[$colName]);
									$textSchema = $theTable.'.'.$colName.'.I.';
									$itemArray = $this->langObj->getItemsLL($textSchema, true);

									if (!count ($itemArray))	{
										$itemArray = $colConfig['items'];
									}

									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);

										$stdWrap = array();
										$bNotLast = FALSE;
										if (is_array($this->conf['radio.']) && is_array($this->conf['radio.'][$activity.'.']) &&
										is_array($this->conf['radio.'][$activity.'.'][$colName.'.']) &&
										is_array($this->conf['radio.'][$activity.'.'][$colName.'.']['item.']))	{
											$stdWrap = $this->conf['radio.'][$activity.'.'][$colName.'.']['item.'];
											if ($this->conf['radio.'][$activity.'.'][$colName.'.']['item.']['notLast'])	{
												$bNotLast = TRUE;
											}
										} else {
											$stdWrap['wrap'] = '| ';
										}
										for ($i = 0; $i < count ($valuesArray); $i++) {
											$label = $this->langObj->getLLFromString($itemKeyArray[$valuesArray[$i]][0]);
											$label = htmlspecialchars($label,ENT_QUOTES,$charset);
											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $this->cObj->stdWrap($label,$stdWrap) : $label);
										}
									}
								}
								break;
							case 'select':
								if ($mrow[$colName] != '') {
									$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',',$mrow[$colName]);
									$textSchema = $theTable.'.'.$colName.'.I.';
									$itemArray = $this->langObj->getItemsLL($textSchema, true);
									if (!count ($itemArray))	{
										$itemArray = $colConfig['items'];
									}
									$stdWrap = array();
									$bNotLast = FALSE;
									if (is_array($this->conf['select.']) && is_array($this->conf['select.'][$activity.'.']) &&
										is_array($this->conf['select.'][$activity.'.'][$colName.'.']) &&
										is_array($this->conf['select.'][$activity.'.'][$colName.'.']['item.']))	{
										$stdWrap = $this->conf['select.'][$activity.'.'][$colName.'.']['item.'];
										if ($this->conf['select.'][$activity.'.'][$colName.'.']['item.']['notLast'])	{
											$bNotLast = TRUE;
										}
									} else {
										$stdWrap['wrap'] = '|<br />';
									}
									if (is_array($itemArray)) {
										$itemKeyArray = $this->getItemKeyArray($itemArray);

										for ($i = 0; $i < count ($valuesArray); $i++) {
											$label = $this->langObj->getLLFromString($itemKeyArray[$valuesArray[$i]][0]);
											$label = htmlspecialchars($label,ENT_QUOTES,$charset);

											$colContent .= ((!$bNotLast || $i < count($valuesArray) - 1 ) ?  $this->cObj->stdWrap($label,$stdWrap) : $label);
										}
									}

									if ($colConfig['foreign_table']) {
										t3lib_div::loadTCA($colConfig['foreign_table']);
										$reservedValues = array();
										if ($theTable == 'fe_users' && $colName == 'usergroup') {
											$userGroupObj = &$addressObj->getFieldObj ('usergroup');
											$reservedValues = $userGroupObj->getReservedValues();
										}
										$valuesArray = array_diff($valuesArray, $reservedValues);
										reset($valuesArray);
										$firstValue = current($valuesArray);
										if (!empty($firstValue) || count ($valuesArray) > 1) {
											$titleField = $TCA[$colConfig['foreign_table']]['ctrl']['label'];
											$where = 'uid IN ('.implode(',', $valuesArray).')';

											$res = $TYPO3_DB->exec_SELECTquery(
												'*',
												$colConfig['foreign_table'],
												$where
												);
											$i = 0;
											$languageUid = $this->controlData->getSysLanguageUid('ALL',$colConfig['foreign_table']);
											while ($row2 = $TYPO3_DB->sql_fetch_assoc($res)) {

												if ($theTable == 'fe_users' && $colName == 'usergroup') {
													$row2 = $this->getUsergroupOverlay($row2);
												} else if ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row2, $languageUid)) {
													$row2 = $localizedRow;
												}
												$text = htmlspecialchars($row2[$titleField],ENT_QUOTES,$charset);
												$colContent .= $this->cObj->stdWrap($text,$stdWrap);
	// TODO: consider $bNotLast
											}
										}
									}
								}
								break;
							default:
								// unsupported input type
								$colContent .= $colConfig['type'].':'.htmlspecialchars($this->langObj->pi_getLL('unsupported'),ENT_QUOTES,$charset);
								break;
						}
					} else {
						// Configure inputs based on TCA type
						if (in_array($colConfig['type'], array('check', 'radio', 'select')))	{
								$valuesArray = is_array($mrow[$colName]) ? $mrow[$colName] : explode(',',$mrow[$colName]);

								if (!$valuesArray[0] && $colConfig['default']) {
									$valuesArray[] = $colConfig['default'];
								}
								$textSchema = $theTable.'.'.$colName.'.I.';
								$itemArray = $this->langObj->getItemsLL($textSchema, true);
								$bUseTCA = false;
								if (!count($itemArray))	{
									$itemArray = $colConfig['items'];
									$bUseTCA = true;
								}
						}

						switch ($colConfig['type']) {
							case 'input':
								$colContent = '<input type="input" name="FE['.$theTable.']['.$colName.']"'.
									' size="'.($colConfig['size']?$colConfig['size']:30).'"';
								if ($colConfig['max']) {
									$colContent .= ' maxlength="'.$colConfig['max'].'"';
								}
								if ($colConfig['default']) {
									$label = $this->langObj->getLLFromString($colConfig['default']);
									$label = htmlspecialchars($label,ENT_QUOTES,$charset);
									$colContent .= ' value="'.$label.'"';
								}
								$colContent .= ' />';
								break;
							case 'text':
								$label = $this->langObj->getLLFromString($colConfig['default']);
								$label = htmlspecialchars($label,ENT_QUOTES,$charset);
								$colContent = '<textarea id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']"'.
									' title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###"'.
									' cols="'.($colConfig['cols']?$colConfig['cols']:30).'"'.
									' rows="'.($colConfig['rows']?$colConfig['rows']:5).'"'.
									'>'.($colConfig['default']?$label:'').'</textarea>';
								break;
							case 'check':
								$label = $this->langObj->pi_getLL('tooltip_' . $colName);
								$label = htmlspecialchars($label,ENT_QUOTES,$charset);
								if (is_array($itemArray)) {
									$uidText = $this->pibase->pi_getClassName($colName).'-'.$mrow['uid'];
									$colContent  = '<ul id="'. $uidText . ' " class="tx-srfeuserregister-multiple-checkboxes">';
									foreach ($itemArray as $key => $value) {
										if ($this->controlData->getSubmit() || $cmd=='edit')	{
											$startVal = $mrow[$colName];
										} else {
											$startVal = $colConfig['default'];
										}
										$checked = ($startVal & (1 << $key))?' checked="checked"':'';
										$label = $this->langObj->getLLFromString($itemArray[$key][0]);
										$label = htmlspecialchars($label,ENT_QUOTES,$charset);
										$colContent .= '<li><input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . ' id="' . $uidText . '-' . $key .  ' " name="FE['.$theTable.']['.$colName.'][]" value="'.$key.'"'.$checked.' /><label for="' . $uidText . '-' . $key .  '">' . $label . '</label></li>';
									}
									$colContent .= '</ul>';
								} else {
									$colContent = '<input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . ' id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']" title="'.$label.'"' . ($mrow[$colName]?' checked="checked"':'') . ' />';
								}
								break;

							case 'radio':
								$startVal = $colConfig['default'];
								if (!isset($startVal))	{
									reset($colConfig['items']);
									list($startConf) = $colConfig['items'];
									$startVal = $startConf[1];
								}

								if (isset($itemArray) && is_array($itemArray))	{
									$i = 0;
									foreach ($itemArray as $key => $confArray) {
										$value = $confArray[1];
										$label = $this->langObj->getLLFromString($confArray[0]);
										$label = htmlspecialchars($label,ENT_QUOTES,$charset);
										$colContent .= '<input type="radio"' . $this->pibase->pi_classParam('radio') . ' id="'. $this->pibase->pi_getClassName($colName) . '-' . $i . '" name="FE['.$theTable.']['.$colName.']"' .
											' value="' . $value . '" ' . ($value==$startVal ? ' checked="checked"' : '') . ' />' .
											'<label for="' . $this->pibase->pi_getClassName($colName) . '-' . $i . '">' . $label . '</label>';
										$i++;
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
								if ($theTable == 'fe_users' && $colName == 'usergroup' && !$this->conf['allowMultipleUserGroupSelection']) {
									$multiple = '';
								}
								if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
									$colContent .= '
										<input id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']" value="" type="hidden" />';
									$colContent .='
										<dl class="' . $this->pibase->pi_getClassName('multiple-checkboxes') . '" title="###TOOLTIP_' . (($cmd == 'invite') ? 'INVITATION_' : '') . $this->cObj->caseshift($colName,'upper') . '###">';
								} else {
									$colContent .= '<select id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']' . $multiple . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###">';
								}

								if (is_array($itemArray)) {
									$itemArray = $this->getItemKeyArray($itemArray);
									$i = 0;
									foreach ($itemArray as $k => $item)	{
										$label = $this->langObj->getLLFromString($item[0],true);
										$label = htmlspecialchars($label,ENT_QUOTES,$charset);
										if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{

											$colContent .= '<dt><input class="' . $this->pibase->pi_getClassName('checkbox') . '" id="' . $this->pibase->pi_getClassName($colName) . '-' . $i . '" name="FE['.$theTable.']['.$colName.']['.$k.']" value="'.$k.'" type="checkbox"  ' . (in_array($k, $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
													<dd><label for="' . $this->pibase->pi_getClassName($colName) . '-' . $i .'">' . $label . '</label></dd>';
										} else {
											$colContent .= '<option value="'.$k. '" ' . (in_array($k, $valuesArray) ? 'selected="selected"' : '') . '>' . $label.'</option>';
										}
										$i++;
									}
								}
								if ($colConfig['foreign_table']) {
									t3lib_div::loadTCA($colConfig['foreign_table']);
									$titleField = $TCA[$colConfig['foreign_table']]['ctrl']['label'];
									if ($theTable == 'fe_users' && $colName == 'usergroup') {
										$userGroupObj = &$addressObj->getFieldObj ('usergroup');
										$reservedValues = $userGroupObj->getReservedValues();
										$selectedValue = false;
									}
									$whereClause = ($theTable == 'fe_users' && $colName == 'usergroup') ? ' pid='.intval($this->controlData->getPid()).' ' : ' 1=1';
									if ($TCA[$colConfig['foreign_table']] && $TCA[$colConfig['foreign_table']]['ctrl']['languageField'] && $TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField']) {
										$whereClause .= ' AND '.$TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField'].'=0';
									}
									if ($colName == 'module_sys_dmail_category' && $colConfig['foreign_table'] == 'sys_dmail_category' && $this->conf['module_sys_dmail_category_PIDLIST']) {
										$languageUid = $this->controlData->getSysLanguageUid('ALL',$colConfig['foreign_table']);
										$tmpArray = t3lib_div::trimExplode(',',$this->conf['module_sys_dmail_category_PIDLIST']);
										$pidArray = array();
										foreach ($tmpArray as $v)	{
											if (is_numeric($v))	{
												$pidArray[] = $v;
											}
										}
										$whereClause .= ' AND sys_dmail_category.pid IN (' . implode(',',$pidArray) . ')' . ' AND sys_language_uid=' . $languageUid;
									}
									$whereClause .= $this->cObj->enableFields($colConfig['foreign_table']);
									$res = $TYPO3_DB->exec_SELECTquery('*', $colConfig['foreign_table'], $whereClause, '', $TCA[$colConfig['foreign_table']]['ctrl']['sortby']);
									if (!in_array($colName, $this->controlData->getRequiredArray())) {
										if ($colConfig['renderMode'] == 'checkbox' || $colContent)	{
											// nothing
										} else {
											$colContent .= '<option value="" ' . ($valuesArray[0] ? '' : 'selected="selected"') . '></option>';
										}
									}

									while ($row2 = $TYPO3_DB->sql_fetch_assoc($res)) {
										if ($theTable == 'fe_users' && $colName == 'usergroup') {
											if (!in_array($row2['uid'], $reservedValues)) {
												$row2 = $this->getUsergroupOverlay($row2);
												$titleText = htmlspecialchars($row2[$titleField],ENT_QUOTES,$charset);
												$selected = (in_array($row2['uid'], $valuesArray) ? 'selected="selected"' : '');
												if(!$this->conf['allowMultipleUserGroupSelection'] && $selectedValue) {
													$selected = '';
												}
												$selectedValue = $selected ? true: $selectedValue;
												if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
													$colContent .= '<dt><input  class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row2['uid'].'"]" value="'.$row2['uid'].'" type="checkbox"' . ($selected ? ' checked="checked"':'') . ' /></dt>
													<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'">'.$titleText.'</label></dd>';
												} else {
													$colContent .= '<option value="'.$row2['uid'].'"' . $selected . '>'.$titleText.'</option>';
												}
											}
										} else {
											$languageUid = $this->controlData->getSysLanguageUid('ALL',$colConfig['foreign_table']);
											if ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row2, $languageUid)) {
												$row2 = $localizedRow;
											}
											$titleText = htmlspecialchars($row2[$titleField],ENT_QUOTES,$charset);
											if ($colConfig['renderMode']=='checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
												$colContent .= '<dt><input class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row2['uid']. ']" value="'.$row2['uid'].'" type="checkbox"' . (in_array($row2['uid'], $valuesArray) ? ' checked="checked"' : '') . ' /></dt>
												<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'">'.$titleText.'</label></dd>';

											} else {
												$colContent .= '<option value="'.$row2['uid'].'"' . (in_array($row2['uid'], $valuesArray) ? 'selected="selected"' : '') . '>'.$titleText.'</option>';
											}
										}
									}
								}
								if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
									$colContent .= '</dl>';
								} else {
									$colContent .= '</select>';
								}
								break;
							default:
								$colContent .= $colConfig['type'].':'.$this->langObj->pi_getLL('unsupported');
								break;
						}
					}
				} else {
					$colContent = '';
				}

				if ($mode == MODE_PREVIEW || $viewOnly) {
					$markerArray['###TCA_INPUT_VALUE_'.$colName.'###'] = $colContent;
				}
				$markerArray['###TCA_INPUT_'.$colName.'###'] = $colContent;
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
	function getItemKeyArray ($itemArray) {
		$rc = array();

		if (is_array($itemArray))	{
			foreach ($itemArray as $k => $row)	{
				$key = $row[1];
				$rc [$key] = $row;
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
	function getUsergroupOverlay ($usergroup, $languageUid='') {
		global $TYPO3_DB;
		// Initialize:
		if ($languageUid=='') {
			$languageUid = $this->controlData->getSysLanguageUid('ALL','fe_groups_language_overlay');
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
				$whereClause = 'fe_group=' . intval($fe_groups_uid) . ' ' .
					'AND sys_language_uid='.intval($languageUid). ' ' .
					$this->cObj->enableFields('fe_groups_language_overlay');
				$res = $TYPO3_DB->exec_SELECTquery(implode(',', $fieldArr), 'fe_groups_language_overlay', $whereClause);
				if ($TYPO3_DB->sql_num_rows($res)) {
					$row = $TYPO3_DB->sql_fetch_assoc($res);
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
