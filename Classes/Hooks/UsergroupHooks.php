<?php
namespace SJBR\SrFeuserRegister\Hooks;

/*
 *  Copyright notice
 *
 *  (c) 2008-2011 Franz Holzinger <franz@ttproducts.de>
 *  (c) 2012-2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Hooks for the usergroup field
 */
class UsergroupHooks
{
	/**
	 * Modify the form fields configuration depending on the $cmdKey
	 *
	 * @param array $conf: the configuration array
	 * @param string $cmdKey: the command key
	 * @return void
	 */
	public function modifyConf(array &$conf, $cmdKey)
	{
		// Add usergroup to the list of fields and required fields if the user is allowed to select user groups
		// Except when only updating password
		if ($cmdKey !== 'password') {
			if ($conf[$cmdKey . '.']['allowUserGroupSelection']) {
				$conf[$cmdKey . '.']['fields'] = implode(',', array_unique(GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['fields'] . ',usergroup', true)));
				$conf[$cmdKey . '.']['required'] = implode(',', array_unique(GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['required'] . ',usergroup', true)));
			} else {
				// Remove usergroup from the list of fields and required fields if the user is not allowed to select user groups
				$conf[$cmdKey . '.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['fields'], true), array('usergroup')));
				$conf[$cmdKey . '.']['required'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['required'], true), array('usergroup')));
			}
		}
	}

	/**
	 * Get the array of user groups reserved for control of the registration process
	 *
	 * @param array $conf: the plugin configuration
	 * @return array the reserved user groups
	 */
	public function getReservedValues(array $conf)
	{
		$reservedValues = array_merge(
			GeneralUtility::trimExplode(',', $conf['create.']['overrideValues.']['usergroup'], true),
			GeneralUtility::trimExplode(',', $conf['invite.']['overrideValues.']['usergroup'], true),
			GeneralUtility::trimExplode(',', $conf['setfixed.']['APPROVE.']['usergroup'], true),
			GeneralUtility::trimExplode(',', $conf['setfixed.']['ACCEPT.']['usergroup'], true)
		);
		return array_unique($reservedValues);
	}

	/**
	 * Remove reserved user groups from the usergroup field of an array
	 *
	 * @param array $row: array
	 * @return void
	 */
	public function removeReservedValues(array &$row, array $conf)
	{
		if (isset($row['usergroup'])) {
			$reservedValues = $this->getReservedValues($conf);
			if (is_array($row['usergroup'])) {
				$userGroupArray = $row['usergroup'];
				$bUseArray = true;
			} else {
				$userGroupArray = explode(',', $row['usergroup']);
				$bUseArray = false;
			}
			$userGroupArray = array_diff($userGroupArray, $reservedValues);
			if ($bUseArray) {
				$row['usergroup'] = $userGroupArray;
			} else {
				$row['usergroup'] = implode(',', $userGroupArray);
			}
		}
	}

	public function removeInvalidValues(array $conf, $cmdKey, array &$row)
	{
		if (isset($row['usergroup']) && $conf[$cmdKey . '.']['allowUserGroupSelection']) {
			// Todo
		} else {
			// The setting of the usergroups is allowed
			$row['usergroup'] = '';
		}
	}

	/**
	 * Processes data before entering the database
	 *
	 * @return void
	 */
	public function parseOutgoingData($theTable, $fieldname, $foreignTable, $cmdKey, $pid, array $conf, array $dataArray, array $origArray, array &$parsedArray)
	{
		$valuesArray = array();
		if (isset($origArray[$fieldname]) && is_array($origArray[$fieldname])) {
			$valuesArray = $origArray[$fieldname];
			if ($conf[$cmdKey . '.']['keepUnselectableUserGroups']) {
				if (class_exists('TYPO3\\CMS\\Core\\Database\\ConnectionPool')) {
					$queryBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
						->getQueryBuilderForTable($foreignTable)
						->select('uid')
						->from($foreignTable);
					$this->getAllowedWhereClause($foreignTable, $pid, $conf, $cmdKey, true, $queryBuilder);
					$query = $queryBuilder
						->execute();
					$rowArray = [];
					while ($row = $query->fetch()) {
						$rowArray['uid'] = $row;
					}
				} else {
					// TYPO3 CMS 7 LTS
					$whereClause = $this->getAllowedWhereClause($foreignTable, $pid, $conf, $cmdKey);
					$rowArray = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', $foreignTable, $whereClause, '', '', '', 'uid');
				}
				if (!empty($rowArray) && is_array($rowArray)) {
					$keepValues = array_keys($rowArray);
				}
			} else {
				$keepValues = $this->getReservedValues($conf);
			}
			$valuesArray = array_intersect($valuesArray, $keepValues);
		}
		if (isset($dataArray[$fieldname]) && is_array($dataArray[$fieldname])) {
			$dataArray[$fieldname] = array_unique(array_merge($dataArray[$fieldname], $valuesArray));
			$parsedArray[$fieldname] = $dataArray[$fieldname];
		}
	}

	/**
	 * Contruct a where clause to select the user groups that are allowed to be kept
	 *
	 * @return string the where clause
	 */
	public function getAllowedWhereClause($theTable, $pid, array $conf, $cmdKey, $bAllow = true, $queryBuilder = null)
	{
		if ($queryBuilder === null) {
			// TYPO3 CMS 7 LTS
			return $this->getCompatibleAllowedWhereClause($theTable, $pid, $conf, $cmdKey, $bAllow);
		}

		$pidArray = GeneralUtility::trimExplode(',', $conf['userGroupsPidList'], true);
		if (empty($pidArray)) {
			$pidArray = GeneralUtility::trimExplode(',', $pid, true);
		}
		if (!empty($pidArray)) {
			$pidArray = array_map('intval', $pidArray);
			if (empty($queryBuilder->getQueryPart('where'))) {
				$queryBuilder->where($queryBuilder->expr()->in('pid', $pidArray));
			} else {
				$queryBuilder->andWhere($queryBuilder->expr()->in('pid', $pidArray));
			}
		}

		$allowedUserGroupArray = array();
		$allowedSubgroupArray = array();
		$deniedUserGroupArray = array();
		$this->getAllowedValues($conf, $cmdKey, $allowedUserGroupArray, $allowedSubgroupArray, $deniedUserGroupArray);
		if ($allowedUserGroupArray['0'] !== 'ALL') {
			if ($bAllow) {
				$allowedUserGroupExpression = $queryBuilder->expr()->in('uid', $allowedUserGroupArray);
			} else {
				$allowedUserGroupExpression = $queryBuilder->expr()->notIn('uid', $allowedUserGroupArray);				
			}
		}
		if (count($allowedSubgroupArray)) {
			if ($bAllow) {
				$allowedSubgroupExpression = $queryBuilder->expr()->in('subgroup', $allowedSubgroupArray);
			} else {
				$allowedSubgroupExpression = $queryBuilder->expr()->notIn('subgroup', $allowedSubgroupArray);				
			}
		}
		if ($allowedUserGroupExpression && $allowedSubgroupExpression) {
			if ($bAllow) {
				$allowedExpression = $queryBuilder->expr()->orX($allowedUserGroupExpression, $allowedSubgroupExpression);
				
			} else {
				$allowedExpression = $queryBuilder->expr()->andX($allowedUserGroupExpression, $allowedSubgroupExpression);
			}
		} else {
			if ($allowedUserGroupExpression) {
				$allowedExpression = $allowedUserGroupExpression;
			}
			if ($allowedSubgroupExpression) {
				$allowedExpression = $allowedSubgroupExpression;
			}		
		}		
		if (count($deniedUserGroupArray)) {
			if ($bAllow) {
				$deniedExpression = $queryBuilder->expr()->notIn('uid', $deniedUserGroupArray);
			} else {
				$deniedExpression = $queryBuilder->expr()->in('uid', $deniedUserGroupArray);				
			}
		}
		if ($allowedExpression && $deniedExpression) {
			if ($bAllow) {
				$expression = $queryBuilder->expr()->andX($allowedExpression, $deniedExpression);
			} else {
				$expression = $queryBuilder->expr()->orX($allowedExpression, $deniedExpression);
			}
		} else {
			if ($allowedExpression) {
				$expression = $allowedExpression;
			}
			if ($deniedExpression) {
				$expression = $deniedExpression;
			}			
		}
		if ($expression) {
			if (empty($queryBuilder->getQueryPart('where'))) {
				$queryBuilder->where($expression);
			} else {
				$queryBuilder->andWhere($expression);
			}			
		}
		return '';
	}

	/**
	 * TYPO3 CMS 7 LTS
	 *
	 * Contruct a where clause to select the user groups that are allowed to be kept
	 *
	 * @return string the where clause
	 */
	protected function getCompatibleAllowedWhereClause($theTable, $pid, array $conf, $cmdKey, $bAllow = true)
	{
		$whereClause = '';
		$subgroupWhereClauseArray = array();
		$pidArray = array();
		$tmpArray = GeneralUtility::trimExplode(',', $conf['userGroupsPidList'], true);
		if (count($tmpArray)) {
			foreach ($tmpArray as $value) {
				$valueIsInt = MathUtility::canBeInterpretedAsInteger($value);
				if ($valueIsInt) {
					$pidArray[] = (int) $value;
				}
			}
		}
		if (count($pidArray) > 0) {
			$whereClause = ' pid IN (\'' . implode('\',\'', $pidArray) . '\') ';
		} else {
			$whereClause = ' pid IN (\'' . implode('\',\'', GeneralUtility::trimExplode(',', $pid, true)) . '\') ';
		}

		$whereClausePart2 = '';
		$whereClausePart2Array = array();

		$allowedUserGroupArray = array();
		$allowedSubgroupArray = array();
		$deniedUserGroupArray = array();
		$this->getAllowedValues($conf, $cmdKey, $allowedUserGroupArray, $allowedSubgroupArray, $deniedUserGroupArray);
		if ($allowedUserGroupArray['0'] !== 'ALL') {
			$uidArray = $GLOBALS['TYPO3_DB']->fullQuoteArray($allowedUserGroupArray, $theTable);
			$subgroupWhereClauseArray[] = 'uid ' . ($bAllow ? 'IN' : 'NOT IN') . ' (' . implode(',', $uidArray) . ')';
		}
		if (count($allowedSubgroupArray)) {
			$subgroupArray = $GLOBALS['TYPO3_DB']->fullQuoteArray($allowedSubgroupArray, $theTable);
			$subgroupWhereClauseArray[] = 'subgroup ' . ($bAllow ? 'IN' : 'NOT IN') . ' (' . implode(',', $subgroupArray) . ')';
		}
		if (count($subgroupWhereClauseArray)) {
			$subgroupWhereClause .= implode(' ' . ($bAllow ? 'OR' : 'AND') . ' ', $subgroupWhereClauseArray);
			$whereClausePart2Array[] = '( ' . $subgroupWhereClause . ' )';
		}
		if (count($deniedUserGroupArray)) {
			$uidArray = $GLOBALS['TYPO3_DB']->fullQuoteArray($deniedUserGroupArray, $theTable);
			$whereClausePart2Array[] = 'uid ' . ($bAllow ? 'NOT IN' : 'IN') . ' (' . implode(',', $uidArray) . ')';
		}
		if (count($whereClausePart2Array)) {
			$whereClausePart2 = implode(' ' . ($bAllow ? 'AND' : 'OR') . ' ', $whereClausePart2Array);
			$whereClause .= ' AND (' . $whereClausePart2 . ')';
		}
		return $whereClause;
	}

	/**
	 * Get the allowed values for user groups
	 *
	 * @param array $conf: the configuration array
	 * @param string $cmdKey: the command key
	 * @return void
	 */
	public function getAllowedValues(array $conf, $cmdKey, array &$allowedUserGroupArray, array &$allowedSubgroupArray, array &$deniedUserGroupArray)
	{
		$allowedUserGroupArray = GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['allowedUserGroups'], true);
		if ($allowedUserGroupArray['0'] !== 'ALL') {
			$allowedUserGroupArray = array_map('intval', $allowedUserGroupArray);
		}
		$allowedSubgroupArray = array_map('intval', GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['allowedSubgroups'], true));
		$deniedUserGroupArray = array_map('intval', GeneralUtility::trimExplode(',', $conf[$cmdKey . '.']['deniedUserGroups'], true));
	}

	/**
	 * Restrict the input values array to allowed values
	 *
	 * @param array $values: input values array
	 * @param array $conf: the configuration array
	 * @param string $cmdKey: the command key
	 * @return void
	 */
	public function restrictToSelectableValues(array $values, array $conf, $cmdKey)
	{
		$restrictedValues = $values;
		$reservedValues = $this->getReservedValues($conf);
		$allowedUserGroupArray = array();
		$allowedSubgroupArray = array();
		$deniedUserGroupArray = array();
		$this->getAllowedValues($conf, $cmdKey, $allowedUserGroupArray, $allowedSubgroupArray, $deniedUserGroupArray);
		if (!empty($allowedUserGroupArray) && $allowedUserGroupArray['0'] !== 'ALL') {
			$restrictedValues = array_intersect($restrictedValues, $allowedUserGroupArray);
		}
		if (!empty($allowedSubgroupArray)) {
			$restrictedValues = array_intersect($restrictedValues, $allowedSubgroupArray);	
		}
		$restrictedValues = array_diff($restrictedValues, $deniedUserGroupArray);
		$restrictedValues = array_diff($restrictedValues, $reservedValues);
		return $restrictedValues;
	}
}