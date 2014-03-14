<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Kasper Skårhøj (kasperYYYY@typo3.com)
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
 * Collection of static functions contributed by different people
 *
 * This class contains diverse staticfunctions in "alpha" status.
 * It is a kind of quarantine for newly suggested functions.
 *
 * The class offers the possibilty to quickly add new functions to div2007,
 * without much planning before. In a second step the functions will be reviewed,
 * adapted and fully implemented into the system of div2007 classes.
 *
 * @package    TYPO3
 * @subpackage div2007
 * @author     Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author     Franz Holzinger <franz@ttproducts.de>
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version    SVN: $Id: class.tx_div2007_alpha5.php 189 2013-06-10 13:39:37Z franzholz $
 * @since      0.1
 */

class Tx_SrFeuserRegister_Utility_UrlUtility {

	/**
	 * Returns a linked string made from typoLink parameters.
	 *
	 * This function takes $label as a string, wraps it in a link-tag based on the $params string, which should contain data like that you would normally pass to the popular <LINK>-tag in the TSFE.
	 * Optionally you can supply $urlParameters which is an array with key/value pairs that are rawurlencoded and appended to the resulting url.
	 *
	 * @param	object		cObject
	 * @param	string		Text string being wrapped by the link.
	 * @param	string		Link parameter; eg. "123" for page id, "kasperYYYY@typo3.com" for email address, "http://...." for URL, "fileadmin/blabla.txt" for file.
	 * @param	array		An array with key/value pairs representing URL parameters to set. Values NOT URL-encoded yet.
	 * @param	string		Specific target set, if any. (Default is using the current)
	 * @param	array		Configuration
 	 * @return	string		The wrapped $label-text string
	 * @see getTypoLink_URL()
	 */
	static public function getTypoLink(
		$cObj,
		$label,
		$params,
		$urlParameters = array(),
		$target = '',
		$conf = array()
	) {
		$result = FALSE;

		if (is_object($cObj)) {
			$conf['parameter'] = $params;

			if ($target) {
				if (!isset($conf['target'])) {
					$conf['target'] = $target;
				}
				if (!isset($conf['extTarget'])) {
					$conf['extTarget'] = $target;
				}
			}

			if (is_array($urlParameters)) {
				if (count($urlParameters)) {
					$conf['additionalParams'] .= t3lib_div::implodeArrayForUrl('', $urlParameters);
				}
			} else {
				$conf['additionalParams'] .= $urlParameters;
			}
			$result = $cObj->typolink($label, $conf);
		} else {
			$out = 'error in call of tx_div2007_alpha5::getTypoLink: parameter $cObj is not an object';
			debug($out, '$out'); // keep this
		}
		return $result;
	}


	/**
	 * Returns the URL of a "typolink" create from the input parameter string, url-parameters and target
	 *
	 * @param	object		cObject
	 * @param	string		Link parameter; eg. "123" for page id, "kasperYYYY@typo3.com" for email address, "http://...." for URL, "fileadmin/blabla.txt" for file.
	 * @param	array		An array with key/value pairs representing URL parameters to set. Values NOT URL-encoded yet.
	 * @param	string		Specific target set, if any. (Default is using the current)
	 * @param	array		Configuration
	 * @return	string		The URL
	 * @see getTypoLink()
	 */
	static public function getTypoLink_URL (
		$cObj,
		$params,
		$urlParameters = array(),
		$target = '',
		$conf = array()
	) {
		$result = FALSE;

		if (is_object($cObj)) {
			$result = self::getTypoLink(
				$cObj,
				'',
				$params,
				$urlParameters,
				$target,
				$conf
			);
			if ($result !== FALSE) {
				$result = $cObj->lastTypoLinkUrl;
			}
		} else {
			$out = 'error in call of tx_div2007_alpha5::getTypoLink_URL: parameter $cObj is not an object';
			debug($out, '$out'); // keep this
		}

		return $result;
	}
}