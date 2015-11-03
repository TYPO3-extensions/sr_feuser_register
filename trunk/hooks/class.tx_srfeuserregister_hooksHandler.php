<?php

/*
 *  Copyright notice
 *
 *  (c) 2005-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * Example of hook handler for extension Front End User Registration (sr_feuser_register)
 */
class tx_srfeuserregister_hooksHandler {
	public function registrationProcess_beforeConfirmCreate (&$recordArray, &$controlDataObj) {
		// in the case of this hook, the record array is passed by reference
		// in this example hook, we generate a username based on the first and last names of the user
		$cmdKey = $controlDataObj->getCmdKey();
		$theTable = $controlDataObj->getTable();
		if ($controlDataObj->getFeUserData('preview') && $controlDataObj->conf[$cmdKey . '.']['generateUsername']) {
			$firstName = trim($recordArray['first_name']);
			$lastName = trim($recordArray['last_name']);
			$name = trim($recordArray['name']);
			if ((!$firstName || !$lastName) && $name)	{
				$nameArray = t3lib_div::trimExplode(' ', $name);
				$firstName = ($firstName ? $firstName : $nameArray[0]);
				$lastName = ($lastName ? $lastName : $nameArray[1]);
			}
			$recordArray['username'] = substr(strtolower($firstName), 0, 5) . substr(strtolower($lastName), 0, 5);
			$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($theTable, 'username', $recordArray['username'], 'LIMIT 1');
			$counter = 0;
			while($DBrows) {
				$counter = $counter + 1;
				$DBrows =
					$GLOBALS['TSFE']->sys_page->getRecordsByField(
						$theTable,
						'username',
						$recordArray['username'] . $counter, 'LIMIT 1'
					);
			}
			if ($counter) {
				$recordArray['username'] = $recordArray['username'] . $counter;
			}
		}
	}

	public function registrationProcess_afterSaveEdit (
		$theTable,
		$dataArray,
		$origArray,
		$token,
		&$newRow,
		$cmd,
		$cmdKey,
		$pid,
		$fieldList,
		$pObj // object of type tx_srfeuserregister_data
	) {
	}

	public function registrationProcess_beforeSaveDelete ($recordArray, &$invokingObj) {
	}

	public function registrationProcess_afterSaveCreate (
		$theTable,
		$dataArray,
		$origArray,
		$token,
		&$newRow,
		$cmd,
		$cmdKey,
		$pid,
		$fieldList,
		$pObj // object of type tx_srfeuserregister_data
	) {
	}

	public function confirmRegistrationClass_preProcess (&$recordArray, &$invokingObj) {
		// in the case of this hook, the record array is passed by reference
		// you may not see this echo if the page is redirected to auto-login
	}

	public function confirmRegistrationClass_postProcess ($recordArray, &$invokingObj) {
		// you may not see this echo if the page is redirected to auto-login
	}

	public function addGlobalMarkers (&$markerArray, &$invokingObj) {
	}
}