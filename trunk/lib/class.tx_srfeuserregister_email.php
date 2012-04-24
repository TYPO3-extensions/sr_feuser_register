<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2012 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
 * email functions
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author	Stanislas Rolland <typo3(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

class tx_srfeuserregister_email {
	public $langObj;
	public $conf = array();
	public $config = array();
	public $display;
	public $data;
	public $marker;
	public $tca;
	public $control;
	public $controlData;
	public $infomailPrefix = 'INFOMAIL_';
	public $emailMarkPrefix = 'EMAIL_TEMPLATE_';
	public $emailMarkAdminSuffix = '_ADMIN';
	public $emailMarkHTMLSuffix = '_HTML';
	public $HTMLMailEnabled = TRUE;
	public $cObj;


	public function init (
		&$langObj,
		&$cObj,
		&$conf,
		&$config,
		&$display,
		&$data,
		&$marker,
		&$tca,
		&$controlData,
		&$setfixedObj
	)	{
		$this->langObj = &$langObj;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->display = &$display;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->tca = &$tca;
		$this->controlData = &$controlData;
		$this->setfixedObj = &$setfixedObj;
		$this->cObj = &$cObj;
		$enablestring = $GLOBALS['TSFE']->sys_page->enableFields('fe_users');

		if (isset($this->conf['email.']['HTMLMail'])) {
			$this->HTMLMailEnabled = $this->conf['email.']['HTMLMail'];
		}
	}


	/**
	 * Sends info mail to subscriber or displays a screen to update or delete the membership
	 *
	 * @param array  Array with key/values being marker-strings/substitution values.
	 * @return	string		HTML content message
	 * @see init(),compile(), send()
	 */
	public function sendInfo (
		$theTable,
		$origArr,
		$securedArray,
		&$markerArray,
		$cmd,
		$cmdKey,
		$templateCode,
		$failure = ''
	)	{
		if ($this->conf['infomail'] && $this->conf['email.']['field']) {
			$fetch = $this->controlData->getFeUserData('fetch');

			if (isset($fetch) && !empty($fetch) && !$failure) {
				$pidLock = 'AND pid IN (' . ($this->cObj->data['pages'] ? $this->cObj->data['pages'] . ',' : '') . $this->controlData->getPid() . ')';
				$enable = $GLOBALS['TSFE']->sys_page->enableFields($theTable);
					// Getting records
					// $this->conf['email.']['field'] must be a valid field in the table!
				$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField(
					$theTable,
					$this->conf['email.']['field'],
					$fetch,
					$pidLock.$enable,
					'',
					'',
					'100'
				);
					// Processing records
				if (is_array($DBrows))	{
					$recipient = $DBrows[0][$this->conf['email.']['field']];
					$this->data->setDataArray($DBrows[0]);
					$errorContent = $this->compile(
						'INFOMAIL',
						$theTable,
						$DBrows,
						$DBrows,
						$securedArray,
						trim($recipient),
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode,
						$this->data->inError,
						$this->conf['setfixed.']
					);
				} elseif (t3lib_div::validEmail($fetch)) {
					$fetchArray = array( '0' => array('email' => $fetch));
					$errorContent = $this->compile(
						'INFOMAIL_NORECORD',
						$theTable,
						$fetchArray,
						$fetchArray,
						$securedArray,
						$fetch,
						$markerArray,
						$cmd,
						$cmdKey,
						$templateCode,
						$this->data->inError,
						array()
					);
				}

				if ($errorContent)	{
					$content = $errorContent;
				} else {
					$subpartkey = '###TEMPLATE_' . $this->infomailPrefix . 'SENT###';
					$content =
						$this->display->getPlainTemplate(
							$templateCode,
							$subpartkey,
							$markerArray,
							$origArr,
							(is_array($DBrows) ? $DBrows[0] : (is_array($fetchArray) ? $fetchArray[0] : '')),
							$securedArray,
							FALSE
						);

					if (!$content)	{ // compatibility until 1.1.2010
						$subpartkey = '###' . $this->emailMarkPrefix . $this->infomailPrefix . 'SENT###';
						$content =
							$this->display->getPlainTemplate(
								$templateCode,
								$subpartkey,
								$markerArray,
								$origArr,
								(is_array($DBrows) ? $DBrows[0] : (is_array($fetchArray) ? $fetchArray[0] : '')),
								$securedArray
							);
					}
				}
			} else {
				$subpartkey = '###TEMPLATE_INFOMAIL###';
				if (isset($fetch) && !empty($fetch)) {
					$markerArray['###FIELD_email###'] = htmlspecialchars($fetch);
				} else {
					$markerArray['###FIELD_email###'] = '';
				}
				$content = $this->display->getPlainTemplate(
					$templateCode,
					$subpartkey,
					$markerArray,
					$origArr,
					'',
					$securedArray
				);
			}
		} else {
			$content = $this->langObj->getLL('internal_infomail_configuration');
		}
		return $content;
	}


	/**
	* Prepares an email message
	*
	* @param string  $key: template key
	* @param array  $DBrows: invoked with just one row of fe_users
	* @param string  $recipient: an email or the id of a front user
	* @param array  Array with key/values being marker-strings/substitution values.
	* @param array  $errorFieldArray: array of field with errors (former $this->data->inError[$theField])
	* @param array  $setFixedConfig: a setfixed TS config array
	* @return string : text in case of error
	*/
	public function compile (
		$key,
		$theTable,
		$DBrows,
		$origRows,
		$securedArray,
		$recipient,
		&$markerArray,
		$cmd,
		$cmdKey,
		$templateCode,
		$errorFieldArray,
		$setFixedConfig = array()
	) {
		$missingSubpartArray = array();
		$userSubpartsFound = 0;
		$adminSubpartsFound = 0;

		if (!isset($DBrows[0]) || !is_array($DBrows[0])) {
			$DBrows = $origRows;
		}

		$authObj = &t3lib_div::getUserObj('&tx_srfeuserregister_auth');
		$bHTMLallowed = $DBrows[0]['module_sys_dmail_html'];

			// Setting CSS style markers if required
		if ($this->HTMLMailEnabled) {
			$this->addCSSStyleMarkers($markerArray);
		}

		$viewOnly = TRUE;
		$content = array(
			'user' => array(),
			'userhtml' => array(),
			'admin' => array(),
			'adminhtml' => array(),
			'mail' => array()
		);
		$content['mail'] = '';
		$content['user']['all'] = '';
		$content['userhtml']['all'] = '';
		$content['admin']['all'] = '';
		$content['adminhtml']['all'] = '';
		$setfixedArray =
			array(
				'SETFIXED_CREATE',
				'SETFIXED_CREATE_REVIEW',
				'SETFIXED_INVITE',
				'SETFIXED_REVIEW'
			);
		$infomailArray = array('INFOMAIL', 'INFOMAIL_NORECORD');

		if (
				// Silently refuse to not send infomail to non-subscriber, if so requested
			(isset($this->conf['email.'][$key]) && $this->conf['email.'][$key] != '0') ||
			($this->conf['enableEmailConfirmation'] && in_array($key, $setfixedArray)) ||
			(
				$this->conf['infomail'] &&
				in_array($key, $infomailArray) &&
					// Silently refuse to not send infomail to non-subscriber, if so requested
				!($key === 'INFOMAIL_NORECORD' && $this->conf['email.'][$key] == '0')
			)
		) {
			$subpartMarker = '###' . $this->emailMarkPrefix . $key . '###';
			$content['user']['all'] = trim($this->cObj->getSubpart($templateCode,  $subpartMarker));

			if ($content['user']['all'] == '')	{
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['user']['all'] = $this->display->removeRequired($content['user']['all'], $errorFieldArray);
				$userSubpartsFound++;
			}

			if ($this->HTMLMailEnabled && $bHTMLallowed) {
				$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkHTMLSuffix . '###';
				$content['userhtml']['all'] = trim($this->cObj->getSubpart($templateCode,  $subpartMarker));

				if ($content['userhtml']['all'] == '') {
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['userhtml']['all'] = $this->display->removeRequired($content['userhtml']['all'], $errorFieldArray);
					$userSubpartsFound++;
				}
			}
		}

		if (!isset($this->conf['notify.'][$key]) || $this->conf['notify.'][$key]) {

			$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . '###';
			$content['admin']['all'] = trim($this->cObj->getSubpart($templateCode,  $subpartMarker));

			if ($content['admin']['all'] == '') {
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['admin']['all'] = $this->display->removeRequired($content['admin']['all'], $errorFieldArray);
				$adminSubpartsFound++;
			}

			if ($this->HTMLMailEnabled)	{
				$subpartMarker =  '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . $this->emailMarkHTMLSuffix . '###';
				$content['adminhtml']['all'] = trim($this->cObj->getSubpart($templateCode, $subpartMarker));

				if ($content['adminhtml']['all'] == '')	{
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['adminhtml']['all'] = $this->display->removeRequired($content['adminhtml']['all'], $errorFieldArray);
					$adminSubpartsFound++;
				}
			}
		}

		$contentIndexArray = array();
		$contentIndexArray['text'] = array();
		$contentIndexArray['html'] = array();

		if ($content['user']['all']) {
			$content['user']['rec'] = $this->cObj->getSubpart($content['user']['all'],  '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'user';
		}
		if ($content['userhtml']['all']) {
			$content['userhtml']['rec'] = $this->cObj->getSubpart($content['userhtml']['all'],  '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'userhtml';
		}
		if ($content['admin']['all']) {
			$content['admin']['rec'] = $this->cObj->getSubpart($content['admin']['all'],  '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'admin';
		}
		if ($content['adminhtml']['all']) {
			$content['adminhtml']['rec'] = $this->cObj->getSubpart($content['adminhtml']['all'],  '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'adminhtml';
		}
		$bChangesOnly = ($this->conf['email.']['EDIT_SAVED'] == '2' && $cmd == 'edit');
		if ($bChangesOnly) {
			$keepFields = array('uid', 'pid', 'tstamp', 'name', 'username');
		} else {
			$keepFields = array();
		}
		$markerArray =
			$this->marker->fillInMarkerArray(
				$markerArray,
				$DBrows[0],
				$securedArray,
				'',
				FALSE
			);
		$this->marker->addLabelMarkers(
			$markerArray,
			$theTable,
			$DBrows[0],
			$origRows[0],
			$securedArray,
			$keepFields,
			$this->controlData->getRequiredArray(),
			$this->data->getFieldList(),
			$this->tca->TCA['columns'],
			$bChangesOnly
		);
		$content['user']['all'] = $this->cObj->substituteMarkerArray($content['user']['all'], $markerArray);
		$content['userhtml']['all'] = $this->cObj->substituteMarkerArray($content['userhtml']['all'], $markerArray);
		$content['admin']['all'] = $this->cObj->substituteMarkerArray($content['admin']['all'], $markerArray);
		$content['adminhtml']['all'] = $this->cObj->substituteMarkerArray($content['adminhtml']['all'], $markerArray);

		foreach ($DBrows as $k => $row) {
			$origRow = $origRows[$k];

			if (isset($origRow) && is_array($origRow)) {
				if (isset($row) && is_array($row)) {
					$currentRow = array_merge($origRow, $row);
				} else {
					$currentRow = $origRow;
				}
			} else {
				$currentRow = $row;
			}
			if ($bChangesOnly) {
				$mrow = array();
				foreach ($row as $field => $v) {
					if (in_array($field, $keepFields)) {
						$mrow[$field] = $row[$field];
					} else {
						if ($row[$field] != $origRow[$field]) {
							$mrow[$field] = $row[$field];
						} else {
							$mrow[$field] = ''; // needed to empty the ###FIELD_...### markers
						}
					}
				}
			} else {
				$mrow = $currentRow;
			}

			$markerArray['###SYS_AUTHCODE###'] = $authObj->authCode($row);

			$this->setfixedObj->computeUrl(
				$cmdKey,
				$markerArray,
				$setFixedConfig,
				$currentRow,
				$theTable
			);
			$this->marker->addStaticInfoMarkers($markerArray, $row, $viewOnly);

			foreach ($this->tca->TCA['columns'] as $theField => $fieldConfig) {

				if (
					$fieldConfig['config']['internal_type'] == 'file' &&
					$fieldConfig['config']['allowed'] != '' &&
					$fieldConfig['config']['uploadfolder'] != ''
				) {
					$this->marker->addFileUploadMarkers(
						$theTable,
						$theField,
						$fieldConfig,
						$markerArray,
						$cmd,
						$cmdKey,
						$row,
						$viewOnly,
						'email',
						($emailType == 'html')
					);
				}
			}

			$this->marker->addLabelMarkers(
				$markerArray,
				$theTable,
				$row,
				$origRow,
				$securedArray,
				$keepFields,
				$this->controlData->getRequiredArray(),
				$this->data->getFieldList(),
				$this->tca->TCA['columns'],
				$bChangesOnly
			);

			foreach ($contentIndexArray as $emailType => $indexArray) {
				$fieldMarkerArray = array();
				$fieldMarkerArray = $this->marker->fillInMarkerArray(
					$fieldMarkerArray,
					$mrow,
					$securedArray,
					'',
					FALSE,
					'FIELD_',
					($emailType == 'html')
				);
				$this->tca->addTcaMarkers(
					$fieldMarkerArray,
					$row,
					$origRow,
					$cmd,
					$cmdKey,
					$theTable,
					$viewOnly,
					'email',
					$bChangesOnly,
					($emailType == 'html')
				);
				$markerArray = array_merge($markerArray, $fieldMarkerArray);

				foreach ($indexArray as $index)	{
					$content[$index]['rec'] =
						$this->marker->removeStaticInfoSubparts(
							$content[$index]['rec'],
							$markerArray,
							$viewOnly
						);

					$content[$index]['accum'] .=
						$this->cObj->substituteMarkerArray(
							$content[$index]['rec'],
							$markerArray
						);
					if ($emailType === 'text') {
						$content[$index]['accum'] = htmlSpecialChars_decode($content[$index]['accum'], ENT_QUOTES);
					}
				}
			}
		}

			// Substitute the markers and eliminate HTML markup from plain text versions, but preserve <http://...> constructs
		if ($content['user']['all']) {
			$content['user']['final'] = $this->cObj->substituteSubpart($content['user']['all'], '###SUB_RECORD###', $content['user']['accum']);
			$content['user']['final'] = $this->display->removeHTMLComments($content['user']['final']);
			$content['user']['final'] = $this->display->replaceHTMLBr($content['user']['final']);
			$content['user']['final'] = str_replace('<http', '###http', $content['user']['final']);
			$content['user']['final'] = strip_tags($content['user']['final']);
			$content['user']['final'] = str_replace('###http', '<http', $content['user']['final']);
				// Remove erroneous \n from locallang file
			$content['user']['final'] = str_replace('\n', '', $content['user']['final']);
				// Remove surfluous LF's
			$content['user']['final'] = preg_replace('/[' . preg_quote(LF) . ']{3,}/', LF . LF, $content['user']['final']);
		}

		if ($content['userhtml']['all']) {
			$content['userhtml']['final'] =
				$this->cObj->substituteSubpart(
					$content['userhtml']['all'],
					'###SUB_RECORD###',
					tx_div2007_alpha::wrapInBaseClass_fh001(
						$content['userhtml']['accum'],
						$this->controlData->getPrefixId(),
						$this->controlData->getExtKey()
					)
				);
				// Remove HTML comments
			$content['userhtml']['final'] = $this->display->removeHTMLComments($content['userhtml']['final']);
				// Remove erroneous \n from locallang file
			$content['userhtml']['final'] = str_replace('\n', '', $content['userhtml']['final']);
		}

		if ($content['admin']['all']) {
			$content['admin']['final'] = $this->cObj->substituteSubpart($content['admin']['all'], '###SUB_RECORD###', $content['admin']['accum']);
			$content['admin']['final'] = $this->display->removeHTMLComments($content['admin']['final']);
			$content['admin']['final'] = $this->display->replaceHTMLBr($content['admin']['final']);
			$content['admin']['final'] = str_replace('<http', '###http', $content['admin']['final']);
			$content['admin']['final'] = strip_tags($content['admin']['final']);
			$content['admin']['final'] = str_replace('###http', '<http', $content['admin']['final']);
				// Remove erroneous \n from locallang file
			$content['admin']['final'] = str_replace('\n', '', $content['admin']['final']);
				// Remove surfluous LF's
			$content['admin']['final'] = preg_replace('/[' . preg_quote(LF) . ']{3,}/', LF . LF, $content['admin']['final']);
		}

		if ($content['adminhtml']['all']) {
			$content['adminhtml']['final'] =
				$this->cObj->substituteSubpart(
					$content['adminhtml']['all'],
					'###SUB_RECORD###',
					tx_div2007_alpha::wrapInBaseClass_fh001(
						$content['adminhtml']['accum'],
						$this->controlData->getPrefixId(),
						$this->controlData->getExtKey()
					)
				);
				// Remove HTML comments
			$content['adminhtml']['final'] = $this->display->removeHTMLComments($content['adminhtml']['final']);
				// Remove erroneous \n from locallang file
			$content['adminhtml']['final'] = str_replace('\n', '', $content['adminhtml']['final']);
		}

		$bRecipientIsInt = (
			class_exists('t3lib_utility_Math') ?
				t3lib_utility_Math::canBeInterpretedAsInteger($recipient) :
				t3lib_div::testInt($recipient)
		);
		if ($bRecipientIsInt) {
			$fe_userRec = $GLOBALS['TSFE']->sys_page->getRawRecord('fe_users', $recipient);
			$recipient = $fe_userRec['email'];
		}

			// Check if we need to add an attachment
		if ($this->conf['addAttachment'] && $this->conf['addAttachment.']['cmd'] == $cmd && $this->conf['addAttachment.']['sFK'] == $this->controlData->getFeUserData('sFK')) {
			$file = ($this->conf['addAttachment.']['file'] ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['addAttachment.']['file']) : '');
		}
			// SETFIXED_REVIEW will be sent to user only id the admin part is present
		if (
			($userSubpartsFound + $adminSubpartsFound >= 1) &&
			($adminSubpartsFound >= 1 || $key !== 'SETFIXED_REVIEW')
		) {
			$this->send(
				$recipient,
				$this->conf['email.']['admin'],
				$content['user']['final'],
				$content['userhtml']['final'],
				$content['admin']['final'],
				$content['adminhtml']['final'],
				$file
			);
		} else if ($this->conf['notify.'][$key]) {
			$errorText = $this->langObj->getLL('internal_no_subtemplate');
			$content = sprintf($errorText, $missingSubpartArray['0']);
			return $content;
		}
	} // compile


	/**
	* Dispatches the email messsage
	*
	* @param string  $recipient: email address
	* @param string  $admin: email address
	* @param string  $content: plain content for the recipient
	* @param string  $HTMLcontent: HTML content for the recipient
	* @param string  $adminContent: plain content for admin
	* @param string  $adminContentHTML: HTML content for admin
	* @param string  $fileAttachment: file name
	* @return void
	*/
	public function send (
		$recipient,
		$admin,
		$content = '',
		$contentHTML = '',
		$adminContent = '',
		$adminContentHTML = '',
		$fileAttachment = ''
	) {

		// Send mail to admin
		if ($admin && ($adminContent != '' || $adminContentHTML != '')) {

			if (isset($this->conf['email.']['replyTo']))	{
				if ($this->conf['email.']['replyTo'] == 'user')	{
					$replyTo = $recipient;
				} else {
					$replyTo = $this->conf['email.']['replyTo'];
				}
			}

			// Send mail to the admin
			$this->sendHTML(
				$adminContentHTML,
				$adminContent,
				$admin,
				'',
				$this->conf['email.']['from'],
				$this->conf['email.']['fromName'],
				$replyTo,
				''
			);
		}

		// Send mail to user
		if ($recipient && ($content != '' || $contentHTML != '')) {
			// Send mail to the front end user
			$this->sendHTML(
				$contentHTML,
				$content,
				$recipient,
				'',
				$this->conf['email.']['from'],
				$this->conf['email.']['fromName'],
				$this->conf['email.']['replyToAdmin'] ? $this->conf['email.']['replyToAdmin'] : '',
				$fileAttachment
			);
		}
	}


	/**
	* Adds CSS styles marker to a marker array for substitution in an HTML email message
	*
	* @param array  $markerArray: the input marker array
	* @return void
	*/
	public function addCSSStyleMarkers (&$markerArray) {
		$markerArray['###CSS_STYLES###'] = '	/*<![CDATA[*/
';
		$markerArray['###CSS_STYLES###'] .= $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
		$markerArray['###CSS_STYLES###'] .= '
/*]]>*/';
		return $markerArray;
	}	// addCSSStyleMarkers

	/**
	* Invokes the HTML mailing class
	*
	* @param string  $content['HTML']: HTML version of the message
	* @param string  $PLAINContent: plain version of the message
	* @param string  $recipient: email address
	* @param string  $dummy: ''
	* @param string  $fromEmail: email address
	* @param string  $fromName: name
	* @param string  $replyTo: email address
	* @param string  $fileAttachment: file name
	* @return void
	*/
	public function sendHTML (
		$HTMLContent,
		$PLAINContent,
		$recipient,
		$dummy,
		$fromEmail,
		$fromName,
		$replyTo = '',
		$fileAttachment = ''
	) {
		if (
			trim($recipient) &&
			(trim($HTMLContent) || trim($PLAINContent))
		) {
			$typo3Version = class_exists('t3lib_utility_VersionNumber') ? t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) : t3lib_div::int_from_ver(TYPO3_version);
			if (
				$typo3Version >= 4007000 ||
				(
					$typo3Version >= 4005000 &&
					is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/utility/class.t3lib_utility_mail.php']) &&
					isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/utility/class.t3lib_utility_mail.php']['substituteMailDelivery']) &&
					is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/utility/class.t3lib_utility_mail.php']['substituteMailDelivery']) &&
					array_search('t3lib_mail_SwiftMailerAdapter', $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/utility/class.t3lib_utility_mail.php']['substituteMailDelivery']) !== FALSE
				)
			) {
				$fromName = str_replace('"', '\'', $fromName);
				if (preg_match('#[/\(\)\\<>,;:@\.\]\[\s]#', $fromName)) {
					$fromName = '"' . $fromName . '"';
				}
				$defaultSubject = 'Front end user registration message';
				if ($HTMLContent) {
					$parts = preg_split('/<title>|<\\/title>/i', $HTMLContent, 3);
					$subject = trim($parts[1]) ? strip_tags(trim($parts[1])) : $defaultSubject;
				} else {
						// First line is subject
					$parts = explode(chr(10), $PLAINContent, 2);
					$subject = trim($parts[0]) ? trim($parts[0]) : $defaultSubject;
					$PLAINContent = trim($parts[1]);
				}

				$mail = t3lib_div::makeInstance('t3lib_mail_Message');
				$mail->setSubject($subject);
				$mail->setFrom(array($fromEmail => $fromName));
				$mail->setSender($fromEmail);
				$mail->setReturnPath($fromEmail);
				$mail->setReplyTo($replyTo ? array($replyTo => '') : array($fromEmail => $fromName));
				$mail->setPriority(3);
	
					// ATTACHMENT
				if ($fileAttachment && file_exists($fileAttachment)) {
					$mail->attach(Swift_Attachment::fromPath($fileAttachment));
				}
					// HTML
				if (trim($HTMLContent)) {
					$HTMLContent = $this->embedMedia($mail, $HTMLContent);
					$mail->setBody($HTMLContent, 'text/html');
				}
					// PLAIN
				$mail->addPart($PLAINContent, 'text/plain');
					// SET Headers and Content
				$mail->setTo(array($recipient));
				$mail->send();
			} else {
				//require_once(PATH_BE_div2007 . 'class.tx_div2007_email.php');
				tx_div2007_email::sendMail(
					$recipient,
					$subject,
					$PLAINContent,
					$HTMLContent,
					$fromEmail,
					$fromName,
					$fileAttachment,
					'',
					'',
					'',
					$replyTo
				);
			}
		}
	}
	
	/**
	 * Embeds media into the mail message
	 *
	 * @param t3lib_mail_Message $mail: mail message
	 * @param string $htmlContent: the HTML content of the message
	 * @return string the subtituted HTML content
	 */
	public function embedMedia(t3lib_mail_Message $mail, $htmlContent) {
		$substitutedHtmlContent = $htmlContent;
		$media = array();
		$attribRegex = $this->makeTagRegex(array('img', 'embed', 'audio', 'video'));
			// Split the document by the beginning of the above tags
		$codepieces = preg_split($attribRegex, $htmlContent);
		$len = strlen($codepieces[0]);
		$pieces = count($codepieces);
		$reg = array();
		for ($i = 1; $i < $pieces; $i++) {
			$tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
			$len += strlen($tag) + strlen($codepieces[$i]) + 2;
			$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
				// Fetches the attributes for the tag
			$attributes = $this->getTagAttributes($reg[0]);
			if ($attributes['src']) {
				$media[] = $attributes['src'];
			}
		}
		foreach ($media as $key => $source) {
			$substitutedHtmlContent = str_replace(
				'"' . $source . '"',
				'"' . $mail->embed(Swift_Image::fromPath($source)) . '"',
				$substitutedHtmlContent);
		}
		return $substitutedHtmlContent;
	}

	/**
	 * Creates a regular expression out of an array of tags
	 *
	 * @param	array		$tags: the array of tags
	 * @return	string		the regular expression
	 */
	public function makeTagRegex(array $tags) {
		$regexpArray = array();
		foreach ($tags as $tag) {
			$regexpArray[] = '<' . $tag . '[[:space:]]';
		}
		return '/' . implode('|', $regexpArray) . '/i';
	}

	/**
	 * This function analyzes a HTML tag
	 * If an attribute is empty (like OPTION) the value of that key is just empty. Check it with is_set();
	 *
	 * @param string $tag: is either like this "<TAG OPTION ATTRIB=VALUE>" or this " OPTION ATTRIB=VALUE>" which means you can omit the tag-name
	 * @return array array with attributes as keys in lower-case
	 */
	public function getTagAttributes($tag) {
		$attributes = array();
		$tag = ltrim(preg_replace('/^<[^ ]*/', '', trim($tag)));
		$tagLen = strlen($tag);
		$safetyCounter = 100;
			// Find attribute
		while ($tag) {
			$value = '';
			$reg = preg_split('/[[:space:]=>]/', $tag, 2);
			$attrib = $reg[0];

			$tag = ltrim(substr($tag, strlen($attrib), $tagLen));
			if (substr($tag, 0, 1) == '=') {
				$tag = ltrim(substr($tag, 1, $tagLen));
				if (substr($tag, 0, 1) == '"') {
						// Quotes around the value
					$reg = explode('"', substr($tag, 1, $tagLen), 2);
					$tag = ltrim($reg[1]);
					$value = $reg[0];
				} else {
						// No quotes around value
					preg_match('/^([^[:space:]>]*)(.*)/', $tag, $reg);
					$value = trim($reg[1]);
					$tag = ltrim($reg[2]);
					if (substr($tag, 0, 1) == '>') {
						$tag = '';
					}
				}
			}
			$attributes[strtolower($attrib)] = $value;
			$safetyCounter--;
			if ($safetyCounter < 0) {
				break;
			}
		}
		return $attributes;
	}
}
if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php']) {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php']);
}
?>