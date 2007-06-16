<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
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
 * Part of the sr_feuser_register (Frontend User Registration) extension.
 *
 * setfixed functions
 *
 * $Id$
 *
 * @author Kasper Skaarhoj <kasperXXXX@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author Franz Holzinger <kontakt@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_setfixed {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $control;
	var $controlData;
	var $auth;
	var $previewLabel;
	var $setfixedEnabled;

	var $markerArray = array();
	var $cObj;
	var $buttonLabelsList;
	var $otherLabelsList;


	function init(&$cObj, &$conf, &$config, &$controlData, &$auth)	{
		global $TSFE;

		$this->cObj = &$cObj;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->controlData = &$controlData;
		$this->auth = &$auth;
	}


	/**
	* Computes the setfixed url's
	*
	* @param array  $markerArray: the input marker array
	* @param array  $setfixed: the TS setup setfixed configuration
	* @param array  $r: the record
	* @return void
	*/
	function setfixed(&$markerArray, $setfixed, $r, $theTable) {
		global $TSFE;

		$prefixId = $this->controlData->getPrefixId();
		if ($this->controlData->getSetfixedEnabled() && is_array($setfixed) ) {
			$setfixedpiVars = array();
			reset($setfixed);
			while (list($theKey, $data) = each($setfixed)) {
				if (strstr($theKey, '.') ) {
					$theKey = substr($theKey, 0, -1);
				}
				unset($setfixedpiVars);
				$recCopy = $r;
				$setfixedpiVars[$prefixId .'[rU]'] = $r['uid'];

				if ( $theTable != 'fe_users' && $theKey == 'EDIT' ) {
					if (is_array($data) ) {
						reset($data);
						while (list($fieldName, $fieldValue) = each($data)) {
							$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
							$recCopy[$fieldName] = $fieldValue;
						}
					}
					if( $this->conf['edit.']['setfixed'] ) {
						$setfixedpiVars[$prefixId.'[aC]'] = $this->auth->setfixedHash($recCopy, $data['_FIELDLIST']);
					} else {
						$setfixedpiVars[$prefixId.'[aC]'] = $this->auth->authCode($r);
					}
					$linkPID = $this->controlData->getPID('edit');
				} else {
					$setfixedpiVars[$prefixId.'[cmd]'] = 'setfixed';
					$setfixedpiVars[$prefixId.'[sFK]'] = $theKey;
					if (is_array($data) ) {
						reset($data);
						while (list($fieldName, $fieldValue) = each($data)) {
							$setfixedpiVars['fD['.$fieldName.']'] = rawurlencode($fieldValue);
							$recCopy[$fieldName] = $fieldValue;
						}
					}
					$setfixedpiVars[$prefixId.'[aC]'] = $this->auth->setfixedHash($recCopy, $data['_FIELDLIST']);
					$linkPID = $this->controlData->getPID('confirm');
					if ($this->controlData->getCmd() == 'invite') {
						$linkPID = $this->controlData->getPID('confirmInvitation');

					}
				}
				if (t3lib_div::_GP('L') && !t3lib_div::inList($GLOBALS['TSFE']->config['config']['linkVars'], 'L')) {
					$setfixedpiVars['L'] = t3lib_div::_GP('L');
				}

				if ($this->conf['useShortUrls']) {
					$thisHash = $this->storeFixedPiVars($setfixedpiVars);
					$setfixedpiVars = array($prefixId.'[regHash]' => $thisHash);
				}
				$conf = array();
				$conf['disableGroupAccessCheck'] = TRUE;
				$confirmType = (t3lib_div::testInt($this->conf['confirmType']) ? intval($this->conf['confirmType']) : $TSFE->type);

				$url = $this->cObj->getTypoLink_URL($linkPID.','.$this->confirmType, $setfixedpiVars, '', $conf);
				$markerArray['###SETFIXED_'.$this->cObj->caseshift($theKey,'upper').'_URL###'] = ($TSFE->absRefPrefix ? '' : $this->controlData->getSiteUrl()) . $url;
			}
		}
	}	// setfixed


	/**
		*  Store the setfixed vars and return a replacement hash
		*/
	function storeFixedPiVars($vars) {
		global $TYPO3_DB;
		
			// create a unique hash value
		$regHash_array = t3lib_div::cHashParams(t3lib_div::implodeArrayForUrl('',$vars));
		$regHash_calc = t3lib_div::shortMD5(serialize($regHash_array),20);
			// and store it with a serialized version of the array in the DB
		$res = $TYPO3_DB->exec_SELECTquery('md5hash','cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash_calc,'cache_md5params'));
		if (!$TYPO3_DB->sql_num_rows($res))  {
			$insertFields = array (
				'md5hash' => $regHash_calc,
				'tstamp' => time(),
				'type' => 99,
				'params' => serialize($vars)
			);
			$TYPO3_DB->exec_INSERTquery('cache_md5params',$insertFields);
		}
		return $regHash_calc;
	}	// storeFixedPiVars


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/control/class.tx_srfeuserregister_setfixed.php']);
}
?>
