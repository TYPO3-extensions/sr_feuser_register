<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Stanislas Rolland (stanislas.rolland@sjbr.ca)
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
 * authentication functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper2007@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_auth {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $authCode;

	function init (&$pibase, &$conf, &$config)	{
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->config['addKey'] = 'A';

			// Setting the authCode length
		$this->config['codeLength'] = 8;
		if (isset($this->conf['authcodeFields.']) && is_array($this->conf['authcodeFields.']))	{
			if (intval($this->conf['authcodeFields.']['codeLength']))	{
				$this->config['codeLength'] = intval($this->conf['authcodeFields.']['codeLength']);
			}

			if ($this->conf['authcodeFields.']['addKey'])	{
				$this->config['addKey'] = $this->conf['authcodeFields.']['addKey'];
			}
		}
	}

	function setAuthCode ($code)	{
		$this->authCode = $code;
	}

	function getAuthCode ()	{
		return $this->authCode;
	}

	/**
	* Computes the authentication code
	*
	* @param array  $r: the data array
	* @param string  $extra: some extra mixture
	* @return string  the code
	*/
	function authCode ($r, $fields = '', $extra = '') {

		$rc = '';
		$l = $this->config['codeLength'];
		$value = '';

		if ($fields) {
			$recCopy_temp=array();
			$fieldArr = t3lib_div::trimExplode(',', $fields, 1);

			foreach($fieldArr as $k => $v) {

				if (isset($r[$v]))	{
					if (is_array($r[$v]))	{
						$recCopy_temp[$k] = implode(',',$r[$v]);
					} else {
						$recCopy_temp[$k] = $r[$v];
					}
				}
			}

			if (isset($recCopy_temp) && is_array($recCopy_temp))	{
				$preKey = implode('|',$recCopy_temp);
			}
		}
		$value .= $preKey . ($extra != '' ? '|' . $extra : '') . '|'  . $this->config['addKey'];

		if ($this->conf['authcodeFields.']['addDate']) {
			$value .= '|'.date($this->conf['authcodeFields.']['addDate']);
		}
		$value .= '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$rc = substr(md5($value), 0, $l);
		return $rc;
	}	// authCode


	/**
	* Authenticates a record
	*
	* @param array  $r: the record
	* @return boolean  true if the record is authenticated
	*/
	function aCAuth ($r, $fields) {
		$rc = false;

		if ($this->authCode) {
			$authCode = $this->authCode($r, $fields);

			if (!strcmp($this->authCode, $authCode))	{
				$rc = true;
			}
		}
		return $rc;
	}

	/**
	* Computes the setfixed hash
	* a variant of t3lib_div::stdAuthCode
	*
	* @param array  $recCopy: copy of the record
	* @param string  $fields: the list of fields to include in the hash computation
	* @return string  the hash value
	*/
	function setfixedHash ($recCopy, $fields = '') {

		$recCopy_temp=array();
		if ($fields) {
			$fieldArr = t3lib_div::trimExplode(',', $fields, 1);
			foreach($fieldArr as $k => $v) {
				if (isset($recCopy[$v]))	{
					if (is_array($recCopy[$v]))	{
						$recCopy_temp[$k] = implode(',',$recCopy[$v]);
					} else {
						$recCopy_temp[$k] = $recCopy[$v];
					}
				}
			}
		} else {
			$recCopy_temp = $recCopy;
		}

		if (isset($recCopy_temp) && is_array($recCopy_temp))	{
			$preKey = implode('|',$recCopy_temp);
		}
		$authCode = $preKey . '|' . $this->config['addKey'];

		if ($this->conf['authcodeFields.']['addDate']) {
			$authCode .= '|' . date($this->conf['authcodeFields.']['addDate']);
		}
		$authCode .= '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$authCode = substr(md5($authCode), 0, $this->config['codeLength']);

		return $authCode;
	}	// setfixedHash

	/**
	* Generates a token for the form to secure agains Cross Site Request Forgery (CSRF)
	*
	* @param void
	* @return string  the token value
	*/
	function generateToken ()	{
		$time = time();
		$rc = md5($time . getmypid() . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

		return $rc;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_auth.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_auth.php']);
}
?>