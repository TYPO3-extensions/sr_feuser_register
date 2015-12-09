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
use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create View
 */
class CreateView extends AbstractView
{
	/**
	 * Generate a record creation form or a first link display to create or edit someone's data
	 *
	 * @return string the template with substituted markers
	 */
	public function render(array $dataArray, array $origArray, array $securedArray, $cmd, $cmdKey, $mode) {
		$content = '';
		$currentArray = array_merge($origArray, $dataArray);

		if ($this->theTable === 'fe_users') {
			if ($this->getUsePassword() && !isset($currentArray['password'])) {
				$currentArray['password'] = '';
			}
			if ($this->getUsePasswordAgain()) {
				$currentArray['password_again'] = $currentArray['password'];
            }
		}

		if ($this->conf['create']) {
			// Call all beforeConfirmCreate hooks before the record has been shown and confirmed
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extensionKey][$this->prefixId]['registrationProcess'] as $classRef) {
					$hookObj= GeneralUtility::makeInstance($classRef);
					if (method_exists($hookObj,'registrationProcess_beforeConfirmCreate')) {
						$hookObj->registrationProcess_beforeConfirmCreate($this->theTable, $dataArray, $this->parameters, $cmdKey, $this->conf);
					}
				}
			}
			$currentArray = array_merge($currentArray, $dataArray);
			$key = ($cmd === 'invite') ? 'INVITE': 'CREATE';
			$bNeedUpdateJS = true;
			if ($cmd === 'create' || $cmd === 'invite') {
				$subpartMarker = '###TEMPLATE_' . $key . ($mode ? Marker::PREVIEW_SUFFIX : '') . '###';
				$this->marker->addPasswordTransmissionMarkers($this->getUsePassword(), $this->getUsePasswordAgain());
			} else {
				$bNeedUpdateJS = false;
				if ($GLOBALS['TSFE']->loginUser) {
					$subpartMarker = '###TEMPLATE_CREATE_LOGIN###';
				} else {
					$subpartMarker = '###TEMPLATE_AUTH###';
				}
			}
			$templateCode = HtmlParser::getSubpart($this->marker->getTemplateCode(), $subpartMarker);
			if (empty($templateCode)) {
				$errorText = LocalizationUtility::translate('internal_no_subtemplate', $this->extensionName);
				$errorText = sprintf($errorText, $subpartMarker);
				throw new \Exception($errorText, Exception::MISSING_SUBPART);
			}
			$isPreview = ($mode === self::MODE_PREVIEW);
			$requiredFileds = $this->data->getRequiredFieldsArray($cmdKey);
			$infoFields = $this->data->getFieldList();
			$templateCode = $this->marker->removeRequired($templateCode, $this->data->getFailure(), $requiredFileds, $infoFields, $this->data->getSpecialFieldList(), $cmdKey, $isPreview);
			$this->marker->fillInMarkerArray($currentArray, $securedArray, '', true);
			$this->marker->addStaticInfoMarkers($dataArray, $isPreview);
			$this->marker->addTcaMarkers($dataArray, $origArray, $cmd, $cmdKey, $isPreview, $requiredFileds);
			foreach ($GLOBALS['TCA'][$this->theTable]['columns'] as $theField => $fieldConfig) {
				if ($fieldConfig['config']['internal_type'] === 'file' && !empty($fieldConfig['config']['allowed']) && !empty($fieldConfig['config']['uploadfolder'])) {
					$this->marker->addFileUploadMarkers($theField, $fieldConfig, $cmd, $cmdKey, $dataArray, $isPreview);
				}
			}
			$this->marker->addLabelMarkers($dataArray, $origArray, $securedArray, array(), $requiredFileds, $infoFields, $this->data->getSpecialFieldList());
			$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $isPreview);
			$this->marker->addGeneralHiddenFieldsMarkers($cmd, $this->parameters->getAuthCode(), $this->parameters->getBackURL());
			$this->marker->addHiddenFieldsMarkers($cmdKey, $mode, $this->conf[$cmdKey . '.']['useEmailAsUsername'], $this->conf[$cmdKey . '.']['fields'], $dataArray);
			$this->marker->removePasswordMarkers();
			$deleteUnusedMarkers = true;
			$content .= HtmlParser::substituteMarkerArray($templateCode, $this->marker->getMarkerArray(), '', false, $deleteUnusedMarkers);
			if (!$isPreview && $bNeedUpdateJS) {
				$fields = $this->data->getFieldList() . ',' . $this->data->getAdditionalUpdateFields();
				$fields = implode(',', array_intersect(explode(',', $fields), GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1)));	
				$fields = SecuredData::getOpenFields($fields);
				$modData = $this->data->modifyDataArrForFormUpdate($dataArray, $cmdKey);
				$form = $this->conf['formName'] ?: CssUtility::getClassName($this->prefixId, $this->theTable . '_form');
				$updateJS = $this->getUpdateJS($modData, $form, 'FE[' . $this->theTable . ']', $fields);
				$content .= $updateJS;
			}
		}
		return $content;
	}
}