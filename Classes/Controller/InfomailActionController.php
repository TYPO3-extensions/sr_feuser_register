<?php
namespace SJBR\SrFeuserRegister\Controller;

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

use SJBR\SrFeuserRegister\Exception;
use SJBR\SrFeuserRegister\Controller\AbstractActionController;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\Marker;
use SJBR\SrFeuserRegister\View\PlainView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Infomail action controller
 */
class InfomailActionController extends AbstractActionController
{
	/**
	 * Processes the infomail request
	 *
	 * @param array $dataArray: array of form input fields
	 * @param string $cmd: the command
	 * @param string $cmdKey: the command key
	 * @return string the template with substituted markers
	 */
	public function doProcessing(array $dataArray, $cmd, $cmdKey) {
		// If editing is enabled
		if (empty($this->conf['infomail']) || empty($this->conf['email.']['field']) || !in_array($this->conf['email.']['field'], array_keys($GLOBALS['TCA'][$this->theTable]['columns']))) {
			$errorText = LocalizationUtility::translate('internal_infomail_option', $this->extensionName);
			throw new Exception($errorText, Exception::MISCONFIGURATION);
		}
		$content = '';
		$origArray = $this->data->getOrigArray();
		$securedArray = array();
		$fetch = $this->parameters->getFeUserData('fetch');
		if (!empty($fetch)) {
			$dataArray['email'] = $fetch;
		}
		$origArray = $this->data->parseIncomingData($origArray, false);
		if (!empty($fetch) || $this->parameters->getFeUserData('submit')) {
			$evalErrors = $this->data->evalValues($dataArray, $origArray, $this->marker, $cmdKey);
		}
		$failure = $this->data->getFailure();
		if (!empty($fetch) && empty($failure)) {
			$pidLock = 'AND pid IN (\'' . implode('\',\'', GeneralUtility::trimExplode(',', $this->parameters->getPid(), true)) . '\')';
			$enable = $GLOBALS['TSFE']->sys_page->enableFields($this->theTable);
			$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($this->theTable, $this->conf['email.']['field'], $fetch, $pidLock . $enable, '', '', '100');
			if (is_array($DBrows)) {
				$recipient = $DBrows[0][$this->conf['email.']['field']];
				$this->data->setDataArray($DBrows[0]);
				$this->email->compile('INFOMAIL', $DBrows, $DBrows, $securedArray, trim($recipient), $cmd, $cmdKey);
			} else if (GeneralUtility::validEmail($fetch)) {
				$fetchArray = array( '0' => array('email' => $fetch));
				$this->email->compile('INFOMAIL_NORECORD', $fetchArray, $fetchArray, $securedArray, $fetch, $cmd, $cmdKey);
			}
			$subpartKey = '###TEMPLATE_' . Marker::INFOMAIL_PREFIX . 'SENT###';
			$this->marker->addGeneralHiddenFieldsMarkers($cmd, $this->parameters->getAuthCode(), $this->parameters->getBackURL());
			$plainView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
			$content = $plainView->render($subpartKey, is_array($DBrows) ? $DBrows[0] : (is_array($fetchArray) ? $fetchArray[0] : array()), $origArray, $securedArray, $cmd, $cmdKey);
		} else {
			$subpartKey = '###TEMPLATE_INFOMAIL###';
			$markerArray['###FIELD_email###'] = empty($fetch) ? '' : htmlspecialchars($fetch);
			$this->marker->addGeneralHiddenFieldsMarkers($cmd, $this->parameters->getAuthCode(), $this->parameters->getBackURL());
			$plainView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
			$content.= $plainView->render($subpartKey, array(), $origArray, $securedArray, $cmd, $cmdKey);
		}
		if ($this->parameters->getValidRegHash()) {
			$regHash = $this->parameters->getRegHash();
			HashUtility::deleteHash($regHash);
		}
		return $content;
	}
}