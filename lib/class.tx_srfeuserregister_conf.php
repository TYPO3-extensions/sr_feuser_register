<?php

/*
 *  Copyright notice
 *
 *  (c) 2008-2011 Franz Holzinger <franz@ttproducts.de>
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

/**
 * Setup configuration functions
 */
class tx_srfeuserregister_conf {
	public $conf = array();
	public $config = array();

	public function init ($conf) {
		$this->conf = $conf;
		$this->config = array();
	}

	public function setConf (array $dataArray, $k = '') {
		if ($k) {
			$this->conf[$k] = $dataArray;
		} else {
			$this->conf = $dataArray;
		}
	}

	public function getConf () {
		return $this->conf;
	}

	public function setConfig (array $dataArray, $k = '') {
		if ($k) {
			$this->config[$k] = $dataArray;
		} else {
			$this->config = $dataArray;
		}
	}

	public function getConfig () {
		return $this->config;
	}
}