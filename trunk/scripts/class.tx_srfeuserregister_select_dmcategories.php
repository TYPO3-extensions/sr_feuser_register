<?php

/*
 *  Copyright notice
 *
 *  (c) 2013-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * Localize categories in backend forms
 */
class tx_srfeuserregister_select_dmcategories {
	public $sys_language_uid = 0;
	public $collate_locale = 'C';

	/**
	 * Get the localization of the select field items (right-hand part of form)
	 * Referenced by TCA
	 *
	 * @param	array		$params: array of searched translation
	 * @return	void		...
	 */
	public function get_localized_categories ($params) {

/*
		$params['items'] = &$items;
		$params['config'] = $config;
		$params['TSconfig'] = $iArray;
		$params['table'] = $table;
		$params['row'] = $row;
		$params['field'] = $field;
*/

		$items = $params['items'];
		$config = $params['config'];
		$table = $config['itemsProcFunc_config']['table'];

			// initialize backend user language
		if ($GLOBALS['LANG']->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				//'sys_language.uid,static_languages.lg_collate_locale',
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($GLOBALS['LANG']->lang,'static_languages')
					. t3lib_pageSelect::enableFields('sys_language')
					. t3lib_pageSelect::enableFields('static_languages')
				);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
				$this->collate_locale = $row['lg_collate_locale'];
			}
		}
		if (is_array($params['items'])) {
			reset($params['items']);
			while(list($k, $item) = each($params['items'])) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					$table,
					'uid='.intval($item[1])
					);
				while($rowCat = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$localizedRowCat =
						tx_srfeuserregister_dmstatic::getRecordOverlay(
							$table,
							$rowCat,
							$this->sys_language_uid,
							''
						);
					if($localizedRowCat) {
						$params['items'][$k][0] = $localizedRowCat['category'];
					}
				}
			}
		}
	}
}