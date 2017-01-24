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

use SJBR\SrFeuserRegister\Controller\AbstractActionController;
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Utility\HashUtility;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\SrFeuserRegister\View\AfterSaveView;
use SJBR\SrFeuserRegister\View\CreateView;
use SJBR\SrFeuserRegister\View\Marker;
use SJBR\SrFeuserRegister\View\PlainView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create action controller
 */
class CreateActionController extends AbstractActionController
{
	/**
	 * Processes the create request
	 *
	 * @param array $dataArray: array of form input fields
	 * @param string $cmd: the command
	 * @param string $cmdKey: the command key
	 * @return string the template with substituted markers
	 */
	public function doProcessing(array $finalDataArray, $cmd, $cmdKey) {
		$content = '';
		$origArray = $this->data->getOrigArray();
		$securedArray = $this->theTable === 'fe_users' ? SessionData::readSecuredArray($this->extensionKey) : array();

		$mode = AbstractView::MODE_NORMAL;
		if ($this->conf[$cmdKey . '.']['preview'] && (int) $this->parameters->getFeUserData('preview')) {
			$mode = AbstractView::MODE_PREVIEW;
		}
		$isSubmit = $this->parameters->getFeUserData('submit');
		$isSubmit = !empty($finalDataArray) && $this->parameters->isTokenValid();
		$isDoNotSave = $this->parameters->getFeUserData('doNotSave');
		$isDoNotSave = !empty($isDoNotSave);
		if ($isDoNotSave) {
			SessionData::clearSessionData($this->extensionKey);
		}

		if (empty($finalDataArray)) {
			// Displaying an empty form
			$finalDataArray = $this->data->defaultValues($cmdKey);
			$this->marker->setNoError($cmdKey);
			$createView = GeneralUtility::makeInstance(CreateView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
			$content = $createView->render($finalDataArray, $origArray, $securedArray, $cmd, $cmdKey, $mode);
		} else {
			$this->data->setName($finalDataArray, $cmdKey);
			$this->data->parseValues($finalDataArray, $origArray, $cmdKey);
			$this->data->overrideValues($finalDataArray, $cmdKey);
			$evalErrors = $this->data->evalValues($finalDataArray, $origArray, $this->marker, $cmdKey);
			// If the two password fields are not equal, clear session data
			if (is_array($evalErrors['password']) && in_array('twice', $evalErrors['password'])) {
				SessionData::clearSessionData($this->extensionKey);
			}
			// No preview flag if a evaluation failure has occured
			if ($this->data->getFailure()) {
				$mode = AbstractView::MODE_NORMAL;
			}
			if (GeneralUtility::inList($this->data->getFailure(), 'username')) {
				$isDoNotSave = true;
			}
			if ($this->parameters->getFeUserData('countryChange') || $this->parameters->getFeUserData('fileDelete')) {
				// This is either a country change submitted through the onchange event or a file deletion already processed by the parsing function
				// We are going to redisplay
				$mode = AbstractView::MODE_NORMAL;
				$isDoNotSave = true;
			}
			$this->data->setUsername($finalDataArray, $cmdKey);
			$this->data->setDataArray($finalDataArray);
			if (!$this->data->getFailure() && $mode === AbstractView::MODE_NORMAL && $isSubmit && !$isDoNotSave) {
				$this->data->setPassword($finalDataArray, $cmdKey);
				$newDataArray = array();
				$theUid = $this->data->save($finalDataArray, $origArray, SessionData::readToken($this->extensionKey), $newDataArray, $cmd, $cmdKey, $this->parameters->getPid());
				if (!empty($newDataArray)) {
					$dataArray = $newDataArray;
					$dataArray['auto_login_key'] = $finalDataArray['auto_login_key'];
				}
				if ($this->data->getSaved()) {
					// If auto login on create
					if ($this->theTable === 'fe_users' && $cmdKey === 'create' && !$this->parameters->getSetfixedEnabled() && $this->conf['enableAutoLoginOnCreate']) {
							// keep the session for the following auto login
					} else {
							SessionData::clearSessionData($this->extensionKey);
					}
					$isCustomerConfirmsMode = false;
					if ($this->conf['enableAdminReview'] && ($this->conf['enableEmailConfirmation'] || $this->conf['infomail'])) {
						$isCustomerConfirmsMode = true;
					}
					if ($cmd === 'invite') {
						$key = Marker::SETFIXED_PREFIX . 'INVITE';
					} else if ($this->parameters->getSetfixedEnabled()) {
						// This is the case where the user or admin has to confirm
						$key = Marker::SETFIXED_PREFIX . 'CREATE';
						if ($this->theTable === 'fe_users' && !$this->conf['enableEmailConfirmation'] && $this->conf['enableAdminReview']) {
							// This is the case where the user does not have to confirm, but has to wait for admin review
							$key = 'CREATE' . Marker::SAVED_SUFFIX . '_REVIEW';
						} else if ($isCustomerConfirmsMode) {
							// This is the case where both have to confirm
							$key .= '_REVIEW';
						}
					} else {
						$key = 'CREATE' . Marker::SAVED_SUFFIX;
					}
					$afterSaveView = GeneralUtility::makeInstance(AfterSaveView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
					$content = $afterSaveView->render($finalDataArray, $origArray, $securedArray, $cmd, $cmdKey, $key);
					if ($this->conf['enableAdminReview'] && !$isCustomerConfirmsMode) {
						// Send admin the confirmation email
						// The user will not confirm in this mode
						$this->email->compile(
							Marker::SETFIXED_PREFIX . 'REVIEW',
							array($dataArray),
							array($origArray),
							$securedArray,
							$this->conf['email.']['admin'],
							'setfixed',
							$cmdKey
						);
					} else {
						$this->emailField = $this->conf['email.']['field'];
						$recipient = $finalDataArray[$this->emailField] ?: $origArray[$this->emailField];
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
					// Auto login on create
					if ($this->theTable === 'fe_users' && $cmd === 'create' && !$this->parameters->getSetfixedEnabled() && $this->conf['enableAutoLoginOnCreate']) {
						$password = SessionData::readPassword($this->extensionKey);
						$loginSuccess = $this->login($dataArray['username'], $password['password']);
						if ($loginSuccess) {
							// Login was successful
							exit;
						} else {
							// Login failed... should not happen...
							// If it does, a login form will be displayed as if auto login was not configured
							$content = '';
						}
					}
				} else if ($this->data->getError()) {
					// If there was an error, we return an error message
					$errorView = GeneralUtility::makeInstance(PlainView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
					$content = $errorView->render($this->data->getError(), $finalDataArray, $origArray, $securedArray, $cmd, $cmdKey);
				}
			} else {
				// There has been no attempt to save.
				// That is either preview or displaying a not correctly filled form
				$createView = GeneralUtility::makeInstance(CreateView::class, $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
				$content = $createView->render($finalDataArray, $origArray, $securedArray, $cmd === 'invite' ? $cmd : 'create', $cmdKey, $mode);
			}
		}
		if ($this->parameters->getValidRegHash() && $mode === AbstractView::MODE_NORMAL) {
			$regHash = $this->parameters->getRegHash();
			HashUtility::deleteHash($regHash);
		}
		return $content;
	}
}