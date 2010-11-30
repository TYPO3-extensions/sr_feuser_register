<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2010 Stanislas Rolland (stanislas.rolland@sjbr.ca)
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
 * @author	Stanislas Rolland <stanislas.rolland(arobas)sjbr.ca>
 * @author	Franz Holzinger <franz@ttproducts.de>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

require_once(PATH_t3lib.'class.t3lib_htmlmail.php');


class tx_srfeuserregister_email {
	var $langObj;
	var $conf = array();
	var $config = array();
	var $display;
	var $data;
	var $marker;
	var $tca;
	var $control;
	var $controlData;
	var $infomailPrefix = 'INFOMAIL_';
	var $emailMarkPrefix = 'EMAIL_TEMPLATE_';
	var $emailMarkAdminSuffix = '_ADMIN';
	var $emailMarkHTMLSuffix = '_HTML';
	var $HTMLMailEnabled = TRUE;
	var $cObj;


	function init (
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
	function sendInfo (
		$theTable,
		$origArr,
		$securedArray,
		&$markerArray,
		$cmd,
		$cmdKey,
		$templateCode
	)	{
		if ($this->conf['infomail'] && $this->conf['email.']['field'])	{
			$fetch = $this->controlData->getFeUserData('fetch');

			if (isset($fetch) && !empty($fetch))	{
				$pidLock = 'AND pid IN (' . ($this->cObj->data['pages'] ? $this->cObj->data['pages'] . ',' : '') . $this->controlData->getPid() . ')';
				$enable = $GLOBALS['TSFE']->sys_page->enableFields($theTable);
					// Getting records
				if ($theTable == 'fe_users' && t3lib_div::testInt($fetch) )	{
					$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField(
						$theTable,
						'uid',
						$fetch,
						$pidLock.$enable,
						'',
						'',
						'1'
					);
				} elseif ($fetch) {	// $this->conf['email.']['field'] must be a valid field in the table!
					$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField(
						$theTable,
						$this->conf['email.']['field'],
						$fetch,
						$pidLock.$enable,
						'',
						'',
						'100'
					);
				}

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
					$subpartkey = '###TEMPLATE_'.$this->infomailPrefix.'SENT###';
					$content =
						$this->display->getPlainTemplate(
							$templateCode,
							$subpartkey,
							$markerArray,
							$origArr,
							(is_array($DBrows)?$DBrows[0]:(is_array($fetchArray)?$fetchArray[0]:'')),
							$securedArray,
							FALSE
						);

					if (!$content)	{ // compatibility until 1.1.2010
						$subpartkey = '###'.$this->emailMarkPrefix.$this->infomailPrefix.'SENT###';
						$content =
							$this->display->getPlainTemplate(
								$templateCode,
								$subpartkey,
								$markerArray,
								$origArr,
								(is_array($DBrows)?$DBrows[0]:(is_array($fetchArray)?$fetchArray[0]:'')),
								$securedArray
							);
					}
				}
			} else {
				$subpartkey = '###TEMPLATE_INFOMAIL###';
				$markerArray['###FIELD_email###'] = $this->controlData->getPrefixId().'[fetch]';
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
			$content=$this->langObj->getLL('internal_infomail_configuration');
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
	function compile (
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
		global $TSFE;

		$missingSubpartArray = array();
		$subpartsFound = 0;
		if (!isset($DBrows[0]) || !is_array($DBrows[0]))	{
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
		$setfixedArray = array('SETFIXED_CREATE', 'SETFIXED_CREATE_REVIEW', 'SETFIXED_INVITE',
			'SETFIXED_REVIEW');
		$infomailArray = array('INFOMAIL', 'INFOMAIL_NORECORD');
			// Avoid sending admin-only mails to user
		$adminOnly = array('SETFIXED_REVIEW');

		if (
			($this->conf['email.'][$key] ||
			$this->conf['enableEmailConfirmation'] && in_array($key, $setfixedArray) ||
			$this->conf['infomail'] && in_array($key, $infomailArray))
			&&
			!in_array($key, $adminOnly)
		) {
			$subpartMarker = '###' . $this->emailMarkPrefix . $key . '###';
			$content['user']['all'] = trim($this->cObj->getSubpart($templateCode, $subpartMarker));

			if ($content['user']['all'] == '')	{
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['user']['all'] = $this->display->removeRequired($content['user']['all'],$errorFieldArray);
				$subpartsFound++;
			}

			if ($this->HTMLMailEnabled && $bHTMLallowed)	{
				$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkHTMLSuffix . '###';
				$content['userhtml']['all'] = trim($this->cObj->getSubpart($templateCode,  $subpartMarker));

				if ($content['userhtml']['all'] == '')	{
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['userhtml']['all'] = $this->display->removeRequired($content['userhtml']['all'],$errorFieldArray);
					$subpartsFound++;
				}
			}
		}

		if (!isset($this->conf['notify.'][$key]) || $this->conf['notify.'][$key]) {

			$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . '###';
			$content['admin']['all'] = trim($this->cObj->getSubpart($templateCode, $subpartMarker));

			if ($content['admin']['all'] == '')	{
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['admin']['all'] = $this->display->removeRequired($content['admin']['all'],$errorFieldArray);
				$subpartsFound++;
			}

			if ($this->HTMLMailEnabled)	{
				$subpartMarker =  '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . $this->emailMarkHTMLSuffix . '###';
				$content['adminhtml']['all'] = trim($this->cObj->getSubpart($templateCode, $subpartMarker));

				if ($content['adminhtml']['all'] == '')	{
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['adminhtml']['all'] = $this->display->removeRequired($content['adminhtml']['all'],$errorFieldArray);
					$subpartsFound++;
				}
			}
		}

		$contentIndexArray = array();
		$contentIndexArray['text'] = array();
		$contentIndexArray['html'] = array();

		if ($content['user']['all'])	{
			$content['user']['rec'] = $this->cObj->getSubpart($content['user']['all'], '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'user';
		}
		if ($content['userhtml']['all'])	{
			$content['userhtml']['rec'] = $this->cObj->getSubpart($content['userhtml']['all'], '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'userhtml';
		}
		if ($content['admin']['all'])	{
			$content['admin']['rec'] = $this->cObj->getSubpart($content['admin']['all'], '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'admin';
		}
		if ($content['adminhtml']['all'])	{
			$content['adminhtml']['rec'] = $this->cObj->getSubpart($content['adminhtml']['all'], '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'adminhtml';
		}
		$bChangesOnly = ($this->conf['email.']['EDIT_SAVED'] == '2' && $cmd == 'edit');
		if ($bChangesOnly)	{
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

		foreach ($DBrows as $k => $row)	{
			$origRow = $origRows[$k];

			if (isset($origRow) && is_array($origRow))	{
				if (isset($row) && is_array($row))	{
					$currentRow = array_merge($origRow, $row);
				} else {
					$currentRow = $origRow;
				}
			} else {
				$currentRow = $row;
			}
			if ($bChangesOnly)	{
				$mrow = array();
				foreach ($row as $field => $v)	{
					if (in_array($field, $keepFields))	{
						$mrow[$field] = $row[$field];
					} else {
						if ($row[$field] != $origRow[$field])	{
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

			foreach ($this->tca->TCA['columns'] as $theField => $fieldConfig)	{

				if (
					$fieldConfig['config']['internal_type'] == 'file' &&
					$fieldConfig['config']['allowed'] != '' &&
					$fieldConfig['config']['uploadfolder'] != ''
				)	{
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

			foreach ($contentIndexArray as $emailType => $indexArray)	{
				$fieldMarkerArray = array();
				$fieldMarkerArray = $this->marker->fillInMarkerArray(
					$fieldMarkerArray,
					$mrow,
					$securedArray,
					'',
					FALSE,
					'FIELD_',
					($emailType=='html')
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
					($emailType=='html')
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
				}
			}
		}

			// Substitute the markers and eliminate HTML markup from plain text versions, but preserve <http://...> constructs
		if ($content['user']['all']) {
			$content['user']['final'] .= $this->cObj->substituteSubpart($content['user']['all'], '###SUB_RECORD###', $content['user']['accum']);
			$tmp = str_replace('<http', '###http', $content['user']['final']);
			$tmp = strip_tags($tmp);
			$content['user']['final'] = str_replace('###http', '<http', strip_tags($tmp));
			$content['user']['final'] = $this->display->removeHTMLComments($content['user']['final']);
			$content['user']['final'] = $this->display->replaceHTMLBr($content['user']['final']);
		}

		if ($content['userhtml']['all']) {
			$content['userhtml']['final'] .=
				$this->cObj->substituteSubpart(
					$content['userhtml']['all'],
					'###SUB_RECORD###',
					tx_div2007_alpha::wrapInBaseClass_fh001(
						$content['userhtml']['accum'],
						$this->controlData->getPrefixId(),
						$this->controlData->getExtKey()
					)
				);
		}

		if ($content['admin']['all']) {
			$content['admin']['final'] .= $this->cObj->substituteSubpart($content['admin']['all'], '###SUB_RECORD###', $content['admin']['accum']);
			// $content['admin']['final'] = str_replace('###http', '<http', strip_tags(str_replace('<http', '###http', $content['admin']['final'])));
			$content['admin']['final'] = $this->display->removeHTMLComments($content['admin']['final']);
			$content['admin']['final'] = $this->display->replaceHTMLBr($content['admin']['final']);
		}

		if ($content['adminhtml']['all']) {
			$content['adminhtml']['final'] .=
				$this->cObj->substituteSubpart(
					$content['adminhtml']['all'],
					'###SUB_RECORD###',
					tx_div2007_alpha::wrapInBaseClass_fh001(
						$content['adminhtml']['accum'],
						$this->controlData->getPrefixId(),
						$this->controlData->getExtKey()
					)
				);
		}

		if (t3lib_div::testInt($recipient)) {
			$fe_userRec = $GLOBALS['TSFE']->sys_page->getRawRecord('fe_users', $recipient);
			$recipient = $fe_userRec['email'];
		}

			// Check if we need to add an attachment
		if ($this->conf['addAttachment'] && $this->conf['addAttachment.']['cmd'] == $cmd && $this->conf['addAttachment.']['sFK'] == $this->controlData->getFeUserData('sFK')) {
			$file = ($this->conf['addAttachment.']['file'] ? $TSFE->tmpl->getFileName($this->conf['addAttachment.']['file']) : '');
		}

		if ($subpartsFound >= 1)	{

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
	function send (
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
				'',
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
	function addCSSStyleMarkers (&$markerArray) {

		if ($this->conf['templateStyle'] == 'css-styled') {
			$markerArray['###CSS_STYLES###'] = '	/*<![CDATA[*/
';
			$markerArray['###CSS_STYLES###'] .= $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
			$markerArray['###CSS_STYLES###'] .= '
/*]]>*/';
		} else {
			$markerArray['###CSS_STYLES###'] = $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
		}
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
	function sendHTML (
		$HTMLContent,
		$PLAINContent,
		$recipient,
		$dummy,
		$fromEmail,
		$fromName,
		$replyTo = '',
		$fileAttachment = ''
	) {
		// HTML

		if (trim($recipient) && (trim($HTMLContent) || trim($PLAINContent))) {

			$defaultSubject = 'Front end user registration message';
			if ($HTMLContent)	{
				$parts = preg_split('/<title>|<\\/title>/i', $HTMLContent, 3);
				$subject = trim($parts[1]) ? strip_tags(trim($parts[1])) : $defaultSubject;
			} else {
				$parts = explode(chr(10),$PLAINContent,2);    // First line is subject
				$subject = trim($parts[0]) ? trim($parts[0]) : $defaultSubject;
				$PLAINContent = trim($parts[1]);
			}

			$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
			$Typo3_htmlmail->start();
			$Typo3_htmlmail->mailer = 'TYPO3 HTMLMail';
			$Typo3_htmlmail->subject = $subject;
			$Typo3_htmlmail->from_email = $fromEmail;
			$Typo3_htmlmail->returnPath = $fromEmail;
			$Typo3_htmlmail->from_name = $fromName;
			$Typo3_htmlmail->from_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->from_name));
			$Typo3_htmlmail->replyto_email = $replyTo ? $replyTo :$fromEmail;
			$Typo3_htmlmail->replyto_name = $replyTo ? '' : $fromName;
			$Typo3_htmlmail->replyto_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->replyto_name));
			$Typo3_htmlmail->organisation = '';
			$Typo3_htmlmail->priority = 3;

			// ATTACHMENT
			if ($fileAttachment && file_exists($fileAttachment)) {
				$Typo3_htmlmail->addAttachment($fileAttachment);
			}

			// HTML
			if (trim($HTMLContent)) {
				$Typo3_htmlmail->theParts['html']['content'] = $HTMLContent;
				$Typo3_htmlmail->theParts['html']['path'] = '';
				$Typo3_htmlmail->extractMediaLinks();
				$Typo3_htmlmail->extractHyperLinks();
				$Typo3_htmlmail->fetchHTMLMedia();
				$Typo3_htmlmail->substMediaNamesInHTML(0); // 0 = relative
				$Typo3_htmlmail->substHREFsInHTML();
				$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
			}

			// PLAIN
			$Typo3_htmlmail->addPlain($PLAINContent);
			// SET Headers and Content
			$Typo3_htmlmail->setHeaders();
			$Typo3_htmlmail->setContent();
			$Typo3_htmlmail->setRecipient($recipient);
			$Typo3_htmlmail->sendtheMail();
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php'])  {
  include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php']);
}

?>