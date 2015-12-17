<?php
namespace SJBR\SrFeuserRegister\Captcha;

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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Inferface for captcha hooks
 */
interface CaptchaInterface extends SingletonInterface
{
	/**
	 * Determines whether the required captcha extension is loaded
	 *
	 * @return boolean true if the required captcha extension is loaded
	 */
	public function isLoaded();

	/**
	 * Returns the eval rule for this captcha
	 *
	 * @return string the eval rule for this captcha
	 */
	public function getEvalRule();

	/**
	 * Sets the value of captcha markers
	 *
	 * @param array $markerArray: a marker array
	 * @param string $cmdKey: the cmd being processed
	 * @param array $conf: the plugin configuration
	 * @return void
	 */
	public function addGlobalMarkers(array &$markerArray, $cmdKey, array $conf);

	/**
	 * Evaluates the captcha word
	 *
	 * @param string $theTable: the name of the table in use
	 * @param array $dataArray: current input array
	 * @param string $theField: the name of the captcha field
	 * @param string $cmdKey: the current command key
	 * @param array $cmdParts: parts of the 'eval' command
	 * @return string The name of the field in error or empty string
	 */
	public function evalValues($theTable, array $dataArray, $theField, $cmdKey, array $cmdParts);
}