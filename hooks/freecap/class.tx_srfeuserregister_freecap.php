<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Stanislas Rolland <typo3@sjbr.ca>
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
if (t3lib_extMgm::isLoaded('sr_freecap')) {
	require_once(t3lib_extMgm::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
}

/**
 * Hook for captcha image marker when extension 'fsr_freecap' is used
 */
class tx_srfeuserregister_freecap {
	/**
	 * Sets the value of captcha markers
	 */
	public function addGlobalMarkers (&$markerArray, $markerObject) {
		$cmdKey = $markerObject->controlData->getCmdKey();
		if (t3lib_extMgm::isLoaded('sr_freecap') && $markerObject->conf[$cmdKey . '.']['evalValues.']['captcha_response'] == 'freecap') {
			$freeCap = t3lib_div::getUserObj('&tx_srfreecap_pi2');
			$captchaMarkerArray = $freeCap->makeCaptcha();
		} else {
			$captchaMarkerArray = array('###SR_FREECAP_NOTICE###' => '', '###SR_FREECAP_CANT_READ###' => '', '###SR_FREECAP_IMAGE###' => '', '###SR_FREECAP_ACCESSIBLE###' => '');
		}
		$markerArray = array_merge($markerArray, $captchaMarkerArray);
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
			trim($cmdParts[0]) == 'freecap' &&
			t3lib_extMgm::isLoaded('sr_freecap') &&
			isset($dataArray[$theField])
		) {
			$freeCap = t3lib_div::getUserObj('&tx_srfreecap_pi2');
				// Save the sr_freecap word_hash
				// sr_freecap will invalidate the word_hash after calling checkWord
			$sessionData = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_' . $freeCap->extKey);
			if (!$freeCap->checkWord($dataArray[$theField])) {
				$errorField = $theField;
			} else {
				// Restore sr_freecap word_hash
				$GLOBALS['TSFE']->fe_user->setKey(
					'ses',
					'tx_' . $freeCap->extKey,
					$sessionData
				);
				$GLOBALS['TSFE']->storeSessionData();
			}
		}
		return $errorField;
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXT . '/hooks/freecap/class.tx_srfeuserregister_freecap.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/' . SR_FEUSER_REGISTER_EXT . '/hooks/freecap/class.tx_srfeuserregister_freecap.php']);
}
?>