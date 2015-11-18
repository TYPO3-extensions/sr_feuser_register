<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Security\SessionData;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Secured data handling
 */
class SecuredData
{
	/**
	 * Names of secured fields
	 *
	 * @var array
	 */
	static protected $securedFields = array('password', 'password_again', 'tx_srfeuserregister_password');

	/**
	 * Gets the array of names of secured fields
	 *
	 * @return array names of secured fields
	 */
	static public function getSecuredFields()
	{
		return self::$securedFields;
	}

	/**
	 * Reduces the list of fields to the fields that are allowed to be shown
	 *
	 * @param array $fields: initial list of field names
	 * @return array new list of field names
	 */
	static public function getOpenFields($fields)
	{
		$securedFieldArray = self::getSecuredFields();
		$fieldArray = array_unique(GeneralUtility::trimExplode(',', $fields));
		foreach ($securedFieldArray as $securedField) {
			$k = array_search($securedField, $fieldArray);
			if ($k !== false) {
				unset($fieldArray[$k]);
			}
		}
		return implode(',', $fieldArray);
	}

	/**
	 * Changes potential malicious script code of the input to harmless HTML
	 *
	 * @param array $dataArray: array of key/value pairs
	 * @param bool $htmlSpecial: whether to apply htmlspecialchars to the values
	 * @return void
	 */
	public function secureInput(array &$dataArray, $htmlSpecial = true)
	{
		foreach ($dataArray as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					if (is_array($value2)) {
						foreach ($value2 as $key3 => $value3) {
							$dataArray[$key][$key2][$key3] = self::getSecuredValue($key3, $value3, $htmlSpecial);
						}
					} else {
						$dataArray[$key][$key2] = self::getSecuredValue($key2, $value2, $htmlSpecial);
					}
				}
			} else {
				$dataArray[$key] = self::getSecuredValue($key, $value, $htmlSpecial);
			}
		}
	}

	/**
	 * Changes potential malicious script code of the input to harmless HTML
	 *
	 * @param string $field: name of field to secure
	 * @param string $value: input value
	 * @param bool $htmlSpecial: whether to apply htmlspecialchars to the value
	 * @return string secured value
	 */
	static public function getSecuredValue($field, $value, $htmlSpecial = true)
	{
		$securedValue = $value;
		if (!in_array($field, self::$securedFields)) {
			$securedValue = htmlspecialchars_decode($value);
			if ($htmlSpecial) {
				$securedValue = htmlspecialchars($securedValue);
			}
		}
		return $securedValue;
	}
}