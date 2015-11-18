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
	 * SrFreecap object
	 *
	 * @var \SJBR\SrFreecap\PiBaseApi
	 */
	protected $srFreecap = null;

	/**
	 * Determines whether the required captcha extension is loaded
	 *
	 * @return boolean true if the required captcha extension is loaded
	 */
	public function isLoaded()
	{
		return ExtensionManagementUtility::isLoaded('sr_freecap');
	}

	/**
	 * Returns the eval rule for this captcha
	 *
	 * @return string the eval rule for this captcha
	 */
	public function getEvalRule()
	{
		return 'freecap';
	}

	/**
	 * Sets the value of captcha markers
	 */
	public function addGlobalMarkers(array &$markerArray, $cmdKey, array $conf)
	{
		if ($conf[$cmdKey . '.']['evalValues.']['captcha_response'] === 'freecap' && $this->initialize() !== null) {
			$captchaMarkerArray = $this->srFreecap->makeCaptcha();
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
	public function evalValues($theTable, array $dataArray, $theField, $cmdKey, array $cmdParts)
	{
		$errorField = '';
		if (trim($cmdParts[0]) === 'freecap' && isset($dataArray[$theField]) && $this->initialize() !== null) {
			$sessionNameSpace = '';
			if (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['sr_freecap_EidDispatcher'])) {
				$sessionNameSpace = 'tx_srfreecap';
			}
			// Save the sr_freecap word_hash
			// sr_freecap will invalidate the word_hash after calling checkWord
			$sessionData = $GLOBALS['TSFE']->fe_user->getKey('ses', $sessionNameSpace);
			if (!$this->srFreecap->checkWord($dataArray[$theField])) {
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

	/**
	 * Initializes de SrFreecap object
	 */
	protected function initialize()
	{
		if ($this->srFreecap === null && $this->isLoaded()) {
			$this->srFreecap = GeneralUtility::makeInstance('SJBR\\SrFreecap\\PiBaseApi');
		}
		return $this->srFreecap;
	}
}