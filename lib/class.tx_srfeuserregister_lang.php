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
 * language functions
 *
 * $Id$
 *
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_lang {
	
	protected $pibaseObj;

	// List of allowed suffixes
	public $allowedSuffixes = array('formal', 'informal');

	public function init ($pibaseObj) {
		$this->pibaseObj = $pibaseObj;
		$this->pibaseObj->pi_loadLL();
		if (isset($this->pibaseObj->conf['_LOCAL_LANG.'])) {
			// Clear the "unset memory"
			//$this->pibaseObj->LOCAL_LANG_UNSET = array();
			foreach ($this->pibaseObj->conf['_LOCAL_LANG.'] as $languageKey => $languageArray) {
				// Remove the dot after the language key
				$languageKey = substr($languageKey, 0, -1);
				// Don't process label if the language is not loaded
				if (is_array($languageArray) && isset($this->pibaseObj->LOCAL_LANG[$languageKey])) {
					foreach ($languageArray as $labelKey => $labelValue) {
						if (!is_array($labelValue)) {
							if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4006000) {
								$this->pibaseObj->LOCAL_LANG[$languageKey][$labelKey][0]['target'] = $labelValue;
								$this->pibaseObj->LOCAL_LANG_charset[$languageKey][$labelKey] = 'utf-8';
							} else {
								$this->pibaseObj->LOCAL_LANG[$languageKey][$labelKey] = $labelValue;
							}
						} else {
							$labelValue = $this->flattenTypoScriptLabelArray($labelValue, $labelKey);
							$this->pibaseObj->LOCAL_LANG[$languageKey] = array_merge($this->pibaseObj->LOCAL_LANG[$languageKey], $labelValue);

						}
					}
				}
			}
		}
	}

	public function getLLFromString ($string, $force = TRUE) {
		$label = '';
		$arr = explode(':', $string);
		if ($arr[0] === 'LLL' && $arr[1] === 'EXT') {
			$temp = $this->getLL($arr[3]);
			if ($temp || !$force) {
				$label = $temp;
			} else {
				$label = $GLOBALS['TSFE']->sL($string);
			}
		} else {
			$label = $string;
		}

		return $label;
	}

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
	}

	/**
	 * From the 'salutationswitcher' extension.
	 *
	 * @author	Oliver Klee <typo-coding@oliverklee.de>
	 */
	
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
	public function getLL($key, $alt = '', $hsc = FALSE) {
		// If the suffix is allowed and we have a localized string for the desired salutation, we'll take that.
		if (isset($this->pibaseObj->conf['salutation']) && in_array($this->pibaseObj->conf['salutation'], $this->allowedSuffixes, 1)) {
			$expandedKey = $key . '_' . $this->pibaseObj->conf['salutation'];
			if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4006000) {
				if ($this->pibaseObj->LOCAL_LANG[$this->pibaseObj->LLkey][$expandedKey][0]['target'] != '') {
					$key = $expandedKey;
				}
			} else {
				if (isset($this->pibaseObj->LOCAL_LANG[$this->pibaseObj->LLkey][$expandedKey])) {
					$key = $expandedKey;
				}
			}
		}
		return $this->pibaseObj->pi_getLL($key, $alt, $hsc);
	}

	/**
	 * Flatten TypoScript label array; converting a hierarchical array into a flat
	 * array with the keys separated by dots.
	 *
	 * Example Input:  array('k1' => array('subkey1' => 'val1'))
	 * Example Output: array('k1.subkey1' => 'val1')
	 *
	 * @param array $labelValues Hierarchical array of labels
	 * @param string $parentKey the name of the parent key in the recursion; is only needed for recursion.
	 * @return array flattened array of labels.
	 */
	protected function flattenTypoScriptLabelArray(array $labelValues, $parentKey = '') {
		$result = array();
		foreach ($labelValues as $key => $labelValue) {
			if (!empty($parentKey)) {
				$key = $parentKey . $key;
			}
			if (is_array($labelValue)) {
				$labelValue = $this->flattenTypoScriptLabelArray($labelValue, $key);
				$result = array_merge($result, $labelValue);
			} else {
				if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4006000) {
					$result[$key][0]['target'] = $labelValue;
				} else {
					$result[$key] = $labelValue;
				}
			}
		}
		return $result;
	}

}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_lang.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_lang.php']);
}
?>