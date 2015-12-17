<?php
namespace SJBR\SrFeuserRegister\Controller;

/*
 *  Copyright notice
 *
 *  (c) 2007-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete action controller
 */

class DeleteActionController extends AbstractActionController
{
	/**
	 * Processes the delete request
	 *
	 * @param array $dataArray: array of form input fields
	 * @param string $cmd: the command
	 * @param string $cmdKey: the command key
	 * @return string the template with substituted markers
	 */
	public function doProcessing(array $dataArray, $cmd, $cmdKey) {
		// If deleting is enabled
		if (empty($this->conf['delete'])) {
			$errorText = LocalizationUtility::translate('internal_delete_option', $this->extensionName);
			throw new Exception($errorText, Exception::MISCONFIGURATION);
		}
		$content = '';
		$origArray = $this->data->getOrigArray();
		$securedArray = $this->theTable === 'fe_users' ? SessionData::readSecuredArray($this->extensionKey) : array();

		$mode = AbstractView::MODE_NORMAL;
		if ($this->conf[$cmdKey . '.']['preview'] && (int) $this->parameters->getFeUserData('preview')) {
			$mode = AbstractView::MODE_PREVIEW;
		}
		$isSubmit = $this->parameters->getFeUserData('submit');
		$isSubmit = !empty($isSubmit) && $this->parameters->isTokenValid();		
		$isDoNotSave = $this->parameters->getFeUserData('doNotSave');
		$isDoNotSave = !empty($isDoNotSave);
		if ($isDoNotSave) {
			SessionData::clearSessionData($this->extensionKey);
		}
		// If data is submitted, we take care of it here.
		if ($mode === AbstractView::MODE_NORMAL && $isSubmit && !$isDoNotSave) {
			$this->data->deleteRecord($origArray, $dataArray);
			if ($this->data->getSaved()) {
				$key =  'DELETE' . Marker::SAVED_SUFFIX;
				$afterSaveView = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\AfterSaveView', $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
				$content = $afterSaveView->render($dataArray, $origArray, $securedArray, $cmd, $cmdKey, $key);
				if ($this->conf['email.']['DELETE_SAVED']) {
					$this->emailField = $this->conf['email.']['field'];
					$recipient = $dataArray[$this->emailField] ?: $origArray[$this->emailField];
					// Send email message(s)
					$this->email->compile(
						$key,
						array($dataArray),
						array($origArray),
						$securedArray,
						$recipient,
						$cmd,
						$cmdKey
					);
				}
			} else if ($this->data->getError()) {
				// If there was an error, we return an error message
				$errorView = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\PlainView', $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
				$content = $errorView->render($this->data->getError(), $finalDataArray, $this->data->getOrigArray(), $securedArray, $cmd, $cmdKey);
			} 
		} else {
			// That is either preview or initial form
			$deleteView = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\DeleteView', $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
			$content .= $deleteView->render($dataArray, $origArray, $securedArray, $cmd, $cmdKey);
		}
		if ($this->parameters->getValidRegHash() && $mode === AbstractView::MODE_NORMAL) {
			$regHash = $this->parameters->getRegHash();
			HashUtility::deleteHash($regHash);
		}
		return $content;
	}
}