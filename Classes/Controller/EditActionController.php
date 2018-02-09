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
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\Utility\UrlUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\SrFeuserRegister\View\AfterSaveView;
use SJBR\SrFeuserRegister\View\CreateView;
use SJBR\SrFeuserRegister\View\EditView;
use SJBR\SrFeuserRegister\View\Marker;
use SJBR\SrFeuserRegister\View\PlainView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Edit action controller
 */
class EditActionController extends AbstractActionController
{
	/**
	 * Processes the edit request
	 *
	 * @param array $dataArray: array of form input fields
	 * @param string $cmd: the command
	 * @param string $cmdKey: the command key
	 * @return string the template with substituted markers
	 */
	public function doProcessing(array $dataArray, $cmd, $cmdKey) {
		// If editing is enabled
		if (empty($this->conf['edit']) || empty($this->conf['edit.'])) {
			$errorText = LocalizationUtility::translate('internal_edit_option', $this->extensionName);
			throw new Exception($errorText, Exception::MISCONFIGURATION);
		}
		$content = '';
		$origArray = $this->data->getOrigArray();
		$securedArray = $this->theTable === 'fe_users' ? SessionData::readSecuredArray($this->extensionKey) : array();

		$mode = AbstractView::MODE_NORMAL;
		if ($this->conf[$cmdKey . '.']['preview'] && (int) $this->parameters->getFeUserData('preview')) {
			$mode = AbstractView::MODE_PREVIEW;
		}
		$isSubmit = $this->parameters->getFeUserData('submit') && $this->parameters->isTokenValid();
		$isDoNotSave = $this->parameters->getFeUserData('doNotSave');
		$isDoNotSave = !empty($isDoNotSave);
		if ($isDoNotSave) {
			SessionData::clearSessionData($this->extensionKey);
		}
		if (($cmd === '' && empty($dataArray)) || $cmd === 'login') {
			// Displaying a link to edit or a no permission to edit message
			$createView = GeneralUtility::makeInstance(CreateView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
			$content = $createView->render($dataArray, $origArray, $securedArray, $cmd, $cmdKey, $mode);
		} else {
			$this->data->setName($dataArray, $cmdKey);
			$this->data->parseValues($dataArray, $origArray, $cmdKey);
			$this->data->overrideValues($dataArray, $cmdKey);
			$evalErrors = $this->data->evalValues($dataArray, $origArray, $this->marker, $cmdKey, $mode);
			// If there is an error on password, clear session data
			if (!empty($evalErrors['password'])) {
				SessionData::clearSessionData($this->extentionKey);
			}
			// No preview flag if an evaluation failure has occured
			if ($this->data->getFailure()) {
				$mode = AbstractView::MODE_NORMAL;
			}
			if (GeneralUtility::inList($this->data->getFailure(), 'username')) {
				$isDoNotSave = true;
			}
			if ($this->parameters->getFeUserData('countryChange') || $this->parameters->getFeUserData('fileDelete')) {
				// This is a country change submitted through the onchange event or a file deletion already processed by the parsing function
				$mode = AbstractView::MODE_NORMAL;
				$isDoNotSave = true;
			}
			$this->data->setUsername($dataArray, $cmdKey);
			$this->data->setDataArray($dataArray);
			if (!$this->data->getFailure() && $mode === AbstractView::MODE_NORMAL && $this->parameters->isTokenValid() && $isSubmit && !$isDoNotSave && !$this->parameters->getFeUserData('rU')) {
				$this->data->setPassword($dataArray, $cmdKey);
				$newDataArray = array();
				$theUid = $this->data->save($dataArray, $origArray, SessionData::readToken($this->extensionKey), $newDataArray, $cmd, $cmdKey, $this->parameters->getPid());
				if (!empty($newDataArray)) {
					$dataArray = $newDataArray;
				}
				if ($this->data->getSaved()) {
					SessionData::clearSessionData($this->extensionKey);
					$key = 'EDIT' . Marker::SAVED_SUFFIX;
					$afterSaveView = GeneralUtility::makeInstance(AfterSaveView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
					$content = $afterSaveView->render($dataArray, $origArray, $securedArray, $cmd, $cmdKey, $key);
					if ($this->conf['email.']['EDIT_SAVED'] || $this->conf['email.']['DELETE_SAVED'] || $this->conf['notify.']['EDIT_SAVED'] || $this->conf['notify.']['DELETE_SAVED']) {
						$this->emailField = $this->conf['email.']['field'];
						$recipient = $dataArray[$this->emailField] ?: $origArray[$this->emailField];
						// Send email message(s)
						$this->email->compile($key, array($dataArray), array($origArray), $securedArray, $recipient, $cmd, $cmdKey);
					}
					if ($this->parameters->getValidRegHash() && $mode === AbstractView::MODE_NORMAL) {
						$regHash = $this->parameters->getRegHash();
						HashUtility::deleteHash($regHash);
					}
					// Link to on edit save
					// backURL may link back to referring process
					if (
						$this->theTable === 'fe_users'
						&& ($cmd === 'edit' || $cmd === 'password')
						&& ($this->parameters->getBackURL() || ($this->conf['linkToPID'] && ($this->parameters->getFeUserData('linkToPID') || !$this->conf['linkToPIDAddButton'])))
					) {
						$destUrl = $this->parameters->getBackURL() ?: UrlUtility::getTypoLink_URL($this->conf['linkToPID'] . ',' . $GLOBALS['TSFE']->type);
						header('Location: ' . GeneralUtility::locationHeaderUrl($destUrl));
						exit;
					}
				} else if ($this->data->getError()) {
					// If there was an error, we return an error message
					$errorView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
					$content = $errorView->render($this->data->getError(), $finalDataArray, $this->data->getOrigArray(), $securedArray, $cmd, $cmdKey);
				} 
			} else {
				if ($this->theTable !== 'fe_users' && $this->conf['setfixed.']['EDIT.']['_FIELDLIST']) {
					$fD = GeneralUtility::_GP('fD');
					$fieldArr = array();
					if (is_array($fD)) {
						foreach ($fD as $field => $value) {
							$origArray[$field] = rawurldecode($value);
							$fieldArr[] = $field;
						}
					}
					$theCode = Authentication::setfixedHash($origArray, $this->conf, $origArray['_FIELDLIST']);
				}
				$origArray = $this->data->parseIncomingData($origArray);
				$aCAuth = Authentication::aCAuth($this->parameters->getAuthCode(), $origArray, $this->conf, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']);
				if (($this->theTable === 'fe_users' && $GLOBALS['TSFE']->loginUser) || $aCAuth || ($theCode && !strcmp($this->parameters->getAuthCode(), $theCode))) {
					// Must be logged in OR be authenticated by the aC code in order to edit
					// If the recUid selects a record.... (no check here)
					if (!strcmp($this->parameters->getAuthCode(), $theCode) || $aCAuth || $this->data->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf'])) {
						// Display the form, if access granted.
						$editView = GeneralUtility::makeInstance(EditView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
						$content .= $editView->render($dataArray, $origArray, $securedArray, $cmd, $cmdKey, $mode);
					} else {
						// Else display error, that you could not edit that particular record...
						$plainView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
						$content.= $plainView->render('###TEMPLATE_NO_PERMISSIONS###', $dataArray, $origArray, $securedArray, $cmd, $cmdKey);
					}
				} else {
					// This is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
					$plainView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
					$content.= $plainView->render('###TEMPLATE_AUTH###', $dataArray, $origArray, $securedArray, $cmd, $cmdKey);
				}
			}
		}
		return $content;
	}
}