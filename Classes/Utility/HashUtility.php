<?php
namespace SJBR\SrFeuserRegister\Utility;

/*
 *  Copyright notice
 *
 *  (c) 2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * Hash handling for the short url feature
 */
class HashUtility
{
	/**
	 * Store the parameters and return a replacement hash
	 *
	 * @param array $parameterArray: the array of parameters
	 * @return string the value of the calculated hash
	 */
	static public function getHashFromParameters(array $parameterArray)
	{
		// Create a unique hash value
		$cacheHash = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\CacheHashCalculator');
		$hash = $cacheHash->calculateCacheHash($parameterArray);
		$hash = substr($hash, 0, 20);
		// and store it with a serialized version of the array in the DB
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('md5hash', 'cache_md5params', 'md5hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'cache_md5params'));
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$insertFields = array('md5hash' => $hash, 'tstamp' => time(), 'type' => 99, 'params' => serialize($parameterArray));
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('cache_md5params', $insertFields);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $hash;
	}

	/**
	 *  Get the stored parameters using the hash value to access the database
	 *
	 * @param string $hash: the value of the input hash
	 * @return array the array of parameters
	 */
	static public function getParametersFromHash($hash)
	{
		// Get the serialised array from the DB based on the passed hash value
		$variables = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('params', 'cache_md5params', 'md5hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'cache_md5params'));
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$variables = unserialize($row['params']);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		// Convert the array to one that will be properly incorporated into the GET global array
		$parameterArray = array();
		foreach ($variables as $key => $value) {
			$value = str_replace('%2C', ',', $value);
			$search = array('[%5D]', '[%5B]');
			$replace = array('\']', '\'][\'');
			$newkey = "['" . preg_replace($search, $replace, $key);
			if (!preg_match('/' . preg_quote(']') . '$/', $newkey)){
				$newkey .= "']";
			}
			eval("\$parameterArray" . $newkey . " = '$value';");
		}
		return $parameterArray;
	}

	/**
	 *  Delete the stored hash
	 *
	 * @param string $regHash: the value of the regHash
	 * @return void
	 */
	static public function deleteHash($hash)
	{
		if (!empty($hash)) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_md5params', 'md5hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'cache_md5params'));
		}
	}

	/**
	 *  Clears obsolete hashes used for short url's
	 *
	 * @param array $conf: the plugin configuration
	 * @return void
	 */
	static public function cleanHashCache($conf)
	{
		$shortUrlLife = intval($conf['shortUrlLife']) ? intval($conf['shortUrlLife']) : 30;
		$max_life = time() - (86400 * $shortUrlLife);
		$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_md5params', 'tstamp<' . $max_life . ' AND type=99');
	}
}