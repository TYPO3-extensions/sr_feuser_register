<?php
namespace SJBR\SrFeuserRegister\Captcha;

/*
 *  Copyright notice
 *
 *  (c) 2012-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook for captcha image marker when extension 'sr_freecap' is used
 */
class Freecap implements CaptchaInterface
{
	/**
	 * Sets the value of captcha markers
	 */
	public function addGlobalMarkers(&$markerArray, $markerObject)
	{
		$cmdKey = $markerObject->controlData->getCmdKey();
		if (ExtensionManagementUtility::isLoaded('sr_freecap') && $markerObject->conf[$cmdKey . '.']['evalValues.']['captcha_response'] === 'freecap') {
			$freeCap = GeneralUtility::makeInstance('SJBR\\SrFreecap\\PiBaseApi');
			$captchaMarkerArray = $freeCap->makeCaptcha();
		} else {
			$captchaMarkerArray = array('###SR_FREECAP_NOTICE###' => '', '###SR_FREECAP_CANT_READ###' => '', '###SR_FREECAP_IMAGE###' => '', '###SR_FREECAP_ACCESSIBLE###' => '');
		}
		$markerArray = array_merge($markerArray, $captchaMarkerArray);
	}

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
	public function evalValues($theTable, $dataArray, $theField, $cmdKey, $cmdParts)
	{
		$errorField = '';
		if (trim($cmdParts[0]) === 'freecap' && ExtensionManagementUtility::isLoaded('sr_freecap') && isset($dataArray[$theField])) {
			$freeCap = GeneralUtility::makeInstance('SJBR\\SrFreecap\\PiBaseApi');
			$sessionNameSpace = '';
			if (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['sr_freecap_EidDispatcher'])) {
				$sessionNameSpace = 'tx_srfreecap';
			}
			// Save the sr_freecap word_hash
			// sr_freecap will invalidate the word_hash after calling checkWord
			$sessionData = $GLOBALS['TSFE']->fe_user->getKey('ses', $sessionNameSpace);
			if (!$freeCap->checkWord($dataArray[$theField])) {
				$errorField = $theField;
			} else {
				// Restore sr_freecap word_hash
				$GLOBALS['TSFE']->fe_user->setKey(
					'ses',
					$sessionNameSpace,
					$sessionData
				);
				$GLOBALS['TSFE']->storeSessionData();
			}
		}
		return $errorField;
	}
}