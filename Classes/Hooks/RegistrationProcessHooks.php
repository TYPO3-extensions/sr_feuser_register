<?php
namespace SJBR\SrFeuserRegister\Hooks;

/*
 *  Copyright notice
 *
 *  (c) 2005-2018 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Domain\Data;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Utility\DataUtility;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Example of hooks for extension Front End User Registration (sr_feuser_register)
 */
class RegistrationProcessHooks
{
	/**
	 * @param string $cmdKey: the cmd being processed
	 * @param array $conf: the plugin configuration
	 */
	public function registrationProcess_beforeConfirmCreate(
		$theTable,
		array &$recordArray,
		Parameters $parameters,
		$cmdKey,
		array $conf
	) {
		// in the case of this hook, the record array is passed by reference
		// in this example hook, we generate a username based on the first and last names of the user
		if ($parameters->getFeUserData('preview') && $conf[$cmdKey . '.']['generateUsername']) {
			$firstName = trim($recordArray['first_name']);
			$lastName = trim($recordArray['last_name']);
			$name = trim($recordArray['name']);
			if ((!$firstName || !$lastName) && $name) {
				$nameArray = GeneralUtility::trimExplode(' ', $name);
				$firstName = ($firstName ? $firstName : $nameArray[0]);
				$lastName = ($lastName ? $lastName : $nameArray[1]);
			}
			$recordArray['username'] = substr(strtolower($firstName), 0, 5) . substr(strtolower($lastName), 0, 5);
			$DBrows = DataUtility::getRecordsByField($theTable, 'username', $recordArray['username'], '', '', '', '1');
			$counter = 0;
			while ($DBrows) {
				$counter = $counter + 1;
				$DBrows = DataUtility::getRecordsByField($theTable, 'username', $recordArray['username'] . $counter, '', '', '', '1');
			}
			if ($counter) {
				$recordArray['username'] = $recordArray['username'] . $counter;
			}
		}
	}

	public function registrationProcess_afterSaveEdit(
		$theTable,
		array $dataArray,
		array $origArray,
		$token,
		array &$newRow,
		$cmd,
		$cmdKey,
		$pid,
		$fieldList,
		Data $pObj
	) {
	}

	public function registrationProcess_beforeSaveDelete($recordArray, $invokingObj) {
	}

	public function registrationProcess_afterSaveCreate(
		$theTable,
		array $dataArray,
		array $origArray,
		$token,
		array &$newRow,
		$cmd,
		$cmdKey,
		$pid,
		$fieldList,
		Data $pObj
	) {
	}

	public function confirmRegistrationClass_preProcess(array &$recordArray, $invokingObj) {
		// in the case of this hook, the record array is passed by reference
		// you may not see this echo if the page is redirected to auto-login
	}

	public function confirmRegistrationClass_postProcess(array $recordArray, $invokingObj) {
		// you may not see this echo if the page is redirected to auto-login
	}

	/**
	 * Add some markers to the current marker array
	 *
	 * @param array $markerArray: reference to the marker array
	 * @param Marker invoking marker object
	 * @param string $cmdKey: the cmd being processed
	 * @param array $conf: the plugin configuration
	 */
	public function addGlobalMarkers(array &$markerArray, Marker $markerObj, $cmdKey, array $conf) {}
}