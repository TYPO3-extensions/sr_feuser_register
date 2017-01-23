<?php
namespace SJBR\SrFeuserRegister\View;

/*
 *  Copyright notice
 *
 *  (c) 2007-2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Domain\Data;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Display functions
 */

abstract class AbstractView
{
	/**
	 * Display mode constants
	 */
	const MODE_NORMAL = 0;
	const MODE_PREVIEW = 1;

	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extensionKey;

	/**
	 *  Extension name
	 *
	 * @var string Extension name
	 */
	protected $extensionName;

	/**
	 * Prefix used for CSS classes and variables
	 *
	 * @var string
	 */
	protected $prefixId;

	/**
	 * The table in used
	 *
	 * @var string
	 */
	protected $theTable;

	/**
	 * The plugin configuration
	 *
	 * @var array
	 */
	protected $conf;

	/**
	 * The data object
	 *
	 * @var Data
	 */
	protected $ata;

	/**
	 * The request parameters object
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * The marker object
	 *
	 * @var Marker
	 */
	protected $marker;

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param Data $ata: the data object
	 * @param Parameters $parameters: the request parameters object
	 * @param Marker $marker: the marker object
	 * @return void
	 */
	public function __construct(
		$extensionKey,
		$prefixId,
		$theTable,
		array $conf,
		Data $data,	
		Parameters $parameters,
		Marker $marker
	) {
		$this->extensionKey = $extensionKey;
		$this->extensionName = GeneralUtility::underscoredToUpperCamelCase($extensionKey);
		$this->prefixId = $prefixId;
		$this->theTable = $theTable;
	 	$this->conf = $conf;
	 	$this->data = $data;
	 	$this->parameters = $parameters;
	 	$this->marker = $marker;
	}

	/**
	 * Returns a JavaScript <script> section with some function calls to JavaScript functions from "typo3/js/jsfunc.updateform.js" (which is also included by setting a reference in $GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'])
	 * The JavaScript codes simply transfers content into form fields of a form which is probably used for editing information by frontend users. Used by fe_adminLib.inc.
	 *
	 * @param array $dataArray Data array which values to load into the form fields from $formName (only field names found in $fieldList)
	 * @param string $formName The form name
	 * @param string $arrPrefix A prefix for the data array
	 * @param string $fieldList The list of fields which are loaded
	 * @return string
	 */
	protected function getUpdateJS($dataArray, $formName, $arrPrefix, $fieldList) {
		$JSPart = '';
		$updateValues = GeneralUtility::trimExplode(',', $fieldList);
		foreach ($updateValues as $fKey) {
			$value = $dataArray[$fKey];
			if (is_array($value)) {
				foreach ($value as $Nvalue) {
					$JSPart .= '
	updateForm(\'' . $formName . '\',\'' . $arrPrefix . '[' . $fKey . '][]\',' . GeneralUtility::quoteJSvalue($Nvalue, TRUE) . ');';
				}
			} else {
				$JSPart .= '
	updateForm(\'' . $formName . '\',\'' . $arrPrefix . '[' . $fKey . ']\',' . GeneralUtility::quoteJSvalue($value, TRUE) . ');';
			}
		}
		$JSPart = '<script type="text/javascript">
	/*<![CDATA[*/ ' . $JSPart . '
	/*]]>*/
</script>
';
		$GLOBALS['TSFE']->additionalHeaderData['JSincludeFormupdate'] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename(ExtensionManagementUtility::siteRelPath('sr_feuser_register')  . 'Resources/Public/JavaScript/jsfunc.updateform.js') . '"></script>';
		return $JSPart;
	}

	/**
	 * Get the use of the password field
	 *
	 * @return boolean whether password field is used
	 */
	protected function getUsePassword()
	{
		return $this->theTable === 'fe_users' && isset($this->conf['create.']['evalValues.']['password']);
	}

	/**
	 * Get the use of the password again field
	 *
	 * @return boolean whether password again field is used
	 */
	protected function getUsePasswordAgain()
	{
		return $this->getUsePassword() && GeneralUtility::inList($this->conf['create.']['evalValues.']['password'], 'twice');
	}
}