<?php
namespace SJBR\SrFeuserRegister\Setfixed;

/*
 *  Copyright notice
 *
 *  (c) 2007-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Utility\UrlUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Compute setfixed urls
 */
class SetfixedUrls
{
	/**
	 * Computes the setfixed url's
	 *
	 * @param string $cmd: the current command
	 * @param array $record: the record row
	 * @return array array of key => url pairs
	 */
	public static function compute(
		$prefixId,
		$theTable,
		array $conf,
		array $pids,
		$cmd,
		array $record
	) {
		$setfixedUrls = array();
		if (is_array($conf['setfixed.'])) {
			foreach ($conf['setfixed.'] as $theKey => $data) {
				if (strstr($theKey, '.')) {
					$theKey = substr($theKey, 0, -1);
				}
				$setfixedpiVars = array();
	
				if ($theTable !== 'fe_users' && $theKey === 'EDIT') {
					$noFeusersEdit = true;
				} else {
					$noFeusersEdit = false;
				}
	
				$setfixedpiVars[$prefixId . '%5BrU%5D'] = $record['uid'];
				$fieldList = $data['_FIELDLIST'];
				$fieldListArray = GeneralUtility::trimExplode(',', $fieldList);
				foreach ($fieldListArray as $fieldname) {
						if (isset($data[$fieldname])) {
								$fieldValue = $data[$fieldname];
								$record[$fieldname] = $fieldValue;
						}
				}

				if ($noFeusersEdit) {
					$theCmd = $pidCmd = 'edit';
					if ($conf['edit.']['setfixed']) {
						$addSetfixedHash = true;
					} else {
						$addSetfixedHash = false;
						$setfixedpiVars[$prefixId . '%5BaC%5D'] = Authentication::authCode($record, $conf, $fieldList);
					}
				} else {
					$theCmd = 'setfixed';
					$pidCmd = ($cmd === 'invite' ? 'confirmInvitation' : 'confirm');
					$setfixedpiVars[$prefixId . '%5BsFK%5D'] = $theKey;
					$addSetfixedHash = true;
					if (isset($record['auto_login_key'])) {
						$setfixedpiVars[$prefixId . '%5Bkey%5D'] = $record['auto_login_key'];
					}
				}
	
				if ($addSetfixedHash) {
					$setfixedpiVars[$prefixId . '%5BaC%5D'] = Authentication::setfixedHash($record, $conf, $fieldList);
				}
				$setfixedpiVars[$prefixId . '%5Bcmd%5D'] = $theCmd;
	
				if (is_array($data) ) {
					foreach ($data as $fieldname => $fieldValue) {
						if (strpos($fieldname, '.') !== false) {
							continue;
						}
						$setfixedpiVars['fD%5B' . $fieldname . '%5D'] = rawurlencode($fieldValue);
					}
				}
	
				$linkPID = $pids[$pidCmd];
	
				if (GeneralUtility::_GP('L') && !GeneralUtility::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = GeneralUtility::_GP('L');
				}
	
				if ($conf['useShortUrls']) {
					$thisHash = HashUtility::getHashFromParameters($setfixedpiVars);
					$setfixedpiVars = array($prefixId . '%5BregHash%5D' => $thisHash);
				}
				$urlConf = array();
				$urlConf['disableGroupAccessCheck'] = true;
				$confirmType = MathUtility::canBeInterpretedAsInteger($conf['confirmType']) ? (int) $conf['confirmType'] : $GLOBALS['TSFE']->type;
				$url = UrlUtility::getTypoLink_URL($linkPID . ',' . $confirmType, $setfixedpiVars, '', $urlConf);
				$setfixedUrls[$theKey] = $url;
			}
		}
		return $setfixedUrls;
	}
}