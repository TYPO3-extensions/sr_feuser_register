<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skårhøj <kasperYYYY@typo3.com>
*  (c) 2004-2008 Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca)>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * Front End creating/editing/deleting records authenticated by fe_user login.
 * A variant restricted to front end user self-registration and profile maintenance, with a number of enhancements (see the manual).
 *
 * $Id$
 * 
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <contact@fholzinger.com>
 * @maintainer	Franz Holzinger <contact@fholzinger.com> 
 *
 *
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_BE_srfeuserregister.'control/class.tx_srfeuserregister_control_main.php');


class tx_srfeuserregister_pi1_base extends tslib_pibase {

		// Plugin initialization variables
	var $prefixId = 'tx_srfeuserregister_pi1';		// Should be same as classname of the plugin, used for CSS classes, variables
	var $scriptRelPath = 'pi1/class.tx_srfeuserregister_pi1_base.php'; // Path to this script relative to the extension dir.
	var $extKey = SR_FEUSER_REGISTER_EXTkey;		// Extension key.

	function main($content, &$conf) {
		global $TSFE;

		$this->pi_setPiVarDefaults();

		$mainObj = &t3lib_div::getUserObj('&tx_srfeuserregister_control_main');
		$mainObj->cObj = &$this->cObj;
		$content = &$mainObj->main($content, $conf, $this, 'fe_users');
		return $content;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_base.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_base.php']);
}
?>
