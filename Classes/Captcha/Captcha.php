<?php
namespace SJBR\SrFeuserRegister\Captcha;

/*
 *  Copyright notice
 *
 *  (c) 2009 Sonja Scholz <ss@cabag.ch>
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

use SJBR\SrFeuserRegister\Captcha\CaptchaInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Hook for captcha image marker when extension 'captcha' is used
 */
class Captcha implements CaptchaInterface
{
	/**
	 * Determines whether the required captcha extension is loaded
	 *
	 * @return boolean true if the required captcha extension is loaded
	 */
	public function isLoaded()
	{
		return ExtensionManagementUtility::isLoaded('captcha');
	}

	/**
	 * Returns the eval rule for this captcha
	 *
	 * @return string the eval rule for this captcha
	 */
	public function getEvalRule()
	{
		return 'captcha';
	}
	
	/**
	 * Sets the value of captcha markers
	 */
	public function addGlobalMarkers(array &$markerArray, $cmdKey, array $conf)
	{
		if ($this->isLoaded() && $conf[$cmdKey . '.']['evalValues.']['captcha_response'] === 'captcha') {
			$markerArray['###CAPTCHA_IMAGE###'] = '<img src="' . ExtensionManagementUtility::siteRelPath('captcha') . 'captcha/captcha.php" alt="" />';
		} else {
			$markerArray['###CAPTCHA_IMAGE###'] = '';
		}
	}

	/**
	 * Evaluates the captcha word
	 *
	 * @param string $theTable: the name of the table in use
	 * @param array $dataArray: current input array
	 * @param string $theField: the name of the captcha field
	 * @param string $cmdKey: the current command key
	 * @param array $cmdParts: parts of the 'eval' command
	 * @param string $extensionName: name of the extension
	 * @return string The name of the field in error or empty string
	 */
	public function evalValues($theTable, array $dataArray, $theField, $cmdKey, array $cmdParts, $extensionName = '')
	{
		$errorField = '';
		if (trim($cmdParts[0]) === 'captcha' && $this->isLoaded() && isset($dataArray[$theField])) {
			$captchaString = '';
			$started = session_start();
			if (isset($_SESSION['tx_captcha_string'])) {
				$captchaString = $_SESSION['tx_captcha_string'];
				if (empty($captchaString) || $dataArray['captcha_response'] !== $captchaString) {
					$errorField = $theField;
				} else {
					$_SESSION['tx_captcha_string'] = '';
				}
			} else {
				$errorField = $theField;
			}
		}
		return $errorField;
	}
}