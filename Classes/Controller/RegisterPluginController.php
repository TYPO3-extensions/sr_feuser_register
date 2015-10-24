<?php
namespace SJBR\SrFeuserRegister\Controller;

/*
 *  Copyright notice
 *
 *  (c) 1999-2003 Kasper Skårhøj <kasperYYYY@typo3.com>
 *  (c) 2004-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Utility\ConfigurationCheckUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Front end user self-registration and profile maintenance
 */
class RegisterPluginController extends AbstractPlugin
{

	public $cObj;

	/**
	 * @var string Extension name
	 */
	protected $extensionName = 'SrFeuserRegister';

	/**
	 * Used for CSS classes and variables
	 *
	 * @var string
	 */
	public $prefixId = 'tx_srfeuserregister_pi1';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	public $extKey = 'sr_feuser_register';

	public function main($content, $conf)
	{
		$this->pi_setPiVarDefaults();
		$this->conf = &$conf;
		// Check extension requirements
		$content = ConfigurationCheckUtility::checkRequirements($this->extKey);
		// Check installation security settings
		$content .= ConfigurationCheckUtility::checkSecuritySettings($this->extKey);
		// Check presence of deprecated markers
		$content .= ConfigurationCheckUtility::checkDeprecatedMarkers($this->extKey, $conf);
		// If no error content, proceed
		if (!$content) {
			$mainObj = GeneralUtility::makeInstance('tx_srfeuserregister_control_main');
			$mainObj->cObj = $this->cObj;
			$mainObj->extensionName = $this->extensionName;
			$content = $mainObj->main($content, $conf, $this, 'fe_users');
		}
		return $content;
	}
}