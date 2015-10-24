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
		$url = $tag ? $cObj->getTypoLink($tag, $id, $piVars) : $cObj->getTypoLink_URL($id, $piVars);
		$url = htmlspecialchars(str_replace(array('[',']'), array('%5B', '%5D'), $url));
		return $url;
	}

	/**
	 * Initializes variables
	 *
	 * @param string $prefixId: prefix id of variables
	 * @return void
	 */
	static protected function initializeUrlUtility($prefixId)
	{
		if (self::$cObj === null) {
			self::$cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		}
		if (empty(self::$piVars[$prefixId])) {
				self::$piVars[$prefixId] = GeneralUtility::_GPmerged($prefixId);
		}
	}
}