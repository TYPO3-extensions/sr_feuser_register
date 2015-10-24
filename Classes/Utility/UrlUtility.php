<?php
namespace SJBR\SrFeuserRegister\Utility;

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
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Generates a typolink
 */
class UrlUtility
{
	/**
	 * Content object
	 *
	 * @var ContentObjectRenderer
	 */
	 static protected $cObj = null;

	/**
	 * Pi vars
	 *
	 * @var array
	 */
	 static protected $piVars = array();

	/**
	 * Generates a pibase-compliant typolink
	 *
	 * @param string $prefixId: prefix id of variables
	 * @param string $tag: string to include within <a>-tags; if empty, only the url is returned
	 * @param string $id: page id (could of the form id,type )
	 * @param array $vars: extension variables to add to the url ($key, $value)
	 * @param array $unsetVars: extension variables (piVars to unset)
	 * @param boolean $usePiVars: if set, input vars and incoming piVars arrays are merge
	 * @return string generated link or url
	 */
	static public function get($prefixId, $tag = '', $id, $vars = array(), $unsetVars = array(), $usePiVars = true)
	{
		self::initializeUrlUtility($prefixId);

		$vars = (array) $vars;
		$unsetVars = (array) $unsetVars;
		$piVars = array();
		if ($usePiVars) {
			// vars override pivars
			$vars = array_merge(self::$piVars[$prefixId], $vars);
			foreach ($unsetVars as $key) {
				if (isset($vars[$key])) {
					// unsetvars override anything
					unset($vars[$key]);
				}
			}
		}
		foreach ($vars as $key => $val) {
			$piVars[$prefixId . '%5B' . $key . '%5D'] = $val;
		}
		$url = $tag ? self::$cObj->getTypoLink($tag, $id, $piVars) : self::$cObj->getTypoLink_URL($id, $piVars);
		$url = htmlspecialchars(str_replace(array('[',']'), array('%5B', '%5D'), $url));
		return $url;
	}

	/**
	 * Returns the URL of a "typolink" create from the input parameter string, url-parameters and target
	 *
	 * @param string Link parameter; eg. "123" for page id, "kasperYYYY@typo3.com" for email address, "http://...." for URL, "fileadmin/blabla.txt" for file.
	 * @param array An array with key/value pairs representing URL parameters to set. Values NOT URL-encoded yet.
	 * @param string Specific target set, if any. (Default is using the current)
	 * @param array Configuration
	 * @return string The URL
	 */
	static public function getTypoLink_URL($params, $urlParameters = array(), $target = '', $conf = array())
	{
		self::initializeUrlUtility();
		$result = false;
		$result = self::getTypoLink('', $params, $urlParameters, $target, $conf);
		if ($url !== false) {
			$url = self::$cObj->lastTypoLinkUrl;
		}
        return $url;
    }

	/**
	 * Returns a linked string made from typoLink parameters.
	 *
	 * This function takes $label as a string, wraps it in a link-tag based on the $params string, which should contain data like that you would normally pass to the popular <LINK>-tag in the TSFE.
	 * Optionally you can supply $urlParameters which is an array with key/value pairs that are rawurlencoded and appended to the resulting url.
	 *
	 * @param string Text string being wrapped by the link.
	 * @param string Link parameter; eg. "123" for page id, "kasperYYYY@typo3.com" for email address, "http://...." for URL, "fileadmin/blabla.txt" for file.
	 * @param array An array with key/value pairs representing URL parameters to set. Values NOT URL-encoded yet.
	 * @param string Specific target set, if any. (Default is using the current)
	 * @param array Configuration
	 * @return string The wrapped $label-text string
	 */
	 static public function getTypoLink($label, $params, $urlParameters = array(), $target = '', $conf = array())
	 {
	 	 $url = false;
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
	 	 	 	 $conf['additionalParams'] .= GeneralUtility::implodeArrayForUrl('', $urlParameters);
	 	 	 }
	 	 } else {
	 	 	 $conf['additionalParams'] .= $urlParameters;
	 	 }
	 	 $url = self::$cObj->typolink($label, $conf);
	}

	/**
	 * Initializes variables
	 *
	 * @param string $prefixId: prefix id of variables
	 * @return void
	 */
	static protected function initializeUrlUtility($prefixId = '')
	{
		if (self::$cObj === null) {
			self::$cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		}
		if ($prefixId && empty(self::$piVars[$prefixId])) {
				self::$piVars[$prefixId] = GeneralUtility::_GPmerged($prefixId);
		}
	}
}