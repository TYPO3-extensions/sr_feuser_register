<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2008 Stanislas Rolland (stanislas.rolland@sjbr.ca)
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
 * Part of the sr_feuser_register (Front End User Registration) extension.
 *
 * md5 password functions
 *
 * $Id$
 *
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */


class tx_srfeuserregister_passwordmd5 {
	public $data;
	public $marker;
	public $controlData;
	public $chal_val;

	public function init (&$marker, &$data, &$controlData)	{
		$this->marker = &$marker;
		$this->data = &$data;
		$this->controlData = &$controlData;
	}

	public function getChallenge ()	{
		return $this->chal_val;
	}

	public function generateChallenge (&$row)	{
		global $TYPO3_DB;

		$time = time();
		$this->chal_val = md5($time.getmypid());
		$row['password'] = '';
		$row['chalvalue'] = $this->chal_val;
		$tableArray = $TYPO3_DB->admin_get_tables();

		if (t3lib_extMgm::isLoaded('kb_md5fepw') && isset($tableArray['tx_kbmd5fepw_challenge']))	{
			$challengeTable = 'tx_kbmd5fepw_challenge';
		}

		if (t3lib_extMgm::isLoaded('felogin') && isset($tableArray['tx_felogin_challenge']) && $GLOBALS['TYPO3_CONF_VARS']['FE']['passwordType'] == 'md5' && $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'] == 'superchallenged')	{
			$challengeTable = 'tx_felogin_challenge';
		}

		if ($challengeTable != '')	{
			$res = $TYPO3_DB->exec_SELECTquery('count(*) as count', $challengeTable, 'challenge='.$TYPO3_DB->fullQuoteStr($this->chal_val, $challengeTable));
			if (!method_exists($TYPO3_DB,debug_check_recordset) || $TYPO3_DB->debug_check_recordset($res))	{
				$row = $TYPO3_DB->sql_fetch_assoc($res);
				$cnt = $row['count'];
				$TYPO3_DB->sql_free_result($res);
				if (!$cnt)	{
					$res = $TYPO3_DB->exec_INSERTquery($challengeTable, array('challenge' => $this->chal_val, 'tstamp' => $time));
				}
			}
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_passwordmd5.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_passwordmd5.php']);
}
?>