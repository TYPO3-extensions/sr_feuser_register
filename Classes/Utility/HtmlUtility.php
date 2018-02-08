<?php
namespace SJBR\SrFeuserRegister\Utility;

/*
 *  Copyright notice
 *
 *  (c) 2015-2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * HTML utility functions
 */

class HtmlUtility
{
	/**
	 * Removes HTML comments contained in input content string
	 *
	 * @param string $content: the input content
	 * @param boolean $preserveSpecialComments: if set, special comments are preserved
	 * @return string the input content with HTML comment removed
	 */
	public static function removeHTMLComments($content, $preserveSpecialComments = false)
	{
		if ($preserveSpecialComments) {
			// Preserve Outlook conditional comments: <!--[if mso]> ... <![endif]-->
			$result = preg_replace('/<!(?:--[^\[][\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
		} else {
			$result = preg_replace('/<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
		}
		return $result;
	}

	/**
	 * Replaces HTML br tags with line feeds in input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with HTML br tags replaced
	 */
	public static function replaceHTMLBr($content)
	{
		$result = preg_replace('/<br\s?\/?>/', LF, $content);
		return $result;
	}

	/**
	 * Removes all HTML tags from input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with HTML tags removed
	 */
	public static function removeHtmlTags($content)
	{
		// Preserve <http://...> constructs
		$result = str_replace('<http', '###http', $content);
		$result = strip_tags($result);
		$result = str_replace('###http', '<http', $result);
		return $result;
	}

	/**
	 * Removes superfluous line feeds from input content string
	 *
	 * @param string $content: the input content
	 * @return string the input content with superfluous fine feeds removed
	 */
	public static function removeSuperfluousLineFeeds($content)
	{
		$result = preg_replace('/' . preg_quote(CR . LF) . '/', LF, $content);
		$result = preg_replace('/[' . preg_quote(LF) . ']{3,}/', LF . LF, $result);
		return $result;
	}
}