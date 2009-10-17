<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Franz Holzinger <franz@ttproducts.de>
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
 * Part of the sr_feuser_register (Front End User Registration) extension.
 *
 * control data store functions
 *
 * $Id$
 *
 * @author	Franz Holzinger <franz@ttproducts.de>
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
	var $sys_language_content;
	var $feUserData = array();
	var $jsMd5Added; // If the JavaScript for MD5 encoding has already been added


	function init (&$conf, $prefixId, $extKey, $piVars, $theTable)	{
		global $TSFE;

		$this->conf = &$conf;
		$this->site_url = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$this->prefixId = $prefixId;
		$this->extKey = $extKey;
		$this->piVars = $piVars;
		$this->setTable($theTable);

		$this->sys_language_content = (t3lib_div::testInt($TSFE->config['config']['sys_language_uid']) ? $TSFE->config['config']['sys_language_uid'] : 0);

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

			// Get hash variable if provided and if short url feature is enabled
		$feUserData = t3lib_div::_GP($this->getPrefixId());

		// <Steve Webster added short url feature>
		if ($this->conf['useShortUrls']) {
			$this->cleanShortUrlCache();
			if (isset($feUserData) && is_array($feUserData))	{
				$regHash = $feUserData['regHash'];
			}
			if (!$regHash)	{
				$getData = t3lib_div::_GET($this->getPrefixId());
				if (isset($getData) && is_array($getData))	{
					$regHash = $getData['regHash'];
				}
			}

				// Check and process for short URL if the regHash GET parameter exists
			if ($regHash) {
				$getVars = $this->getShortUrl($regHash);

				if (isset($getVars) && is_array($getVars) && count($getVars))	{
					$origDataFieldArray = array('sFK','cmd','submit','fetch','regHash');
					$origFeuserData = array();
// 					// copy the original values which must not be overridden by the regHash stored values
					foreach ($origDataFieldArray as $origDataField)	{
						if (isset($feUserData[$origDataField]))	{
							$origFeuserData[$origDataField] = $feUserData[$origDataField];
						}
					}
					$restoredFeUserData = $getVars[$this->getPrefixId()];

					foreach ($getVars as $k => $v ) {
						// restore former GET values for the url
						t3lib_div::_GETset($v,$k);
					}
					if ($restoredFeUserData['rU'] > 0 && $restoredFeUserData['rU'] == $feUserData['rU'])	{
						$feUserData = array_merge($feUserData, $restoredFeUserData);
					} else {
						$feUserData = $restoredFeUserData;
					}
					if (is_array($feUserData))	{
						$feUserData = array_merge($feUserData, $origFeuserData);
					} else {
						$feUserData = $origFeuserData;
					}
				}
			}
		}

		if (isset($feUserData) && is_array($feUserData))	{
			$this->setFeUserData($feUserData);
		}

			// Establishing compatibility with Direct Mail extension
		$piVarArray = array('rU', 'aC', 'cmd', 'sFK');
		foreach($piVarArray as $pivar)	{
			$value = htmlspecialchars(t3lib_div::_GP($pivar));
			if ($value != '')	{
				$this->setFeUserData($value, $pivar);
			}
		}
		if (isset($feUserData) && is_array($feUserData) && isset($feUserData['cmd']))	{
			$cmd = htmlspecialchars($feUserData['cmd']);
			$this->setCmd($cmd);
		}
		$feUserData = $this->getFeUserData();
		$this->secureInput($feUserData);
		$this->setFeUserData($feUserData);
	}


	/**
	* Changes potential malicious script code of the input to harmless HTML
	*
	* @return void
	*/
	function secureInput (&$dataArray)	{
		if (isset($dataArray) && is_array($dataArray))	{
			foreach ($dataArray as $k => $value)	{
				if (is_array($value))	{
					foreach ($value as $k2 => $value2)	{
						if (is_array($value2))	{
							foreach ($value2 as $k3 => $value3)	{
								if ($k3 != 'password' && $k3 != 'password_again')	{
									$value3 = htmlspecialchars_decode($value3);
									$dataArray[$k][$k2][$k3] = htmlspecialchars($value3);
								}
							}
						} else {
							if ($k2 != 'password' && $k2 != 'password_again')	{
								$value2 = htmlspecialchars_decode($value2);
								$dataArray[$k][$k2] = htmlspecialchars($value2);
							}
						}
					}
				} else {
					if ($k != 'password' && $k != 'password_again')	{
						$value = htmlspecialchars_decode($value);
						$dataArray[$k] = htmlspecialchars($value);
					}
				}
			}
		}
	}


	/**
	* undoes HTML encryption
	*
	* @return void
	*/
	function decodeInput (&$dataArray)	{
		if (isset($dataArray) && is_array($dataArray))	{
			foreach ($dataArray as $k => $value)	{
				if (is_array($value))	{
					foreach ($value as $k2 => $value2)	{
						if (is_array($value2))	{
							foreach ($value2 as $k3 => $value3)	{
								if ($k3 != 'password' && $k3 != 'password_again')	{
									$dataArray[$k][$k2][$k3] = htmlspecialchars_decode($value3);
								}
							}
						} else {
							if ($k2 != 'password' && $k2 != 'password_again')	{
								$dataArray[$k][$k2] = htmlspecialchars_decode($value2);
							}
						}
					}
				} else {
					if ($k != 'password' && $k != 'password_again')	{
						$dataArray[$k] = htmlspecialchars_decode($value);
					}
				}
			}
		}
	}


	function useCaptcha ($theCode)	{
		$rc = FALSE;

		if ((t3lib_extMgm::isLoaded('sr_freecap') &&
			t3lib_div::inList($this->conf[$theCode.'.']['fields'], 'captcha_response') &&
			is_array($this->conf[$theCode.'.']) &&
			is_array($this->conf[$theCode.'.']['evalValues.']) &&
			$this->conf[$theCode.'.']['evalValues.']['captcha_response'] == 'freecap')) {
			$rc = TRUE;
		}
		return $rc;
	}


	// example: plugin.tx_srfeuserregister_pi1.conf.sys_dmail_category.ALL.sys_language_uid = 0
	function getSysLanguageUid ($theCode,$theTable)	{

		if (
			isset($this->conf['conf.']) && is_array($this->conf['conf.']) &&
			isset($this->conf['conf.'][$theTable.'.']) && is_array($this->conf['conf.'][$theTable.'.']) &&
			isset($this->conf['conf.'][$theTable.'.'][$theCode.'.']) && is_array($this->conf['conf.'][$theTable.'.'][$theCode.'.']) &&
			t3lib_div::testInt($this->conf['conf.'][$theTable.'.'][$theCode.'.']['sys_language_uid'])
		)	{
			$rc = $this->conf['conf.'][$theTable.'.'][$theCode.'.']['sys_language_uid'];
		} else {
			$rc = $this->sys_language_content;
		}
		return $rc;
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


	function setPiVars ($piVars)	{
		$this->piVars = $piVars;
	}


	function getCmd () {
		return $this->cmd;
	}


	function setCmd ($cmd) {
		$this->cmd = $cmd;
	}


	function getCmdKey () {
		return $this->cmdKey;
	}


	function setCmdKey ($cmdKey)	{
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


	function getFailure ()	{
		return $this->failure;
	}


	function setFailure ($failure)	{
		$this->failure = $failure;
	}


	function setSubmit ($bSubmit)	{
		$this->bSubmit = $bSubmit;
	}


	function getSubmit ()	{
		return $this->bSubmit;
	}


	function getPid ($type='')	{
		global $TSFE;

		if ($type)	{
			if (isset($this->pid[$type]))	{
				$rc = $this->pid[$type];
			}
		}

		if (!$rc) {
			$rc = (t3lib_div::testInt($this->conf['pid']) ? intval($this->conf['pid']) : $TSFE->id);
		}

		return $rc;
	}


	function setPid ($type, $pid)	{
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


	function getMode ()	{
		return $this->mode;
	}


	function setMode ($mode)	{
		$this->mode = $mode;
	}


	function setUseMd5Password ($useMd5Password)	{
		$this->useMd5Password = $useMd5Password;
	}


	function getUseMd5Password()	{
		return $this->useMd5Password;
	}


	function getJSmd5Added ()	{
		return ($this->jsMd5Added);
	}


	function setJSmd5Added ($var)	{
		$this->jsMd5Added = TRUE;
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


	function getBackURL ()	{
		$rc = rawurldecode($this->getFeUserData('backURL'));
		return $rc;
	}


	/**
	* Checks if preview display is on.
	*
	* @return boolean  true if preview display is on
	*/
	function isPreview () {
		$rc = '';
		$cmdKey = $this->getCmdKey();

		$rc = ($this->conf[$cmdKey.'.']['preview'] && $this->getFeUserData('preview'));
		return $rc;
	}	// isPreview


	/**
	*  Get the stored variables using the hash value to access the database
	*/
	function getShortUrl ($regHash) {
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
			$replace = array ('\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			eval("\$retArray".$newkey."='$val';");
		}

		return $retArray;
	}	// getShortUrl


	/**
	*  Get the stored variables using the hash value to access the database
	*/
	function deleteShortUrl ($regHash) {
		global $TYPO3_DB;

		if ($regHash != '')	{
			// get the serialised array from the DB based on the passed hash value
			$TYPO3_DB->exec_DELETEquery('cache_md5params','md5hash='.$TYPO3_DB->fullQuoteStr($regHash,'cache_md5params'));
		}
	}


	/**
	*  Clears obsolete hashes used for short url's
	*/
	function cleanShortUrlCache () {
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
