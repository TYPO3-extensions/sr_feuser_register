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
	var $lang;

	var $TCA = array();
	var $extKey;
	var $sys_language_content;
	var $cObj;


	function init(&$pibase, &$conf, &$config, &$data, &$control, &$lang)	{
		global $TSFE, $TCA, $TYPO3_CONF_VARS;

		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->data = &$data;
		$this->control = &$control;
		$this->lang = &$lang;

		$this->extKey = $pibase->extKey;
		$this->sys_language_content = $pibase->sys_language_content;
		$this->cObj = &$pibase->cObj;

			// get the table definition
		$TSFE->includeTCA();
		$this->TCA = $TCA[$this->data->getTable()];
		if ($TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['uploadFolder'])	{
			$this->TCA[$this->data->getTable()]['columns']['image']['config']['uploadfolder'] = $TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['uploadFolder'];
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
	* @param array  $dataArray: the record array
	* @return void
	*/
	function addTcaMarkers(&$markerArray, $dataArray = '', $viewOnly = false, $activity='') {
		global $TYPO3_DB, $TCA, $TSFE;
		$cmd = $this->control->getCmd();
		$cmdKey = $this->control->getCmdKey();
		$theTable = $this->data->getTable();

		foreach ($this->TCA['columns'] as $colName => $colSettings) {
			if (t3lib_div::inList($this->conf[$cmdKey.'.']['fields'], $colName)) {
				$colConfig = $colSettings['config'];
				$colContent = '';
				if ($this->control->getMode() == MODE_PREVIEW || $viewOnly) {
					// Configure preview based on input type
					switch ($colConfig['type']) {
						//case 'input':
						case 'text':
							$colContent = nl2br(htmlspecialchars($dataArray[$colName]));
							break;
						case 'check':
							// <Ries van Twisk added support for multiple checkboxes>
							if (is_array($colConfig['items'])) {
								$colContent = '<ul class="tx-srfeuserregister-multiple-checked-values">';
								foreach ($colConfig['items'] AS $key => $value) {
									$checked = ($dataArray[$colName] & (1 << $key)) ? 'checked' : '';
									$colContent .= $checked ? '<li>' . $this->lang->getLLFromString($colConfig['items'][$key][0]) . '</li>' : '';
								}
								$colContent .= '</ul>';
								// </Ries van Twisk added support for multiple checkboxes>
							} else {
								$colContent = $dataArray[$colName]?$this->lang->pi_getLL('yes'):$this->lang->pi_getLL('no');
							}
							break;
						case 'radio':
							if ($dataArray[$colName] != '') {
								$colContent = $this->lang->getLLFromString($colConfig['items'][$dataArray[$colName]][0]);
							}
							break;
						case 'select':
							if ($dataArray[$colName] != '') {
								$valuesArray = is_array($dataArray[$colName]) ? $dataArray[$colName] : explode(',',$dataArray[$colName]);
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
										while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
											if ($theTable == 'fe_users' && $colName == 'usergroup') {
												$row = $this->data->getUsergroupOverlay($row);
											} elseif ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row, $this->sys_language_content)) {
												$row = $localizedRow;
											}
											$colContent .= ($i++ ? '<br />': '') . $row[$titleField];
										}
									}
								}
							}
							break;
						default:
							// unsupported input type
							$colContent .= $colConfig['type'].':'.$this->lang->pi_getLL('unsupported');
					}
				} else {
					// Configure inputs based on TCA type
					switch ($colConfig['type']) {
	/*
						case 'input':
							$colContent = '<input type="input" name="FE['.$this->theTable.']['.$colName.']"'.
								' size="'.($colConfig['size']?$colConfig['size']:30).'"';
							if ($colConfig['max']) {
									$colContent .= ' maxlength="'.$colConfig['max'].'"';
							}
							if ($colConfig['default']) {
								$colContent .= ' value="'.$this->getLLFromString($colConfig['default']).'"';
							}
							$colContent .= ' />';
							break;
	*/
						case 'text':
							$colContent = '<textarea id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']"'.
								' title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###"'.
								' cols="'.($colConfig['cols']?$colConfig['cols']:30).'"'.
								' rows="'.($colConfig['rows']?$colConfig['rows']:5).'"'.
								'>'.($colConfig['default']?$this->lang->getLLFromString($colConfig['default']):'').'</textarea>';
							break;
						case 'check':
							if (is_array($colConfig['items'])) {
								// <Ries van Twisk added support for multiple checkboxes>
								$colContent  = '<ul id="'. $this->pibase->pi_getClassName($colName) . '" class="tx-srfeuserregister-multiple-checkboxes">';
								foreach ($colConfig['items'] AS $key => $value) {
									if ($cmd == 'create' || $cmd == 'invite') {
										$checked = ($colConfig['default'] & (1 << $key))?'checked="checked"':'';
									} else {
										$checked = ($dataArray[$colName] & (1 << $key))?'checked="checked"':'';
									}
									$colContent .= '<li><input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . 'id="' . $this->pibase->pi_getClassName($colName) . '-' . $key .  '" name="FE['.$theTable.']['.$colName.'][]" value="'.$key.'" '.$checked.' /><label for="' . $this->pibase->pi_getClassName($colName) . '-' . $key .  '">'.$this->lang->getLLFromString($colConfig['items'][$key][0]).'</label></li>';					
								}
								$colContent .= '</ul>';
								// </Ries van Twisk added support for multiple checkboxes>
							} else {
								$colContent = '<input type="checkbox"' . $this->pibase->pi_classParam('checkbox') . 'id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']"' . ($dataArray[$colName]?'checked="checked"':'') . ' />';
							}
							break;

						case 'radio':
							for ($i = 0; $i < count ($colConfig['items']); ++$i) {
								$colContent .= '<input type="radio"' . $this->pibase->pi_classParam('radio') . ' id="'. $this->pibase->pi_getClassName($colName) . '-' . $i . '" name="FE['.$theTable.']['.$colName.']"'.
										' value="'.$i.'" '.($i==0?'checked="checked"':'').' />' .
										'<label for="' . $this->pibase->pi_getClassName($colName) . '-' . $i . '">' . $this->lang->getLLFromString($colConfig['items'][$i][0]) . '</label>';
							}
							break;

						case 'select':
							$colContent ='';
							$valuesArray = is_array($dataArray[$colName]) ? $dataArray[$colName] : explode(',',$dataArray[$colName]);
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
										<input id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']" value="" type="hidden" />';
								$colContent .='
										<dl class="' . $this->pibase->pi_getClassName('multiple-checkboxes') . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###">';
							} else {
								$colContent .= '<select id="'. $this->pibase->pi_getClassName($colName) . '" name="FE['.$theTable.']['.$colName.']' . $multiple . '" title="###TOOLTIP_' . (($cmd == 'invite')?'INVITATION_':'') . $this->cObj->caseshift($colName,'upper').'###">';
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
								$whereClause = ($theTable == 'fe_users' && $colName == 'usergroup') ? ' pid='.intval($this->control->thePid).' ' : ' 1=1';
								if ($TCA[$colConfig['foreign_table']] && $TCA[$colConfig['foreign_table']]['ctrl']['languageField'] && $TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField']) {
									$whereClause .= ' AND '.$TCA[$colConfig['foreign_table']]['ctrl']['transOrigPointerField'].'=0';
								}
								if ($colName == 'module_sys_dmail_category' && $colConfig['foreign_table'] == 'sys_dmail_category' && $this->conf['module_sys_dmail_category_PIDLIST']) {
									$whereClause .= ' AND sys_dmail_category.pid IN (' . $TYPO3_DB->fullQuoteStr($this->conf['module_sys_dmail_category_PIDLIST'], 'sys_dmail_category') . ')';
								}
								$whereClause .= $this->cObj->enableFields($colConfig['foreign_table']);
								$res = $TYPO3_DB->exec_SELECTquery('*', $colConfig['foreign_table'], $whereClause, '', $TCA[$colConfig['foreign_table']]['ctrl']['sortby']);
								if (!in_array($colName, $this->control->getRequiredArray())) {
									if ($colConfig['renderMode'] == 'checkbox' || $colContent)	{
										// nothing
									} else {
										$colContent .= '<option value="" ' . ($valuesArray[0] ? '' : 'selected="selected"') . '></option>';
									}
								}
								while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
									if ($theTable == 'fe_users' && $colName == 'usergroup') {
										if (!in_array($row['uid'], $reservedValues)) {
											$row = $this->data->getUsergroupOverlay($row);
											$selected = (in_array($row['uid'], $valuesArray) ? 'selected="selected"' : '');
											if(!$this->conf['allowMultipleUserGroupSelection'] && $selectedValue) {
												$selected = '';
											}
											$selectedValue = $selected ? true: $selectedValue;
											if ($colConfig['renderMode'] == 'checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
												$colContent .= '<dt><input  class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row['uid'].'"]" value="'.$row['uid'].'" type="checkbox" ' . $selected ?'checked="checked"':'' . ' /></dt>
												<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $row['uid'] .'">'.$row[$titleField].'</label></dd>';
											} else {
												$colContent .= '<option value="'.$row['uid'].'"' . $selected . '>'.$row[$titleField].'</option>';
											}
										}
									} else {
										if ($localizedRow = $TSFE->sys_page->getRecordOverlay($colConfig['foreign_table'], $row, $this->sys_language_content)) {
											$row = $localizedRow;
										}
										if ($colConfig['renderMode']=='checkbox' && $this->conf['templateStyle'] == 'css-styled')	{
											$colContent .= '<dt><input  class="' . $this->pibase->pi_getClassName('checkbox') . '" id="'. $this->pibase->pi_getClassName($colName) . '-' . $row['uid'] .'" name="FE['.$theTable.']['.$colName.']['.$row['uid']. ']" value="'.$row['uid'].'" type="checkbox" ' . (in_array($row['uid'], $valuesArray) ? 'checked="checked"' : '') . ' /></dt>
											<dd><label for="'. $this->pibase->pi_getClassName($colName) . '-' . $row['uid'] .'">'.$row[$titleField].'</label></dd>';
										} else {
											$colContent .= '<option value="'.$row['uid'].'"' . (in_array($row['uid'], $valuesArray) ? 'selected="selected"' : '') . '>'.$row[$titleField].'</option>';
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
					}
				}
				if ($this->control->getMode() == MODE_PREVIEW || $viewOnly) {
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


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_tca.php']);
}
?>
