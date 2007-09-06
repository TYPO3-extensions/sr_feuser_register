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
 * TCA functions
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



class tx_srfeuserregister_tca {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $data;
	var $control;
	var $controlData;
	var $lang;

	var $TCA = array();
	var $sys_language_content;
	var $cObj;


	function init(&$pibase, &$conf, &$config, &$controlData, &$lang, $extKey)	{
		global $TSFE, $TCA, $TYPO3_CONF_VARS;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->controlData = &$controlData;
		$this->lang = &$lang;

		$this->sys_language_content = $pibase->sys_language_content;
		$this->cObj = &$pibase->cObj;

			// get the table definition
		$TSFE->includeTCA();
		$this->TCA = $TCA[$this->controlData->getTable()];
		if ($TYPO3_CONF_VARS['EXTCONF'][$extKey]['uploadFolder'])	{
			$this->TCA[$this->controlData->getTable()]['columns']['image']['config']['uploadfolder'] = $TYPO3_CONF_VARS['EXTCONF'][$extKey]['uploadFolder'];
		}
	}

	function &getTCA()	{
		return $this->TCA;
	}

	/**
	* Adds the fields coming from other tables via MM tables
	*
	* @param array  $dataArray: the record array
	* @return array  the modified data array
	*/
	function modifyTcaMMfields($dataArray, &$modArray) {
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
	* Adds form element markers from the Table Configuration Array to a marker array
	*
	* @param array  $markerArray: the input marker array
	* @param array  $row: the record
	* @return void
	*/
	function addTcaMarkers(&$markerArray, $row = '', $viewOnly = false, $activity='') {
		global $TYPO3_DB, $TCA, $TSFE;
		$cmd = $this->controlData->getCmd();
		$cmdKey = $this->controlData->getCmdKey();
		$theTable = $this->controlData->getTable();
		$charset = $TSFE->renderCharset;

		foreach ($this->TCA['columns'] as $colName => $colSettings) {
			if (t3lib_div::inList($this->conf[$cmdKey.'.']['fields'], $colName)) {
				$colConfig = $colSettings['config'];
				$colContent = '';
				if ($this->controlData->getMode() == MODE_PREVIEW || $viewOnly) {
					// Configure preview based on input type
					switch ($colConfig['type']) {
						//case 'input':
						case 'text':
							$colContent = nl2br(htmlspecialchars($row[$colName],ENT_QUOTES,$charset));
							break;
						case 'check':
							// <Ries van Twisk added support for multiple checkboxes>
							if (is_array($colConfig['items'])) {
								$colContent = '<ul class="tx-srfeuserregister-multiple-checked-values">';
								foreach ($colConfig['items'] as $key => $value) {
									$label = htmlspecialchars($this->lang->getLLFromString($colConfig['items'][$key][0]),ENT_QUOTES,$charset);
									$checked = ($row[$colName] & (1 << $key)) ? 'checked' : '';
									$colContent .= $checked ? '<li>' . $label . '</li>' : '';
								}
								$colContent .= '</ul>';
								// </Ries van Twisk added support for multiple checkboxes>
							} else {
								$colContent = $row[$colName]?htmlspecialchars($this->lang->pi_getLL('yes'),ENT_QUOTES,$charset):htmlspecialchars($this->lang->pi_getLL('no'),ENT_QUOTES,$charset);
							}
							break;
						case 'radio':
							if ($row[$colName] != '') {
								$colContent = htmlspecialchars($this->lang->getLLFromString($colConfig['items'][$row[$colName]][0],ENT_QUOTES,$charset));
							}
							break;
						case 'select':
							if ($row[$colName] != '') {
								$valuesArray = is_array($row[$colName]) ? $row[$colName] : explode(',',$row[$colName]);
								$textSchema = 'fe_users.'.$colName.'.I.';
								$itemArray = $this->lang->getItemsLL($textSchema, true);
								$bUseTCA = false;
								if (!count ($itemArray))	{
									$itemArray = $colConfig['items'];
									$bUseTCA = true;
								}

								if (is_array($itemArray)) {
									$itemKeyArray = $this->getItemKeyArray($itemArray);

									$stdWrap = array();
									if (is_array($this->conf['select.']) && is_array($this->conf['select.'][$activity.'.']) && is_array($this->conf['select.'][$activity.'.'][$colName.'.']))	{
										$stdWrap = $this->conf['select.'][$activity.'.'][$colName.'.'];
									} else {
										$stdWrap['wrap'] = '|<br />';
									}

									for ($i = 0; $i < count ($valuesArray); $i++) {
										$text = $this->lang->getLLFromString($itemKeyArray[$valuesArray[$i]][0]);
										$text = htmlspecialchars($text,ENT_QUOTES,$charset);
										$colContent .= $this->cObj->stdWrap($text,$stdWrap);
									}
								}
								if ($colConfig['foreign_table']) {
									t3lib_div::loadTCA($colConfig['foreign_table']);
									$reservedValues = array();
									if ($theTable == 'fe_users' && $colName == 'usergroup') {
										$reservedValues = array_merge(t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['APPROVE.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['ACCEPT.']['usergroup'],1));
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
										while ($row2 = $TYPO3_DB->sql_fetch_assoc($res)) {
											if ($theTable == 'fe_users' && $colName == 'usergroup') {
												$row2 = $this->getUsergroupOverlay($row2);
											} elseif ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row2, $this->sys_language_content)) {
												$row2 = $localizedRow;
											}
											$colContent .= ($i++ ? '<br />': '') . htmlspecialchars($row2[$titleField],ENT_QUOTES,$charset);
										}
									}
								}
							}
							break;
						default:
							// unsupported input type
							$colContent .= $colConfig['type'].':'.htmlspecialchars($this->lang->pi_getLL('unsupported'),ENT_QUOTES,$charset);
					}
				} else {

					// Configure inputs based on TCA type
					switch ($colConfig['type']) {
						case 'input':
							$colContent = '<input type="input" name="FE['.$this->theTable.']['.$colName.']"'.
								' size="'.($colConfig['size']?$colConfig['size']:30).'"';
							if ($colConfig['max']) {
									$colContent .= ' maxlength="'.$colConfig['max'].'"';
							}
							if ($colConfig['default']) {
								$label = $this->getLLFromString($colConfig['default']);
								$label = htmlspecialchars($label,ENT_QUOTES,$charset);
								$colContent .= ' value="'.$label.'"';
							}
							$colContent .= ' />';
							break;
	
						case 'text':
							$label = $this->getLLFromString($colConfig['default']);
							$label = htmlspecialchars($label,ENT_QUOTES,$charset);
							$colContent = '<textarea id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']"'.
								' title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###"'.
								' cols="'.($colConfig['cols']?$colConfig['cols']:30).'"'.
								' rows="'.($colConfig['rows']?$colConfig['rows']:5).'"'.
								'>'.($colConfig['default']?$label:'').'</textarea>';
							break;
						case 'check':
							if (is_array($colConfig['items'])) {
								// <Ries van Twisk added support for multiple checkboxes>
								$uidText = $this->pibase->pi_getClassName($colName)'-'.$row['uid'];
								$colContent  = '<ul id="'. $uidText . ' " class="tx-srfeuserregister-multiple-checkboxes">';
								foreach ($colConfig['items'] as $key => $value) {
									if ($cmd == 'create' || $cmd == 'invite') {
										$checked = ($colConfig['default'] & (1 << $key))?'checked="checked"':'';
									} else {
										$checked = ($row[$colName] & (1 << $key))?'checked="checked"':'';
									}
									$label = $this->lang->getLLFromString($colConfig['items'][$key][0]);
									$label = htmlspecialchars($label,ENT_QUOTES,$charset);
									$colContent .= '<li><input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . ' id="' . $uidText . '-' . $key .  ' " name="FE['.$theTable.']['.$colName.'][]" value="'.$key.'" '.$checked.' /><label for="' . $uidText . '-' . $key .  '">' . $label . '</label></li>';
								}
								$colContent .= '</ul>';
							} else {
								$colContent = '<input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . ' id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']"' . ($row[$colName]?'checked="checked"':'') . ' />';
							}
							break;

						case 'radio':
							for ($i = 0; $i < count ($colConfig['items']); ++$i) {
								$label = $this->lang->getLLFromString($colConfig['items'][$i][0]);
								$label = htmlspecialchars($label,ENT_QUOTES,$charset);
								$colContent .= '<input type="radio"' . $this->pibase->pi_classParam('radio') . ' id="'. $this->pibase->pi_getClassName($colName) . '-' . $i . '" name="FE['.$theTable.']['.$colName.']"'.
										' value="'.$i.'" '.($i==0?'checked="checked"':'').' />' .
										'<label for="' . $this->pibase->pi_getClassName($colName) . '-' . $i . '">' . $label . '</label>';
							}
							break;

						case 'select':
							$colContent ='';
							$valuesArray = is_array($row[$colName]) ? $row[$colName] : explode(',',$row[$colName]);
							if (!$valuesArray[0] && $colConfig['default']) {
								$valuesArray[] = $colConfig['default'];
							}
							if ($colConfig['maxitems'] > 1) {
								$multiple = '[]" multiple="multiple';
							} else {
								$multiple = '';
							}
							if ($theTable == 'fe_users' && $colName == 'usergroup' && !$this->conf['allowMultipleUserGroupSelection']) {
								$multiple = '';
							}
							if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
								$colContent .='
										<input id="'. $this->pibase->pi_getClassName($colName) . ' " name="FE['.$theTable.']['.$colName.']" value="" type="hidden" />';
								$colContent .='
										<dl class="' . $this->pibase->pi_getClassName('multiple-checkboxes') . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###">';
							} else {
								$colContent .= '<select id="'. $this->pibase->pi_getClassName($colName) . ' " name="FE['.$theTable.']['.$colName.']' . $multiple . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###">';
							}
							$textSchema = 'fe_users.'.$colName.'.I.';
							$itemArray = $this->lang->getItemsLL($textSchema, true);
							$bUseTCA = false;
							if (!count ($itemArray))	{
								$itemArray = $colConfig['items'];
								$bUseTCA = true;
							}

							if (is_array($itemArray)) {
								$itemArray = $this->getItemKeyArray($itemArray);
								$i = 0;
								if ($bUseTCA)	{
									$deftext = $itemArray[$i][0];
									$deftext = substr($deftext, 0, strlen($deftext) - 2);
								}

								$i = 0;
								foreach ($itemArray as $k => $item)	{
									$label = $this->lang->getLLFromString($item[0],true);
									$label = htmlspecialchars($label,ENT_QUOTES,$charset);
									if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
										$colContent .= '<dt><input class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $i .'" name="FE['.$theTable.']['.$colName.']['.$k.']" value="'.$k.'" type="checkbox"  ' . (in_array($k, $valuesArray) ? 'checked="checked"' : '') . ' /></dt>
												<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $i .'">'.$label.'</label></dd>';
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
									$reservedValues = array_merge(t3lib_div::trimExplode(',', $this->conf['create.']['overrideValues.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['APPROVE.']['usergroup'],1), t3lib_div::trimExplode(',', $this->conf['setfixed.']['ACCEPT.']['usergroup'],1));
									$selectedValue = false;
								}
								$whereClause = ($theTable == 'fe_users' && $colName == 'usergroup') ? ' pid='.intval($this->controlData->getPid()).' ' : ' 1=1';
								if ($TCA[$colConfig['foreign_table']] && $TCA[$colConfig['foreign_table']]['ctrl']['languageField'] && $TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField']) {
									$whereClause .= ' AND '.$TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField'].'=0';
								}
								if ($colName == 'module_sys_dmail_category' && $colConfig['foreign_table'] == 'sys_dmail_category' && $this->conf['module_sys_dmail_category_PIDLIST']) {
									$whereClause .= ' AND sys_dmail_category.pid IN (' . $TYPO3_DB->fullQuoteStr($this->conf['module_sys_dmail_category_PIDLIST'], 'sys_dmail_category') . ')';
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
												$colContent .= '<dt><input  class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row2['uid'].'"]" value="'.$row['uid'].'" type="checkbox" ' . $selected ?'checked="checked"':'' . ' /></dt>
												<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'">'.$titleText.'</label></dd>';
											} else {
												$colContent .= '<option value="'.$row2['uid'].'"' . $selected . '>'.$titleText.'</option>';
											}
										}
									} else {
										if ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row2, $this->sys_language_content)) {
											$row2 = $localizedRow;
										}
										$titleText = htmlspecialchars($row2[$titleField],ENT_QUOTES,$charset);
										if ($colConfig['renderMode']=='checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
											$colContent .= '<dt><input  class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row2['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row2['uid']. ']" value="'.$row2['uid'].'" type="checkbox" ' . (in_array($row2['uid'], $valuesArray) ? 'checked="checked"' : '') . ' /></dt>
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
							$colContent .= $colConfig['type'].':'.$this->lang->pi_getLL('unsupported');
							break;
					}
				}
				if ($this->controlData->getMode() == MODE_PREVIEW || $viewOnly) {
					$markerArray['###TCA_INPUT_VALUE_'.$colName.'###'] = $colContent;
				}
				$markerArray['###TCA_INPUT_'.$colName.'###'] = $colContent;
			}
		}
	}
	// <Ries van Twisk added support for multiple checkboxes>


	/**
	* Transfers the item array to one where the key corresponds to the value
	* @param	array	array of selectable items like found in TCA
	* @ return	array	array of selectable items with correct key
	*/
	function getItemKeyArray($itemArray) {
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
		* @param	integer		Language UID if you want to set an alternative value to $this->pibase->sys_language_content which is default. Should be >=0
		* @return	array		usergroup row which is overlayed with language_overlay record (or the overlay record alone)
		*/
	function getUsergroupOverlay($usergroup, $languageUid = -1) {
		global $TYPO3_DB;
		// Initialize:
		if ($languageUid < 0) {
			$languageUid = $this->pibase->sys_language_content;
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php']);
}
?>
