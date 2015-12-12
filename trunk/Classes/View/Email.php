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

use SJBR\SrFeuserRegister\Exception;
use SJBR\SrFeuserRegister\Domain\Data;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Mail\Message;
use SJBR\SrFeuserRegister\Setfixed\SetfixedUrls;
use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\HtmlUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;


/**
 * Email functions
 */
class Email
{
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
	 * The table being used
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
	protected $data;

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

	public $infomailPrefix = 'INFOMAIL_';
	public $emailMarkPrefix = 'EMAIL_TEMPLATE_';
	public $emailMarkAdminSuffix = '_ADMIN';
	public $emailMarkHTMLSuffix = '_HTML';

	/**
	 * Constructor
	 *
	 * @param string $extensionKey: the extension key
	 * @param string $prefixId: the prefixId
	 * @param string $theTable: the name of the table in use
	 * @param array $conf: the plugin configuration
	 * @param Data $data: the data object
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

	public function isHTMLMailEnabled()
	{
		$enabled = true;
		if (
			isset($this->conf['email.']) &&
			isset($this->conf['email.']['HTMLMail'])
		) {
			$enabled = $this->conf['email.']['HTMLMail'];
		}
		return $enabled;
	}

	/**
	 * Prepares an email message
	 *
	 * @param string $key: template key
	 * @param array $DBrows: invoked with just one row of fe_users
	 * @param string $recipient: an email or the id of a front user
	 * @return void
	 */
	public function compile(
		$key,
		array $DBrows,
		array $origRows,
		array $securedArray,
		$recipient,
		$cmd,
		$cmdKey
	) {
		$missingSubpartArray = array();
		$userSubpartsFound = 0;
		$adminSubpartsFound = 0;
		$bHTMLMailEnabled = $this->isHTMLMailEnabled();
		$setFixedConfig = $this->conf['setfixed.'];

		if (!isset($DBrows[0]) || !is_array($DBrows[0])) {
			$DBrows = $origRows;
		}

		$bHTMLallowed = $DBrows[0]['module_sys_dmail_html'];

		// Setting CSS style markers if required
		if ($bHTMLMailEnabled) {
			$this->marker->addCSSStyleMarkers();
		}

		$viewOnly = true;
		$requiredFields = $this->data->getRequiredFieldsArray($cmdKey);
		$content = array('user' => array(), 'userhtml' => array(), 'admin' => array(), 'adminhtml' => array(), 'mail' => array());
		$content['mail'] = '';
		$content['user']['all'] = '';
		$content['userhtml']['all'] = '';
		$content['admin']['all'] = '';
		$content['adminhtml']['all'] = '';
		$setfixedArray = array('SETFIXED_CREATE', 'SETFIXED_CREATE_REVIEW', 'SETFIXED_INVITE', 'SETFIXED_REVIEW');
		$infomailArray = array('INFOMAIL', 'INFOMAIL_NORECORD');
		if (
			(isset($this->conf['email.'][$key]) && $this->conf['email.'][$key] != '0') ||
			($this->conf['enableEmailConfirmation'] && in_array($key, $setfixedArray)) ||
			(
				$this->conf['infomail'] &&
				in_array($key, $infomailArray) &&
				// Silently refuse to send infomail to non-subscriber, if so requested
				!($key === 'INFOMAIL_NORECORD' && $this->conf['email.'][$key] == '0')
			)
		) {
			$subpartMarker = '###' . $this->emailMarkPrefix . $key . '###';
			$content['user']['all'] = trim($this->marker->getSubpart($this->marker->getTemplateCode(), $subpartMarker));
			if ($content['user']['all'] == '') {
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['user']['all'] = $this->marker->removeRequired($content['user']['all'], $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
				$userSubpartsFound++;
			}
			if ($bHTMLMailEnabled && $bHTMLallowed) {
				$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkHTMLSuffix . '###';
				$content['userhtml']['all'] = trim($this->marker->getSubpart($this->marker->getTemplateCode(),  $subpartMarker));
				if ($content['userhtml']['all'] == '') {
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['userhtml']['all'] = $this->marker->removeRequired($content['userhtml']['all'], $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
					$userSubpartsFound++;
				}
			}
		}

		if (!isset($this->conf['notify.'][$key]) || $this->conf['notify.'][$key]) {
			$subpartMarker = '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . '###';
			$content['admin']['all'] = trim($this->marker->getSubpart($this->marker->getTemplateCode(), $subpartMarker));

			if ($content['admin']['all'] == '') {
				$missingSubpartArray[] = $subpartMarker;
			} else {
				$content['admin']['all'] = $this->marker->removeRequired($content['admin']['all'], $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
				$adminSubpartsFound++;
			}

			if ($bHTMLMailEnabled) {
				$subpartMarker =  '###' . $this->emailMarkPrefix . $key . $this->emailMarkAdminSuffix . $this->emailMarkHTMLSuffix . '###';
				$content['adminhtml']['all'] = trim($this->marker->getSubpart($this->marker->getTemplateCode(), $subpartMarker));

				if ($content['adminhtml']['all'] == '')	{
					$missingSubpartArray[] = $subpartMarker;
				} else {
					$content['adminhtml']['all'] = $this->marker->removeRequired($content['adminhtml']['all'], $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
					$adminSubpartsFound++;
				}
			}
		}

		$contentIndexArray = array();
		$contentIndexArray['text'] = array();
		$contentIndexArray['html'] = array();

		if ($content['user']['all']) {
			$content['user']['rec'] = $this->marker->getSubpart($content['user']['all'], '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'user';
		}
		if ($content['userhtml']['all']) {
			$content['userhtml']['rec'] = $this->marker->getSubpart($content['userhtml']['all'], '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'userhtml';
		}
		if ($content['admin']['all']) {
			$content['admin']['rec'] = $this->marker->getSubpart($content['admin']['all'], '###SUB_RECORD###');
			$contentIndexArray['text'][] = 'admin';
		}
		if ($content['adminhtml']['all']) {
			$content['adminhtml']['rec'] = $this->marker->getSubpart($content['adminhtml']['all'], '###SUB_RECORD###');
			$contentIndexArray['html'][] = 'adminhtml';
		}
		$bChangesOnly = ($this->conf['email.']['EDIT_SAVED'] == '2' && $cmd == 'edit');
		if ($bChangesOnly) {
			$keepFields = array('uid', 'pid', 'tstamp', 'name', 'username');
		} else {
			$keepFields = array();
		}
		$this->marker->fillInMarkerArray($DBrows[0], $securedArray, '', false);
		$this->marker->addLabelMarkers($DBrows[0], $origRows[0], $securedArray, $keepFields, $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $bChangesOnly);
		$content['user']['all'] = $this->marker->substituteMarkerArray($content['user']['all'], $this->marker->getMarkerArray());
		$content['userhtml']['all'] = $this->marker->substituteMarkerArray($content['userhtml']['all'], $this->marker->getMarkerArray());
		$content['admin']['all'] = $this->marker->substituteMarkerArray($content['admin']['all'], $this->marker->getMarkerArray());
		$content['adminhtml']['all'] = $this->marker->substituteMarkerArray($content['adminhtml']['all'], $this->marker->getMarkerArray());

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
			$this->marker->addAuthCodeMarker($row);
			// If setfixed is enabled
			if ($this->conf['enableEmailConfirmation']
				|| ($this->theTable === 'fe_users' && $this->conf['enableAdminReview'])
				|| $this->conf['setfixed']
				|| ($this->theTable !== 'fe_users' && $this->conf['infomail'] && ($cmd === 'setfixed' || $cmd === 'infomail'))
			) {
				$setfixedUrls = SetfixedUrls::compute($this->prefixId, $this->theTable, $this->conf, $this->parameters->getPids(), $cmd, $currentRow);
				$this->marker->addSetfixedUrlMarkers($setfixedUrls);
			}
			$this->marker->addStaticInfoMarkers($row, $viewOnly);
			foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $theField => $fieldConfig) {
				if ($fieldConfig['config']['internal_type'] == 'file' && $fieldConfig['config']['allowed'] != '' && $fieldConfig['config']['uploadfolder'] != '') {
					$this->marker->addFileUploadMarkers($theField, $fieldConfig, $cmd, $cmdKey, $row, $viewOnly, 'email', ($emailType == 'html'));
				}
			}
			$this->marker->addLabelMarkers($row, $origRow, $securedArray, $keepFields, $this->data->getRequiredFieldsArray($cmdKey), $this->data->getFieldList(), $this->data->getSpecialFieldList(), $bChangesOnly);
			foreach ($contentIndexArray as $emailType => $indexArray) {
				$this->marker->fillInMarkerArray($mrow, $securedArray, '', false, 'FIELD_', ($emailType === 'html'));
				$this->marker->addTcaMarkers($row, $origRow, $cmd, $cmdKey, $viewOnly, $this->data->getRequiredFieldsArray($cmdKey), 'email', $bChangesOnly, ($emailType === 'html'));
				foreach ($indexArray as $index) {
					$content[$index]['rec'] = $this->marker->removeStaticInfoSubparts($content[$index]['rec'], $viewOnly);
					$content[$index]['accum'] .= $this->marker->substituteMarkerArray($content[$index]['rec'], $this->marker->getMarkerArray());
					if ($emailType === 'text') {
						$content[$index]['accum'] = htmlSpecialChars_decode($content[$index]['accum'], ENT_QUOTES);
					}
				}
			}
		}

		// Substitute the markers and eliminate HTML markup from plain text versions
		if ($content['user']['all']) {
			$content['user']['final'] = $this->marker->substituteSubpart($content['user']['all'], '###SUB_RECORD###', $content['user']['accum']);
			$content['user']['final'] = HtmlUtility::removeHTMLComments($content['user']['final']);
			$content['user']['final'] = HtmlUtility::replaceHTMLBr($content['user']['final']);
			$content['user']['final'] = HtmlUtility::removeHtmlTags($content['user']['final']);
			$content['user']['final'] = HtmlUtility::removeSuperfluousLineFeeds($content['user']['final']);
			// Remove erroneous \n from locallang file
			$content['user']['final'] = str_replace('\n', '', $content['user']['final']);
		}
		if ($content['userhtml']['all']) {
			$content['userhtml']['final'] = $this->marker->substituteSubpart($content['userhtml']['all'], '###SUB_RECORD###', CssUtility::wrapInBaseClass($this->prefixId, $content['userhtml']['accum']));
			// Remove HTML comments
			$content['userhtml']['final'] = HtmlUtility::removeHTMLComments($content['userhtml']['final']);
			// Remove erroneous \n from locallang file
			$content['userhtml']['final'] = str_replace('\n', '', $content['userhtml']['final']);
		}
		if ($content['admin']['all']) {
			$content['admin']['final'] = $this->marker->substituteSubpart($content['admin']['all'], '###SUB_RECORD###', $content['admin']['accum']);
			$content['admin']['final'] = HtmlUtility::removeHTMLComments($content['admin']['final']);
			$content['admin']['final'] = HtmlUtility::replaceHTMLBr($content['admin']['final']);
			$content['admin']['final'] = HtmlUtility::removeHtmlTags($content['admin']['final']);
			$content['admin']['final'] = HtmlUtility::removeSuperfluousLineFeeds($content['admin']['final']);
			// Remove erroneous \n from locallang file
			$content['admin']['final'] = str_replace('\n', '', $content['admin']['final']);
		}

		if ($content['adminhtml']['all']) {
			$content['adminhtml']['final'] = $this->marker->substituteSubpart($content['adminhtml']['all'], '###SUB_RECORD###', CssUtility::wrapInBaseClass($this->prefixId, $content['adminhtml']['accum']));
			// Remove HTML comments
			$content['adminhtml']['final'] = HtmlUtility::removeHTMLComments($content['adminhtml']['final']);
			// Remove erroneous \n from locallang file
			$content['adminhtml']['final'] = str_replace('\n', '', $content['adminhtml']['final']);
		}

		$bRecipientIsInt = MathUtility::canBeInterpretedAsInteger($recipient);
		if ($bRecipientIsInt) {
			$fe_userRec = $GLOBALS['TSFE']->sys_page->getRawRecord('fe_users', $recipient);
			$recipient = $fe_userRec['email'];
		}
		// Check if we need to add an attachment
		if (
			$this->conf['addAttachment']
			&& $this->conf['addAttachment.']['cmd'] === $cmd
			&& $this->conf['addAttachment.']['sFK'] === $this->parameters->getFeUserData('sFK')
		) {
			$file = ($this->conf['addAttachment.']['file'] ? $GLOBALS['TSFE']->tmpl->getFileName($this->conf['addAttachment.']['file']) : '');
		}
		// SETFIXED_REVIEW will be sent to user only if the admin part is present
		if (($userSubpartsFound + $adminSubpartsFound >= 1) && ($adminSubpartsFound >= 1 || $key !== 'SETFIXED_REVIEW')) {
			Message::send($recipient, $this->conf['email.']['admin'], $content['user']['final'], $content['userhtml']['final'], $content['admin']['final'], $content['adminhtml']['final'], $file, $this->conf);
		} else if ($this->conf['notify.'][$key]) {
			$errorText = LocalizationUtility::translate('internal_no_subtemplate', $this->extensionName);
			$errorText = sprintf($errorText, implode(', ' , $missingSubpartArray));
			throw new Exception($errorText, Exception::MISSING_SUBPART);
		}
	}
}