<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2008 Franz Holzinger (franz@ttproducts.de)
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
 * table class for creation of database table classes and table view classes
 *
 * $Id$
 *
 * @author	Franz Holzinger <franz@ttproducts.de>
 * @maintainer	Franz Holzinger <franz@ttproducts.de>
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 */
class tx_srfeuserregister_lib_tables {
	public $tableClassArray = array();
	public $tablename;

	public function init ($tablename) {

		$this->tablename = $tablename;
		if ($tablename == 'fe_users') {
			$this->tableClassArray['address'] = 'tx_srfeuserregister_model_feusers';
		} else {
			$this->tableClassArray['address'] = 'tx_srfeuserregister_model_setfixed';
		}
	}	// init

	public function getTableClassArray () {
		return $this->tableClassArray;
	}

	public function setTableClassArray ($tableClassArray) {
		$this->tableClassArray = $tableClassArray;
	}

	public function getTableClass ($functablename, $bView = FALSE) {
		$rc = '';
		if ($functablename) {
			$rc = $this->tableClassArray[$functablename] . ($bView ? '_view' : '');
		}
		return $rc;
	}

	public function get ($functablename, $bView = FALSE) {
		$classNameArray = array();
		$tableObjArray = array();

		$classNameArray['model'] = $this->getTableClass($functablename, FALSE);
		if ($bView) {
			$classNameArray['view'] = $this->getTableClass($functablename, TRUE);
		}

		if (!$classNameArray['model'] || $bView && !$classNameArray['model']) {
			debug('Error in ' . SR_FEUSER_REGISTER_EXT . '. No class found after calling function tx_srfeuserregister_lib_tables::get with parameters "' . $functablename . '", ' . $bView . '.', 'internal error', __LINE__, __FILE__);
			return 'ERROR';
		}

		foreach ($classNameArray as $k => $className)	{
			if ($className != 'skip')	{
				if (strpos($className, ':') === FALSE)	{
					$path = PATH_BE_srfeuserregister;
				} else {
					list($extKey, $className) = t3lib_div::trimExplode(':', $className, TRUE);

					if (!t3lib_extMgm::isLoaded($extKey)) {
						debug('Error in ' . SR_FEUSER_REGISTER_EXT . '. No extension "' . $extKey . '" has been loaded to use class class.' . $className . '.','internal error');
						continue;
					}
					$path = t3lib_extMgm::extPath($extKey);
				}
				$classRef = 'class.' . $className;
				$classRef = $path . $k . '/' . $classRef . '.php:&' . $className;
				$tableObj[$k] = t3lib_div::getUserObj($classRef);	// fetch and store it as persistent object
			}
		}

		if (isset($tableObj['model']) && is_object($tableObj['model'])) {
			if ($tableObj['model']->needsInit()) {
				$tableObj['model']->init(
					$functablename,
					$this->tablename
				);
			}
		} else {
			debug ('Object for \'' . $functablename . '\' has not been found.', 'internal error in ' . SR_FEUSER_REGISTER_EXT);
		}

		if (
			isset($tableObj['view']) &&
			is_object($tableObj['view']) &&
			isset($tableObj['model']) &&
			is_object($tableObj['model'])
		) {
			// nothing yet
		}

		return ($bView ? $tableObj['view'] : $tableObj['model']);
	}
}