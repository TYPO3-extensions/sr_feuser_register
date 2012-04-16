<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2011 Stanislas Rolland (stanislas.rolland@sjbr.ca)
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
 * language functions
 *
 * $Id$
 *
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


require_once(PATH_BE_div2007 . 'class.tx_div2007_alpha_language_base.php');


class tx_srfeuserregister_lang extends tx_div2007_alpha_language_base {
	public $pibase;
	public $allowedSuffixes = array('formal', 'informal'); // list of allowed suffixes


// 	public function init (&$pibase, &$conf, $LLkey)	{
	public function init ($pObj, $cObj, $conf, $scriptRelPath, $extKey) {

		$this->pibase = $pObj;

		parent::init(
			$cObj,
			$extKey,
			$conf,
			$scriptRelPath
		);

		// keep previsous language settings if available
		if (isset($pObj->LOCAL_LANG) && is_array($pObj->LOCAL_LANG)) {
			$this->LOCAL_LANG = &$pObj->LOCAL_LANG;
		}
		if (isset($pObj->LOCAL_LANG_charset) && is_array($pObj->LOCAL_LANG_charset)) {
			$this->LOCAL_LANG_charset = &$pObj->LOCAL_LANG_charset;
		}
		if (isset($pObj->LOCAL_LANG_loaded) && is_array($pObj->LOCAL_LANG_loaded)) {
			$this->LOCAL_LANG_loaded = &$pObj->LOCAL_LANG_loaded;
		}
	}


	public function getLLFromString ($string, $bForce=TRUE) {
		global $LOCAL_LANG, $TSFE;

		$rc = '';
		$arr = explode(':', $string);

		if($arr[0] == 'LLL' && $arr[1] == 'EXT') {
			$temp = $this->getLL($arr[3]);
			if ($temp || !$bForce) {
				$rc = $temp;
			} else {
				$rc = $TSFE->sL($string);
			}
		} else {
			$rc = $string;
		}

		return $rc;
	}	// getLLFromString


	/**
	* Get the item array for a select if configured via TypoScript
	* @param	string	name of the field
	* @ return	array	array of selectable items
	*/
	public function getItemsLL ($textSchema, $bAll = TRUE, $valuesArray = array()) {
		$rc = array();
		if ($bAll) {
			for ($i = 0; $i < 999; ++$i) {
				$text = $this->getLL($textSchema . $i);
				if ($text != '') {
					$rc[] = array($text, $i);
				}
			}
		} else {
			foreach ($valuesArray as $k => $i) {
				$text = $this->getLL($textSchema . $i);
				if ($text != '') {
					$rc[] = array($text, $i);
				}
			}
		}
		return $rc;
	}	// getItemsLL


	/**
		* Returns the localized label of the LOCAL_LANG key, $key
		* In $this->conf['salutation'], a suffix to the key may be set (which may be either 'formal' or 'informal').
		* If a corresponding key exists, the formal/informal localized string is used instead.
		* If the key doesn't exist, we just use the normal string.
		*
		* Example: key = 'greeting', suffix = 'informal'. If the key 'greeting_informal' exists, that string is used.
		* If it doesn't exist, we'll try to use the string with the key 'greeting'.
		*
		* Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
		*
		* @param    string        The key from the LOCAL_LANG array for which to return the value.
		* @param    string        Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
		* @param    boolean        If true, the output label is passed through htmlspecialchars()
		* @return    string        The value from LOCAL_LANG.
		*/
	public function getLL ($key, $alt = '', $hsc = FALSE) {

			// If the suffix is allowed and we have a localized string for the desired salutation, we'll take that.
		$localizedLabel = '';
		$usedLang = '';

		if (
			isset($this->conf['salutation']) &&
			in_array($this->conf['salutation'], $this->allowedSuffixes, 1)
		) {
			$expandedKey = $key . '_' . $this->conf['salutation'];
			$localizedLabel =
				tx_div2007_alpha5::getLL_fh002(
					$this,
					$expandedKey,
					$usedLang,
					$alt,
					$hsc
				);
					// To do: fix incomplete language array
				if ($localizedLabel == '') {
					$localizedLabel = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:' . $expandedKey);
				}
		}

		if ($localizedLabel == '' || $localizedLabel == $alt || $usedLang != $this->LLkey) {
			$localizedLabel =
				tx_div2007_alpha5::getLL_fh002(
					$this,
					$key,
					$usedLang,
					$alt,
					$hsc
				);
				// To do: fix incomplete language array
			if ($localizedLabel == '') {
				$localizedLabel = $GLOBALS['TSFE']->sL('LLL:EXT:' . $this->extKey . '/pi1/locallang.xml:' . $key);
			}
		}

		return $localizedLabel;
	}	// getLL


	public function loadLL () {
		$rc = TRUE;

			// flatten the structure of labels overrides
		if (is_array($this->conf['_LOCAL_LANG.'])) {
			$done = FALSE;
			$i = 0;
			while(!$done && $i < 10000) {
				$done = TRUE;
				foreach($this->conf['_LOCAL_LANG.'] as $k => $lA) {
					if (is_array($lA)) {
						foreach($lA as $llK => $llV) {
							if (is_array($llV)) {
								foreach ($llV as $llK2 => $llV2) {
									if (is_array($llK2)) {
										foreach ($llV2 as $llK3 => $llV3) {
											if (is_array($llV3)) {
												foreach ($llV3 as $llK4 => $llV4) {
													$this->conf['_LOCAL_LANG.'][$k][$llK . $llK2 . $llK3 . $llK4] = $llV4;
												}
											} else {
												$this->conf['_LOCAL_LANG.'][$k][$llK . $llK2 . $llK3] = $llV3;
											}
										}
									} else {
										$this->conf['_LOCAL_LANG.'][$k][$llK . $llK2] = $llV2;
									}
								}
								unset($this->conf['_LOCAL_LANG.'][$k][$llK]);
								$done = FALSE;
								++$i;
							}
						}
					}
				}
			}
		}
		$locallang = $this->LOCAL_LANG;
		tx_div2007_alpha5::loadLL_fh002($this);

		if ($locallang != '') {
			foreach ($this->LOCAL_LANG as $key => $langArray) {
				if (isset($locallang[$key]) && is_array($locallang[$key])) {
					$this->LOCAL_LANG[$key] = array_merge($locallang[$key], $langArray);
				}
			}
		}

		// do a check if the language file works
		$tmpText = $this->getLL('unsupported');

		if ($tmpText == '') {
			$rc = FALSE;
		}

		return $rc;
	}	// loadLL
} // class tx_srfeuserregister_lang


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_lang.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_lang.php']);
}
?>