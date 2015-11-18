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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Determines the use of captcha
 */
class CaptchaManager
{
	/**
	 * Determines whether the use of captcha is enabled for the specified command
	 *
	 * @param string $cmdKey: the cmdKey for which the check is requested
	 * @param array $conf: the plugin configuration
	 * @param string $extensionKey: the key of the requesting extension
	 * @return boolean true, if the use of captcha is enabled
	 */
	static public function useCaptcha($cmdKey, array $conf, $extensionKey)
	{
		$useCaptcha = false;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['captcha'])
			&& GeneralUtility::inList($conf[$cmdKey . '.']['fields'], 'captcha_response')
			&& is_array($conf[$cmdKey . '.'])
			&& is_array($conf[$cmdKey . '.']['evalValues.'])
		) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['captcha'] as $classRef) {
				$captchaObj = GeneralUtility::makeInstance($classRef);
				if ($conf[$cmdKey . '.']['evalValues.']['captcha_response'] === $captchaObj->getEvalRule()) {
					$useCaptcha = true;
					break;
				}
			}
		}
		return $useCaptcha;
	}

	/**
	 * Determines whether at least one captcha extension is available
	 *
	 * @return boolean true if at least one captcha extension is available
	 */
	static public function isLoaded($extensionKey)
	{
		$isLoaded = false;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['captcha'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['captcha'] as $classRef) {
				$captchaObj = GeneralUtility::makeInstance($classRef);
				$isLoaded = $captchaObj->isLoaded();
				if ($isLoaded) {
					break;
				}
			}
		}
		return $isLoaded;
	}
}