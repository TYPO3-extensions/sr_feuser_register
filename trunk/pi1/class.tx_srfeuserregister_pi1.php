<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skaarhoj (kasper2007@typo3.com)
*  (c) 2004-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
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
 * @author Kasper Skaarhoj <kasper2007@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 *
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
	// To get the pid language overlay:
require_once(PATH_t3lib.'class.t3lib_page.php');
	// For use with images:
require_once (PATH_t3lib.'class.t3lib_basicfilefunc.php');
	// For translating items from other extensions
// require_once (t3lib_extMgm::extPath('lang').'lang.php');

require_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_urlvalidator.php');
require_once(PATH_BE_srfeuserregister.'control/class.tx_srfeuserregister_control.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_auth.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_email.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_lang.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_tca.php');
require_once(PATH_BE_srfeuserregister.'marker/class.tx_srfeuserregister_marker.php');
require_once(PATH_BE_srfeuserregister.'model/class.tx_srfeuserregister_data.php');
require_once(PATH_BE_srfeuserregister.'view/class.tx_srfeuserregister_display.php');




class tx_srfeuserregister_pi1 extends tslib_pibase {
	var $cObj;
	var $conf = array();
	var $config = array();
	
		// Plugin initialization variables
	var $prefixId = 'tx_srfeuserregister_pi1';  // Same as class name
	var $scriptRelPath = 'pi1/class.tx_srfeuserregister_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'sr_feuser_register';  // The extension key.
		
	var $setfixedEnabled = 1;
	var $incomingData = FALSE;
	var $inError = array(); // array of fields with eval errors other than absence
	var $nc = ''; // "&no_cache=1" if you want that parameter sent.
	var $additionalUpdateFields = '';
	var $sys_language_content;
	var $charset = 'iso-8859-1'; // charset to be used in emails and form conversions
	var $fileFunc = ''; // Set to a basic_filefunc object for file uploads
	var $freeCap; // object of type tx_srfreecap_pi2
	var $auth; // object of type tx_srfeuserregister_auth
	var $control; // object of type tx_srfeuserregister_control
	var $data; // object of type tx_srfeuserregister_data
	var $display; // object of type tx_srfeuserregister_display
	var $email; // object of type tx_srfeuserregister_email
	var $lang; // object of type tx_srfeuserregister_lang
	var $tca;  // object of type tx_srfeuserregister_tca
	var $marker; // object of type tx_srfeuserregister_marker


	function main($content, &$conf) {
		global $TSFE;
		$failure = false; // is set if data did not have the required fields set.

		$this->init($conf);	

		$error_message = '';
		$content = $this->control->doProcessing ($error_message);

		$rc = $this->pi_wrapInBaseClass($content);
		return $rc; 
	}


	/**
	* Initialization
	*
	* @return void
	*/
	function init(&$conf) {
		global $TSFE, $TCA, $TYPO3_CONF_VARS;

			// plugin initialization
		$this->conf = $conf;

		if (t3lib_extMgm::isLoaded('sr_freecap') ) {
			require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		}

		$this->lang = t3lib_div::makeInstance('tx_srfeuserregister_lang');
		$this->data = t3lib_div::makeInstance('tx_srfeuserregister_data');
		$this->auth = t3lib_div::makeInstance('tx_srfeuserregister_auth');
		$this->marker = t3lib_div::makeInstance('tx_srfeuserregister_marker');
		$this->tca = t3lib_div::makeInstance('tx_srfeuserregister_tca');
		$this->display = t3lib_div::makeInstance('tx_srfeuserregister_display');
		$this->email = t3lib_div::makeInstance('tx_srfeuserregister_email');
		$this->control = t3lib_div::makeInstance('tx_srfeuserregister_control');

		$this->lang->init($this, $this->conf, $this->config);
		$this->lang->pi_loadLL();

		$this->data->init($this, $this->conf, $this->config,$this->lang, $this->tca, $this->auth, $this->control, $this->freeCap);

		$this->control->init($this, $this->conf, $this->config, $this->display, $this->data, $this->marker, $this->auth, $this->email, $this->tca);

		$this->pi_USER_INT_obj = 1;
		$this->pi_setPiVarDefaults();
		$this->sys_language_content = t3lib_div::testInt($TSFE->config['config']['sys_language_uid']) ? intval($TSFE->config['config']['sys_language_uid']) : 0;

			// prepare for character set settings
		if ($TSFE->metaCharset) {
			$this->charset = $TSFE->csConvObj->parse_charset($TSFE->metaCharset);
		}

			// Initialise fileFunc object
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');


		if (isset($this->conf['setfixed'])) {
			$this->setfixedEnabled = $this->conf['setfixed'];
		}
	

		$this->auth->init($this, $this->conf, $this->config, $this->data->feUserData['aC']);

		$this->marker->init($this, $this->conf, $this->config, $this->data, $this->tca, $this->lang, $this->control, $this->auth, $this->freeCap);

		$this->tca->init($this, $this->conf, $this->config, $this->data, $this->control, $this->lang);
		$this->display->init($this, $this->conf, $this->config, $this->data, $this->marker, $this->tca, $this->control, $this->auth);
		$this->email->init($this, $this->conf, $this->config, $this->display, $this->data, $this->marker, $this->tca, $this->control, $this->auth);

	}	// init


	/**
	* Invokes a user process
	*
	* @param array  $mConfKey: the configuration array of the user process
	* @param array  $passVar: the array of variables to be passed to the user process
	* @return array  the updated array of passed variables
	*/
	function userProcess($mConfKey, $passVar) {
		if ($this->conf[$mConfKey]) {
			$funcConf = $this->conf[$mConfKey.'.'];
			$funcConf['parentObj'] = &$this;
			$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
		}
		return $passVar;
	}	// userProcess


	/**
	* Invokes a user process
	*
	* @param string  $confVal: the name of the process to be invoked
	* @param array  $mConfKey: the configuration array of the user process
	* @param array  $passVar: the array of variables to be passed to the user process
	* @return array  the updated array of passed variables
	*/
	function userProcess_alt($confVal, $confArr, $passVar) {
		if ($confVal) {
			$funcConf = $confArr;
			$funcConf['parentObj'] = &$this;
			$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($confVal, $funcConf, $passVar);
		}
		return $passVar;
	}	// userProcess_alt


	/**
	* Instantiate the file creation function
	*
	* @return void
	*/
/*	function createFileFuncObj() {
		if (!$this->fileFunc) {
			$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
		}
	}
*/

	/**
	* Check what bit is set and returns the bitnumber
	* @param	int	Number to check, ex: 16 returns 4, 32 returns 5, 0 returns -1, 1 returns 0
	* @ return	bool	Bitnumber, -1 for not found
	*/
/*	function _whatBit($num) {
		$num = intval($num);
		if ($num == 0) return -1;
		for ($i=0; $i<32; $i++) {
			if ($num & (1 << $i)) return $i;
		}
		return -1;
	}
*/


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']);
}
?>
