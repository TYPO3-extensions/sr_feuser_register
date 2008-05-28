<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2008 Franz Holzinger <contact@fholzinger.com>
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
 * control data store functions
 *
 * $Id$
 *
 * @author Franz Holzinger <contact@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

define ('MODE_NORMAL', '0');
define ('MODE_PREVIEW', '1');


class tx_srfeuserregister_controldata {
	var $thePid = 0;
	var $thePidTitle;
	var $theTable;
	var $site_url;
	var $prefixId;
	var $piVars;
	var $extKey;
	var $cmd='';
	var $cmdKey;
	var $pid = array();
	var $useMd5Password = FALSE;
	var $setfixedEnabled = 0;
	var $bSubmit = FALSE;
	var $failure = FALSE; // is set if data did not have the required fields set.

	var $feUserData = array();

	function init (&$conf, $prefixId, $extKey, $piVars, $theTable)	{
		global $TSFE;

		$this->conf = &$conf;
		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');

		$this->prefixId = $prefixId;
		$this->extKey = $extKey;
		$this->piVars = $piVars;
		$this->setTable($theTable);

			// set the title language overlay
		$pidRecord = t3lib_div::makeInstance('t3lib_pageSelect');
		$pidRecord->init(0);
		$pidRecord->sys_language_uid = $this->sys_language_content;
		$row = $pidRecord->getPage($this->getPid());

		$this->thePidTitle = trim($this->conf['pidTitleOverride']) ? trim($this->conf['pidTitleOverride']) : $row['title'];

		$pidTypeArray = array('login', 'register', 'edit', 'infomail', 'confirm', 'confirmInvitation');
		// set the pid's

		foreach ($pidTypeArray as $k => $type)	{
			$this->setPid ($type, $this->conf[$type.'PID']);
		}

			// Initialise password encryption
		if ($theTable == 'fe_users' && $this->conf['useMd5Password']) {
			$this->setUseMd5Password(TRUE);
			$this->conf['enableAutoLoginOnConfirmation'] = FALSE;
			$this->conf['enableAutoLoginOnCreate'] = FALSE;
		}

		if ($this->conf['enableEmailConfirmation'] || $this->conf['enableAdminReview'] || $this->conf['setfixed']) {
			$this->setSetfixedEnabled(1);
		}

		$feUserData = t3lib_div::_GP($this->getPrefixId());

		// <Steve Webster added short url feature>
			// Get hash variable if provided and if short url feature is enabled
		if ($this->conf['useShortUrls']) {
			$this->cleanShortUrlCache();
				// Check and process for short URL if the regHash GET parameter exists
			if ($feUserData['regHash']) {
				$getVars = $this->getStoredURL($feUserData['regHash']);
				foreach ($getVars as $k => $v ) {
					// restore former GET values
					t3lib_div::_GETset($v,$k);
				}
				$restoredFeUserData = t3lib_div::_GP($this->getPrefixId());
				if ($restoredFeUserData['rU'] === $feUserData['rU'])	{
					$feUserData = array_merge ($feUserData, $restoredFeUserData);
				} else {
					$feUserData = $restoredFeUserData;
				}
			}
		}
		// </Steve Webster added short url feature>

		if (isset($feUserData) && is_array($feUserData))	{
			$this->setFeUserData ($feUserData);
		}

			// Get parameters
			// Establishing compatibility with Direct Mail extension
		$piVarArray = array('rU', 'aC', 'cmd', 'sFK');
		foreach ($piVarArray as $pivar)	{
			$value = htmlspecialchars(t3lib_div::_GP($pivar));
			if ($value != '')	{
				$this->setFeUserData($value, $pivar);
			}
		}
		if (isset($feUserData) && is_array($feUserData) && isset($feUserData['cmd']))	{
			$cmd = htmlspecialchars($feUserData['cmd']);
			$this->setCmd($cmd);
		}
		$feUserdata = $this->getFeUserData();
		$this->secureInput($feUserData);
		$this->setFeUserData ($feUserData);
	}

	/**
	* Changes potential malicious script code of the input to harmless HTML
	*
	* @return void
	*/
	function secureInput(&$dataArray)	{
		if (isset($dataArray) && is_array($dataArray))	{
			foreach ($dataArray as $k => $value)	{
				if (is_array($value))	{
					foreach ($value as $k2 => $value2)	{
						if (is_array($value2))	{
							foreach ($value2 as $k3 => $value3)	{
								$dataArray[$k][$k2][$k3] = htmlspecialchars($value3);
							}
						} else {
							$dataArray[$k][$k2] = htmlspecialchars($value2);
						}
					}
				} else {
					$dataArray[$k] = htmlspecialchars($value);
				}
			}
		}
	}

	function getPidTitle ()	{
		return $this->thePidTitle;
	}

	function getSiteUrl ()	{
		return $this->site_url;
	}

	function getPrefixId ()	{
		return $this->prefixId;
	}

	function getExtKey ()	{
		return $this->extKey;
	}

	function getPiVars ()	{
		return $this->piVars;
	}

	function getCmd() {
		return $this->cmd;
	}

	function setCmd($cmd) {
		$this->cmd = $cmd;
	}

	function getCmdKey() {
		return $this->cmdKey;
	}

	function setCmdKey($cmdKey)	{
		$this->cmdKey = $cmdKey;
	}

	function getFeUserData ($k='')	{
		if ($k)	{
			$rc = $this->feUserData[$k];
		} else {
			$rc = $this->feUserData;
		}
		return $rc;
	}

	function setFeUserData ($dataArray, $k='')	{
		if ($k != '')	{
			$this->feUserData[$k] = $dataArray;
		} else {
			$this->feUserData = $dataArray;
		}
	}

	function getFailure()	{
		return $this->failure;
	}

	function setFailure($failure)	{
		$this->failure = $failure;
	}

	function setSubmit($bSubmit)	{
		$this->bSubmit = $bSubmit;
	}

	function getSubmit()	{
		return $this->bSubmit;
	}

	function getPid($type='')	{
		global $TSFE;

		if ($type)	{
			if (isset($this->pid[$type]))	{
				$rc = $this->pid[$type];
			}
		} else {
			$rc = (t3lib_div::testInt($this->conf['pid']) ? intval($this->conf['pid']) : $TSFE->id);
		}

		return $rc;
	}

	function setPid($type, $pid)	{
		global $TSFE;

		if (!intval($pid))	{
			switch ($type)	{
				case 'infomail':
				case 'confirm':
					$pid = $this->getPid('register');
					break;
				case 'confirmInvitation':
					$pid = $this->getPid('confirm');
					break;
				default:
					$pid = $TSFE->id;
					break;
			}
		}
		$this->pid[$type] = $pid;
	}

	function getMode()	{
		return $this->mode;
	}

	function setMode($mode)	{
		$this->mode = $mode;
	}

	function setUseMd5Password ($useMd5Password)	{
		$this->useMd5Password = $useMd5Password;
	}

	function getUseMd5Password()	{
		return $this->useMd5Password;
	}

	function getTable ()	{
		return $this->theTable;
	}

	function setTable ($theTable)	{
		$this->theTable = $theTable;
	}

	function &getRequiredArray ()	{
		return $this->requiredArray;
	}

	function setRequiredArray (&$requiredArray)	{
		$this->requiredArray = $requiredArray;
	}

	function getSetfixedEnabled ()	{
		return $this->setfixedEnabled;
	}

	function setSetfixedEnabled ($setfixedEnabled)	{
		$this->setfixedEnabled = $setfixedEnabled;
	}

	function getBackURL()	{
		$rc = rawurldecode($this->getFeUserData('backURL'));
		return $rc;
	}

	/**
		*  Get the stored variables using the hash value to access the database
		*/
	function getStoredURL($regHash) {
		global $TYPO3_DB;

			// get the serialised array from the DB based on the passed hash value
		$varArray = array();
		$res = $TYPO3_DB->exec_SELECTquery('params','cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash,'cache_md5params'));
		while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$varArray = unserialize($row['params']);
		}
		$TYPO3_DB->sql_free_result($res);

			// convert the array to one that will be properly incorporated into the GET global array.
		$retArray = array();
		foreach($varArray as $key => $val)	{
			$search = array('[\]]', '[\[]');
			$replace = array ( '\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			eval("\$retArray".$newkey."='$val';");
		}
		return $retArray;
	}	// getStoredURL


	/**
		*  Clears obsolete hashes used for short url's
		*/
	function cleanShortUrlCache() {
		global $TYPO3_DB;

		$shortUrlLife = intval($this->conf['shortUrlLife']) ? strval(intval($this->conf['shortUrlLife'])) : '30';
		$max_life = time() - (86400 * intval($shortUrlLife));
		$res = $TYPO3_DB->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');	
	}	// cleanShortUrlCache
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/model/class.tx_srfeuserregister_controldata.php']);
}
?>
