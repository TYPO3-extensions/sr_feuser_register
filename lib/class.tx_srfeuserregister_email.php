<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
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
 * Part of the sr_feuser_register (Frontend User Registration) extension.
 *
 * email functions
 *
 * $Id$
 *
 * @author Kasper Skaarhoj <kasper2007@typo3.com>
 * @author Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author Franz Holzinger <kontakt@fholzinger.com>
 *
 * @package TYPO3
 * @subpackage sr_feuser_register
 *
 *
 */

require_once(PATH_t3lib.'class.t3lib_htmlmail.php');


class tx_srfeuserregister_email {
	var $pibase;
	var $conf = array();
	var $config = array();
	var $display;
	var $data;
	var $marker;
	var $tca;
	var $control;
	var $auth;
	var $infomailPrefix = 'INFOMAIL_';
	var $emailMarkPrefix = 'EMAIL_TEMPLATE_';
	var $emailMarkAdminSuffix = '_ADMIN';
	var $emailMarkHTMLSuffix = '_HTML';
	var $setfixedEnabled;
	var $HTMLMailEnabled = true;
	var $cObj;

	function init(&$pibase, &$conf, &$config, &$display, &$data, &$marker, &$tca, &$control, &$auth)	{
		$this->pibase = &$pibase;
		$this->conf = &$conf;
		$this->config = &$config;
		$this->display = &$display;
		$this->data = &$data;
		$this->marker = &$marker;
		$this->tca = &$tca;
		$this->control = &$control;
		$this->auth = &$auth;
		$this->cObj = &$pibase->cObj;

		$this->setfixedEnabled = $control->setfixedEnabled;
		if (isset($this->conf['email.']['HTMLMail'])) {
			$this->HTMLMailEnabled = $this->conf['email.']['HTMLMail'];
		}

			// Setting CSS style markers if required
		if ($this->HTMLMailEnabled) {
			$markerArray = $this->marker->getArray();
			$this->addCSSStyleMarkers($markerArray);
			$this->marker->setArray($markerArray);
		}
	}

	/**
		* Sends info mail to subscriber
		*
		* @param array  Array with key/values being marker-strings/substitution values.
		* @return	string		HTML content message
		* @see init(),compile(), send()
		*/
	function sendInfo(&$markContentArray,$cmd, $cmdKey, &$templateCode)	{
		if ($this->conf['infomail'] && $this->conf['email.']['field'])	{
			$fetch = $this->data->getFeUserData('fetch');
			$theTable = $this->data->getTable();
			if (isset($fetch) && !empty($fetch))	{
				$pidLock = 'AND pid IN ('.$this->control->thePid.')';
					// Getting records
				if ( $theTable == 'fe_users' && t3lib_div::testInt($fetch) )	{
					$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($theTable,'uid',$fetch,$pidLock,'','','1');
				} elseif ($fetch) {	// $this->conf['email.']['field'] must be a valid field in the table!
					$DBrows = $GLOBALS['TSFE']->sys_page->getRecordsByField($theTable,$this->conf['email.']['field'],$fetch,$pidLock,'','','100');
				}
					// Processing records
				if (is_array($DBrows))	{
					$recipient = $DBrows[0][$this->conf['email.']['field']];
					$this->data->setDataArray($DBrows[0]);
					$this->compile('INFOMAIL', $DBrows, trim($recipient), $markContentArray, $cmd, $cmdKey, $templateCode, $this->conf['setfixed.']);
				} elseif ($this->cObj->checkEmail($fetch)) {
					$fetchArray = array( '0' => array( 'email' => $fetch));
					$this->compile('INFOMAIL_NORECORD', $fetchArray, $fetch, $markContentArray, $cmd, $cmdKey, $templateCode, array());
				}
				$subpartkey = $this->emailMarkPrefix.$this->infomailPrefix.'SENT###';
				$content = 
					$this->display->getPlainTemplate($subpartkey,  (is_array($DBrows)?$DBrows[0]:(is_array($fetchArray)?$fetchArray[0]:''))
					);
				if (!$content)	{ // compatibility until 1.1.2010
					$subpartkey = '###TEMPLATE_'.$this->infomailPrefix.'SENT###';
					$content = 
						$this->display->getPlainTemplate($subpartkey, (is_array($DBrows)?$DBrows[0]:(is_array($fetchArray)?$fetchArray[0]:''))
						);
				}
			} else {
				$subpartkey = '###'.$this->emailMarkPrefix.$this->infomailPrefix.'EMPTY###';
				$content = $this->display->getPlainTemplate($subpartkey);
			}
		} else {
			$content='Configuration error: infomail option is not available or emailField is not setup in TypoScript';
		}
		return $content;
	}


	/**
		* Prepares an email message
		*
		* @param string  $key: template key
		* @param array  $DBrows: invoked with just one row of fe_users!!
		* @param string  $recipient: an email or the id of a front user
		* @param array  Array with key/values being marker-strings/substitution values.
		* @param array  $setFixedConfig: a setfixed TS config array
		* @return void
		*/
	function compile($key, $DBrows, $recipient, &$markContentArray, $cmd, $cmdKey, &$templateCode, $setFixedConfig = array()) {

		$viewOnly = true;
		$content = array('user' => array(), 'HTML' => array(), 'admin' => array(), 'mail' => array());
		$content['mail'] = '';
		$content['user']['all'] = '';
		$content['HTML']['all'] = '';
		$content['admin']['all'] = '';
		if ($this->conf['email.'][$key] || ($this->setfixedEnabled && ($key == 'SETFIXED_CREATE' || $key == 'SETFIXED_CREATE_REVIEW' || $key == 'SETFIXED_INVITE' || $key == 'SETFIXED_REVIEW' || $key == 'INFOMAIL'  || $key == 'INFOMAIL_NORECORD'))) {
			$subpartMarker = $this->emailMarkPrefix.$key;
			$content ['user']['all'] = trim($this->cObj->getSubpart($templateCode, '###'.$subpartMarker.'###'));
			$content['user']['all'] = $this->display->removeRequired($content['user']['all']);
			$subpartMarker = $this->emailMarkPrefix.$key.$this->emailMarkHTMLSuffix;
			$content['HTML']['all'] = ($this->HTMLMailEnabled && $this->data->getDataArray('module_sys_dmail_html')) ? trim($this->cObj->getSubpart($templateCode, '###'.$subpartMarker.'###')):'';
			$content['HTML']['all'] = $this->display->removeRequired($content['HTML']['all']);
		}
		if ($this->conf['notify.'][$key] ) {
			$subpartMarker = '###'.$this->emailMarkPrefix.$key.$this->emailMarkAdminSuffix.'###';
			$content['admin']['all'] = trim($this->cObj->getSubpart($templateCode, $subpartMarker));
			$content['admin']['all'] = $this->display->removeRequired($content['admin']['all']);
		}
		$content['user']['rec'] = $this->cObj->getSubpart($content['user']['all'], '###SUB_RECORD###');
		$content['HTML']['rec'] = $this->cObj->getSubpart($content['HTML']['all'], '###SUB_RECORD###');
		$content['admin']['rec'] = $this->cObj->getSubpart($content['admin']['all'], '###SUB_RECORD###');
		reset($DBrows);
		foreach ($DBrows as $k => $r)	{
			$markerArray = $this->marker->getArray();
			$markerArray = $this->cObj->fillInMarkerArray($markerArray, $r, '', 0);
			$markerArray['###SYS_AUTHCODE###'] = $this->auth->authCode($r);
			$this->marker->setfixed($markerArray, $setFixedConfig, $r);
			$this->marker->addStaticInfoMarkers($markerArray, $r, $viewOnly);
			$this->tca->addTcaMarkers($markerArray, $r, $viewOnly, 'email');
			$this->marker->addFileUploadMarkers('image', $markerArray, $cmd, $cmdKey, $r, $viewOnly);
			$this->marker->addLabelMarkers($markerArray, $r, $this->control->getRequiredArray());
			if ($content['user']['rec']) {
				$content['user']['rec'] = $this->marker->removeStaticInfoSubparts($content['user']['rec'], $markerArray, $viewOnly);
				$content['user']['accum'] .= $this->cObj->substituteMarkerArray($content['user']['rec'], $markerArray);
			}
			if ($content['HTML']['rec']) {
				$content['HTML']['rec'] = $this->marker->removeStaticInfoSubparts($content['HTML']['rec'], $markerArray, $viewOnly);
				$content['HTML']['accum'] .= $this->cObj->substituteMarkerArray($content['HTML']['rec'], $markerArray);
			}
			if ($content['admin']['rec']) {
				$content['admin']['rec'] = $this->marker->removeStaticInfoSubparts($content['admin']['rec'], $markerArray, $viewOnly);
				$content['admin']['accum'] .= $this->cObj->substituteMarkerArray($content['admin']['rec'], $markerArray);
			}
		}

			// Substitute the markers and eliminate HTML markup from plain text versions, but preserve <http://...> constructs
		if ($content['user']['all']) {
			$content['user']['final'] .= $this->cObj->substituteSubpart($content['user']['all'], '###SUB_RECORD###', $content['user']['accum']);

			$content['user']['final'] = str_replace('###http', '<http', strip_tags(str_replace('<http', '###http', $content['user']['final'])));
			$content['user']['final'] = $this->display->removeHTMLComments($content['user']['final']);
			$content['user']['final'] = $this->display->replaceHTMLBr($content['user']['final']);
		}
		if ($content['HTML']['all']) {
			$content['HTML']['final'] .= $this->cObj->substituteSubpart($content['HTML']['all'], '###SUB_RECORD###', $this->pibase->pi_wrapInBaseClass($content['HTML']['accum']));
			$content['HTML']['final'] = $this->cObj->substituteMarkerArray($content['HTML']['final'], $markerArray);
		}
		if ($content['admin']['all']) {
			$content['admin']['final'] .= $this->cObj->substituteSubpart($content['admin']['all'], '###SUB_RECORD###', $content['admin']['accum']);
			// $content['admin']['final'] = str_replace('###http', '<http', strip_tags(str_replace('<http', '###http', $content['admin']['final'])));
			$content['admin']['final'] = $this->display->removeHTMLComments($content['admin']['final']);
			$content['admin']['final'] = $this->display->replaceHTMLBr($content['admin']['final']);
		}
		if (t3lib_div::testInt($recipient)) {
			$fe_userRec = $GLOBALS['TSFE']->sys_page->getRawRecord('fe_users', $recipient);
			$recipient = $fe_userRec['email'];
		}
			// Check if we need to add an attachment
		if ($this->conf['addAttachment'] && $this->conf['addAttachment.']['cmd'] == $this->control->getCmd() && $this->conf['addAttachment.']['sFK'] == $this->data->getFeUserData('sFK')) {
			$file = ($this->conf['addAttachment.']['file']) ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['addAttachment.']['file']):
			'';
		}

		$this->send($recipient, $this->conf['email.']['admin'], $content['user']['final'], $content['admin']['final'], $content['HTML']['final'], $file);
	}


	/**
	* Dispatches the email messsage
	*
	* @param string  $recipient: email address
	* @param string  $admin: email address
	* @param string  $content: plain content for the recipient
	* @param string  $content['admin']: plain content for admin
	* @param string  $content['HTML']: HTML content for the recipient
	* @param string  $fileAttachment: file name
	* @return void
	*/
	function send($recipient, $admin, $content = '', $adminContent = '', $HTMLcontent = '', $fileAttachment = '') {

		// Send mail to admin
		if ($admin && $adminContent) {
			$this->cObj->sendNotifyEmail($adminContent, $admin, '', $this->conf['email.']['from'], $this->conf['email.']['fromName'], $recipient);
		}
		// Send mail to front end user
		if (!$this->HTMLMailEnabled) {
			$HTMLcontent = '';
		}

		$this->sendHTML($HTMLcontent, $content, $recipient, '', $this->conf['email.']['from'], $this->conf['email.']['fromName'], '', $fileAttachment);
	}

	/**
		* Adds CSS styles marker to a marker array for substitution in an HTML email message
		*
		* @param array  $markerArray: the input marker array
		* @return void
		*/
	function addCSSStyleMarkers(&$markerArray) {
		if (!$markerArray)	{
			$markerArray = $this->getArray();
		}
		$HTMLMailEnabled = $this->conf['email.']['HTMLMail'];
		if ($HTMLMailEnabled ) {
			if ($this->conf['templateStyle'] == 'css-styled') {
				$markerArray['###CSS_STYLES###'] = '	/*<![CDATA[*/
';
				$markerArray['###CSS_STYLES###'] .= $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
				$markerArray['###CSS_STYLES###'] .= '
/*]]>*/';
			} else {
				$markerArray['###CSS_STYLES###'] = $this->cObj->fileResource($this->conf['email.']['HTMLMailCSS']);
			}
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
	function sendHTML($HTMLContent, $PLAINContent, $recipient, $dummy, $fromEmail, $fromName, $replyTo = '', $fileAttachment = '') {
		// HTML
		if (trim($recipient)) {
			$defaultSubject = 'Front end user registration message';
			if ($HTMLContent)	{
				$parts = spliti('<title>|</title>', $HTMLContent, 3);
				$subject = trim($parts[1]) ? strip_tags(trim($parts[1])) : $defaultSubject;
			} else {
				$parts = split(chr(10),$PLAINContent,2);    // First line is subject
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/lib/class.tx_srfeuserregister_email.php']);
}
?>
