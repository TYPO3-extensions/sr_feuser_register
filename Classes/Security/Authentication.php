<?php
namespace SJBR\SrFeuserRegister\Security;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Authentication functions
 */
class Authentication
{
	/**
	 * Computes the authentication code
	 * a variant of GeneralUtility::stdAuthCode with added extras
	 *
	 * @param arra $record Record
	 * @param array $conf: the plugin configuration
	 * @param string $fields List of fields from the record to include in the computation, if that is given.
	 * @param string $extra: some extra non-standard mixture
	 * @param boolean $rawUrlDecode: whether to rawurldecode the record values
	 * @param integer $codeLength: the length of the code, if different than configured
	 * @return string MD5 hash (default length of 8 for compatibility with Direct Mail)
	 */
	static public function authCode(array $record, array $conf, $fields = '', $extra = '', $rawUrlDecode = false, $codeLength = 0)
	{
		if (!$codeLength) {
			$codeLength = 8;
			if (isset($conf['authcodeFields.']) && is_array($conf['authcodeFields.']) && (int) $conf['authcodeFields.']['codeLength']) {
					$codeLength = (int) $conf['authcodeFields.']['codeLength'];
			}
		}
		$recordCopy = array();
		if ($fields) {
			$fieldArray = GeneralUtility::trimExplode(',', $fields, 1);
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
		// Additional key may be used for additional security and/or
		// to isolate multiple sr_feuser_register configurations on the same installation
		// This makes the authCode incompatible with TYPO3 standard authCode
		// See GeneralUtility::stdAuthCode
		$extraArray = array();
		if (!empty($extra)) {
			$extraArray[] = $extra;
		}
		if (isset($conf['authcodeFields.']) && is_array($conf['authcodeFields.'])) {
			// Non-standard addKey field
			if (!empty($conf['authcodeFields.']['addKey'])) {
				$extraArray[] = $conf['authcodeFields.']['addKey'];
			}
			// Non-standard addDate field
			if (isset($conf['authcodeFields.']['addDate'])) {
				$extraArray[] = date($conf['authcodeFields.']['addDate']);
			}
		}
		$extras = !empty($extraArray) ? implode('|', $extraArray) : '';
		// In GeneralUtility::stdAuthCode, $extras is empty
		$authCode = $preKey . '|' . $extras . '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
		$authCode = substr(md5($authCode), 0, $codeLength);
		return $authCode;
	}

	/**
	 * Authenticates a record
	 *
	 * @param string $authCode: the authCode to validate
	 * @param array $record: the record
	 * @param array $conf: the plugin configuration
	 * @param string $fields List of fields from the record to include in the computation, if that is given.
	 * @return boolean true, if the record is authenticated
	 */
	static public function aCAuth($authCode, array $record, array $conf, $fields = '')
	{
		$result = false;
		if (!empty($authCode)) {
			$recordAuthCode = self::authCode($record, $conf, $fields);
			if (!strcmp($authCode, $recordAuthCode)) {
				$result = true;
			}
		}
		return $result;
	}

	/**
	 * Computes the setfixed hash where record values need to be rawurldecoded
	 *
	 * @param array $record: Record
	 * @param array $conf: the plugin configuration
	 * @param string $fields: List of fields from the record to include in the computation, if that is given
	 * @param integer $codeLength: The length of the code, if different than configured
	 * @return string the hash value
	 */
	static public function setfixedHash(array $record, array $conf, $fields = '', $codeLength = 0)
	{
		return self::authCode($record, $conf, $fields, '', true, $codeLength);
	}

	/**
	 * Generates a token for the form to secure agains Cross Site Request Forgery (CSRF)
	 *
	 * @param void
	 * @return string the token value
	 */
	static public function generateToken()
	{
		return md5(time() . getmypid() . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
	}
}