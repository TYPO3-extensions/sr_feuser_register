<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005, 2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * Example of hook handler for extension Front End User Registration (sr_feuser_register)
 *
 * $Id$
 *
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 */
	 // $invokingObj is a reference to the invoking object

class tx_srfeuserregister_hooksHandler {
	function registrationProcess_beforeConfirmCreate(&$recordArray, &$dataObj) {
			// in the case of this hook, the record array is passed by reference
			// in this example hook, we generate a username based on the first and last names of the user
		$cmdKey = $dataObj->controlData->getCmdKey();
		$theTable = $dataObj->controlData->getTable();
		if ($dataObj->getFeUserData('preview') && $dataObj->conf[$cmdKey.'.']['generateUsername']) {
			$firstName = trim($recordArray['first_name']);
			$lastName = trim($recordArray['last_name']);
			$name = trim($recordArray['name']);
			if ((!$firstName || !$lastName) && $name)	{
				$nameArray = t3lib_div::trimExplode(' ', $name);
				$firstName = ($firstName ? $firstName : $nameArray[0]);
				$lastName = ($lastName ? $lastName : $nameArray[1]);
			}
			$recordArray['username'] = substr(strtolower($firstName),0,5) . substr(strtolower($lastName),0,5);
			$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($theTable, 'username', $recordArray['username'], 'LIMIT 1');
			$counter = 0;
			while($DBrows) {
				$counter = $counter + 1;
				$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($theTable, 'username', $recordArray['username'].$counter, 'LIMIT 1');
			}
			if ($counter)	{
				$recordArray['username'] = $recordArray['username'].$counter;
			}
		}
	}

	function registrationProcess_afterSaveEdit($recordArray, &$invokingObj) {
		echo 'afterSaveEdit';
	}

	function registrationProcess_beforeSaveDelete($recordArray, &$invokingObj) {
		echo 'beforeSaveDelete';
	}

	function registrationProcess_afterSaveCreate($recordArray, &$invokingObj) {
		echo 'afterSaveCreate';
	}

	function confirmRegistrationClass_preProcess(&$recordArray, &$invokingObj) {
			// in the case of this hook, the record array is passed by reference
			// you may not see this echo if the page is redirected to auto-login
		echo 'confirmRegistrationClass_preProcess';
	}

	function confirmRegistrationClass_postProcess($recordArray, &$invokingObj) {

			// you may not see this echo if the page is redirected to auto-login
		echo 'confirmRegistrationClass_postProcess';
	}
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php"]) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/hooks/class.tx_srfeuserregister_hooksHandler.php"]);
}

?>
