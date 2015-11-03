<?php

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

/**
 * Authentication functions
 */
class tx_srfeuserregister_auth {
	public $conf = array();
	public $config = array();
	public $authCode;


	public function init ($confObj) {
		$this->conf = $confObj->getConf();
		$this->config = $confObj->getConfig();
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
			$confObj->setConfig($this->config);
		}
	}


	public function setAuthCode ($code) {
		$this->authCode = $code;
	}


	public function getAuthCode () {
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
	public function authCode (
		array $record,
		$fields = '',
		$extra = '',
		$rawUrlDecode = FALSE,
		$codeLength = 0
	) {
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
			foreach ($record as $key => $value) {
				if (is_array($value)) {
					$value = implode(',', $value);
				}
				$recordCopy[$key] = $value;
			}
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
	 * @param array  $record: the record
	 * @return boolean  true if the record is authenticated
	 */
	public function aCAuth (
		array $record,
		$fields
	) {
		$result = FALSE;

		if ($this->authCode && is_array($record)) {
			$authCode = $this->authCode($record, $fields);

			if (!strcmp($this->authCode, $authCode)) {
				$result = TRUE;
			}
		}
		return $result;
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
	public function setfixedHash (
		array $record,
		$fields = '',
		$codeLength = 0
	) {
		$rawUrlDecode = TRUE;
		$result = $this->authCode(
			$record,
			$fields,
			'',
			$rawUrlDecode,
			$codeLength
		);
		return $result;
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