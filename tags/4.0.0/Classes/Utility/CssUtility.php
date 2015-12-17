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
 * Css utility functions from AbstractPlugin
 */
class CssUtility
{
	/**
	 * Returns a class-name prefixed with $this->prefixId and with all underscores substituted to dashes (-)
	 *
	 * @param string $prefixId: the prefix
	 * @param string $class The class name (or the END of it since it will be prefixed by $prefixId.'-')
	 * @return string The combined class name (with the correct prefix)
	 */
	static public function getClassName($prefixId, $class) {
		return str_replace('_', '-', $prefixId) . ($prefixId ? '-' : '') . $class;
	}

	/**
	 * Returns the class-attribute with the correctly prefixed classname
	 *
	 * @param string $prefixId: the prefix
	 * @param string $class The class name(s) (suffix) - separate multiple classes with commas
	 * @param string $addClasses Additional class names which should not be prefixed - separate multiple classes with commas
	 * @return string A "class" attribute with value and a single space char before it.
	 */
	static public function classParam($prefixId, $class, $addClasses = '') {
		$output = '';
		foreach (GeneralUtility::trimExplode(',', $class) as $v) {
			$output .= ' ' . self::getClassName($prefixId, $v);
		}
		foreach (GeneralUtility::trimExplode(',', $addClasses) as $v) {
			$output .= ' ' . $v;
		}
		return ' class="' . trim($output) . '"';
	}

	/**
	 * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
	 * All content returned from your plugins should be returned through this function so all content from your plugin is encapsulated in a <div>-tag nicely identifying the content of your plugin.
	 *
	 * @param string $prefixId: the prefix
	 * @param string $str HTML content to wrap in the div-tags with the "main class" of the plugin
	 * @return string HTML content wrapped, ready to return to the parent object.
	 */
	static public function wrapInBaseClass($prefixId, $str) {
		$content = '<div class="' . str_replace('_', '-', $prefixId) . '">
		' . $str . '
	</div>
	';
		if (!$GLOBALS['TSFE']->config['config']['disablePrefixComment']) {
			$content = '


	<!--

		BEGIN: Content of plugin "' . $prefixId . '"

	-->
	' . $content . '
	<!-- END: Content of plugin "' . $prefixId . '" -->

	';
		}
		return $content;
	}
}