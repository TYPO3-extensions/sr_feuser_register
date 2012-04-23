<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Sonja Scholz <ss@cabag.ch>
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Hook for captcha image marker when extension 'captcha' is used
 */
class tx_srfeuserregister_captcha {
	/**
	 * Sets the value of captcha markers
	 */
	public function addGlobalMarkers(&$markerArray, $markerObject) {
		$cmdKey = $markerObject->controlData->getCmdKey();
		if (t3lib_extMgm::isLoaded('captcha') && $markerObject->conf[$cmdKey . '.']['evalValues.']['captcha_response'] == 'captcha') {
			$markerArray['###CAPTCHA_IMAGE###'] = '<img src="' . t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php" alt="" />';
		} else {
			$markerArray['###CAPTCHA_IMAGE###'] = '';
		}
	}

	/**
	 * Evaluates the captcha word
	 */
	public function evalValues (
		$theTable,
		$dataArray,
		$origArray,
		$markContentArray,
		$cmdKey,
		$requiredArray,
		$theField,
		$cmdParts,
		$bInternal,
		&$test,
		$dataObject
		) {
		$errorField = '';
			// Must be set to FALSE if it is not a test
		$test = FALSE;
		if (
			trim($cmdParts[0]) === 'captcha' &&
			t3lib_extMgm::isLoaded('captcha') &&
			isset($dataArray[$theField])
		) {
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
if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXTkey . '/hooks/captcha/class.tx_srfeuserregister_captcha.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXTkey . '/hooks/captcha/class.tx_srfeuserregister_captcha.php']);
}
?>
