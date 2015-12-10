<?php
namespace SJBR\SrFeuserRegister\View;

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

use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Utility\UrlUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete view rendering
 */

class DeleteView extends AbstractView
{
	/**
	 * This is the display of delete
	 *
	 * @return string  the template with substituted markers
	 */
	public function render(array $dataArray, array $origArray, array $securedArray, $cmd, $cmdKey) {
		$aCAuth = Authentication::aCAuth($this->parameters->getAuthCode(), $origArray, $this->conf, $this->conf['setfixed.']['DELETE.']['_FIELDLIST']);
		$plainView = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\PlainView', $this->extensionKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
		if (($this->theTable === 'fe_users' && $GLOBALS['TSFE']->loginUser) || $aCAuth) {
			// Must be logged in OR be authenticated by the aC code in order to delete
			$cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
			$bMayEdit = $cObj->DBmayFEUserEdit($this->theTable, $origArray, $GLOBALS['TSFE']->fe_user->user, $this->conf['allowedGroups'], $this->conf['fe_userEditSelf']);
			if ($aCAuth || $bMayEdit) {
				// Display the form, if access granted.
				$backUrl = $this->parameters->getBackURL() ?: UrlUtility::getTypoLink_URL($this->parameters->getPid('login') . ',' . $GLOBALS['TSFE']->type);
				$this->marker->addBackUrlMarker($backUrl);
				$this->marker->addGeneralHiddenFieldsMarkers('delete', $this->parameters->getAuthCode(), $backUrl);
				$content = $plainView->render('###TEMPLATE_DELETE_PREVIEW###', $origArray, $origArray, $securedArray, $cmd, $cmdKey);
			} else {
				// Else display error, that you could not edit that particular record...
				$content = $plainView->render('###TEMPLATE_NO_PERMISSIONS###', array(), $origArray, $securedArray, $cmd, $cmdKey);
			}
		} else {
			// Finally this is if there is no login user. This must tell that you must login. Perhaps link to a page with create-user or login information.
			if ($this->theTable === 'fe_users' ) {
				$content = $plainView->render('###TEMPLATE_AUTH###', array(), $origArray, $securedArray, $cmd, $cmdKey);
			} else {
				$content = $plainView->render('###TEMPLATE_NO_PERMISSIONS###', array(), $origArray, $securedArray, $cmd, $cmdKey);
			}
		}
		return $content;
	}
}