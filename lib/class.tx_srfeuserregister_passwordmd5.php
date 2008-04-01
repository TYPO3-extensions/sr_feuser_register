<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2008 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Part of the sr_feuser_register (Frontend User Registration) extension.
 *
 * md5 password functions
 *
 * $Id$
 *
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author Franz Holzinger <contact@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_passwordmd5 {
	var $data;
	var $marker;
	var $controlData;

	function init (&$marker, &$data, &$controlData)	{
		$this->marker = &$marker;
		$this->data = &$data;
		$this->controlData = &$controlData;
	}

	function generateChallenge (&$row)	{
		$time = time();
		$chal_val = md5($time.getmypid());
		$row['password'] = '';
		$row['chalvalue'] = $chal_val;

		if (t3lib_extMgm::isLoaded('kb_md5fepw'))	{
			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_kbmd5fepw_challenge', array('challenge' => $chal_val, 'tstamp' => $time));
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_passwordmd5.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_passwordmd5.php']);
}
?>
