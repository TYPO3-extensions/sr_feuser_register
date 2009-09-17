<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2008 Franz Holzinger <franz@ttproducts.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
 * class the usergroup field
 *
 * $Id$
 *
 * @author	Franz Holzinger <franz@ttproducts.de>
 * @maintainer	Franz Holzinger <franz@ttproducts.de>
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 */

require_once(PATH_BE_srfeuserregister.'model/field/class.tx_srfeuserregister_model_field_base.php');



class tx_srfeuserregister_model_field_usergroup  extends tx_srfeuserregister_model_field_base {

	function modifyConf (&$conf, $cmdKey)	{

		if ($conf[$cmdKey.'.']['allowUserGroupSelection']) {
			$conf[$cmdKey.'.']['fields'] = implode(',', array_unique(t3lib_div::trimExplode(',', $conf[$cmdKey.'.']['fields'] . ',usergroup', 1)));
			$conf[$cmdKey.'.']['required'] = implode(',', array_unique(t3lib_div::trimExplode(',', $conf[$cmdKey.'.']['required'] . ',usergroup', 1)));
		} else {
			$conf[$cmdKey.'.']['fields'] = implode(',', array_diff(t3lib_div::trimExplode(',', $conf[$cmdKey.'.']['fields'], 1), array('usergroup')));
		}
	}

	function getReservedValues ()	{
		$confObj = &t3lib_div::getUserObj('&tx_srfeuserregister_lib_conf');
		$conf = &$confObj->getConf();
		$rc = array_merge(t3lib_div::trimExplode(',', $conf['create.']['overrideValues.']['usergroup'],1), t3lib_div::trimExplode(',', $conf['setfixed.']['APPROVE.']['usergroup'],1), t3lib_div::trimExplode(',', $conf['setfixed.']['ACCEPT.']['usergroup'],1));
		return $rc;
	}

	function parseOutgoingData ($fieldname, $dataArray, &$origArray, &$parsedArr) {

		$valuesArray = array();
		if (isset($origArray) && is_array($origArray) && isset($origArray[$fieldname]) && is_array($origArray[$fieldname]))	{
			$valuesArray = $origArray[$fieldname];
			$reservedValues = $this->getReservedValues();
			$valuesArray = array_intersect($valuesArray, $reservedValues);
		}
		if (isset($dataArray) && is_array($dataArray) && isset($dataArray[$fieldname]) && is_array($dataArray[$fieldname]))	{
			$dataArray[$fieldname] = array_unique(array_merge ($dataArray[$fieldname], $valuesArray));
			$parsedArr[$fieldname] = $dataArray[$fieldname];
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/field/class.tx_srfeuserregister_model_field_usergroup.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/field/class.tx_srfeuserregister_model_field_usergroup.php']);
}

?>
