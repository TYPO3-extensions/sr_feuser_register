<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_auth {
	public $pibase;
	public $conf = array();
	public $config = array();
	public $authCode;

	public function init (&$pibase, &$conf, &$config) {
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->config['addKey'] = '';

			// Setting the authCode length
		$this->config['codeLength'] = 8;
		if (isset($this->conf['authcodeFields.']) && is_array($this->conf['authcodeFields.'])) {
			if (intval($this->conf['authcodeFields.']['codeLength'])) {
				$this->config['codeLength'] = intval($this->conf['authcodeFields.']['codeLength']);
			}
				// Additional key may be used for additional security and/or
				// to isolate multiple sr_feuser_register configurations on the same installation
				// This makes the authCode incompatible with TYPO3 standard authCode
				// See t3lib_div::stdAuthCode
			if ($this->conf['authcodeFields.']['addKey']) {
				$this->config['addKey'] = $this->conf['authcodeFields.']['addKey'];
			}
		}
	}

	public function setAuthCode ($code)	{
		$this->authCode = $code;
	}

	public function getAuthCode ()	{
		return $this->authCode;
	}

	/**
	 * Computes the authentication code
	 * a variant of t3lib_div::stdAuthCode with added extras
	 *
	 * @param array $record Record
	 * @param string $fields List of fields from the record to include in the computation, if that is given.
	 * @param string  $extra: some extra non-standard mixture
	 * @params boolean $rawUrlDecode: whether to rawurldecode the record values
	 * @param integer $codeLength: The length of the code, if different than configured
	 * @return string MD5 hash (default length of 8 for compatibility with Direct Mail)
	 */
	public function authCode (array $record, $fields = '', $extra = '', $rawUrlDecode = FALSE, $codeLength = 0) {
		if ($codeLength == 0) {
			$codeLength = intval($this->config['codeLength']) ? intval($this->config['codeLength']) : 8;
		}
		$recordCopy = array();
		if ($fields) {
			$fieldArray = t3lib_div::trimExplode(',', $fields, 1);
			foreach ($fieldArray as $key => $value) {
				if (isset($record[$value])) {
					if (is_array($record[$value])) {
						$recordCopy[$key] = implode(',', $record[$value]);
					} else {
						$recordCopy[$key] = $record[$value];
					}
					if ($rawUrlDecode) {
						$recordCopy[$key] = rawurldecode($recordCopy[$key]);
					}
				}
			}
		} else {
			$recordCopy = $record;
		}
		$preKey = implode('|', $recordCopy);
			// Non-standard extra fields
			// Any of these extras makes the authCode incompatible with TYPO3 standard authCode
			// See t3lib_div::stdAuthCode
		$extraArray = array();
		if ($extra != '') {
			$extraArray[] = $extra;
		}
			// Non-standard addKey field
		if ($this->config['addKey'] != '') {
			$extraArray[] = $this->config['addKey'];
		}
			// Non-standard addDate field
		if ($this->conf['authcodeFields.']['addDate']) {
			$extraArray[] = date($this->conf['authcodeFields.']['addDate']);
		}
		$extras = !empty($extraArray) ? implode('|', $extraArray) : '';
			// In t3lib_div::stdAuthCode, $extras is empty
		$authCode = $preKey . '|' . $extras . '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$authCode = substr(md5($authCode), 0, $codeLength);
		return $authCode;
	}

	/**
	 * Authenticates a record
	 *
	 * @param array  $r: the record
	 * @return boolean  true if the record is authenticated
	 */
	public function aCAuth ($r, $fields) {
		$rc = FALSE;

		if ($this->authCode) {
			$authCode = $this->authCode($r, $fields);

			if (!strcmp($this->authCode, $authCode)) {
				$rc = TRUE;
			}
		}
		return $rc;
	}

	/**
	 * Computes the setfixed hash
	 * where record values need to be rawurldecoded
	 *
	 * @param array $record: Record
	 * @param string $fields: List of fields from the record to include in the computation, if that is given
	 * @param integer $codeLength: The length of the code, if different than configured
	 * @return string  the hash value
	 */
	public function setfixedHash (array $record, $fields = '', $codeLength = 0) {
		$rawUrlDecode = TRUE;
		return $this->authCode ($record, $fields, '', $rawUrlDecode, $codeLength);
	}

	/**
	* Generates a token for the form to secure agains Cross Site Request Forgery (CSRF)
	*
	* @param void
	* @return string  the token value
	*/
	public function generateToken () {
		$time = time();
		$rc = md5($time . getmypid() . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

		return $rc;
	}
}
if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_auth.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_auth.php']);
}
?>