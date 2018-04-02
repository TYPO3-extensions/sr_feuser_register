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
use SJBR\SrFeuserRegister\Security\Authentication;
use SJBR\SrFeuserRegister\Security\SecuredData;
use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Edit view
 */

class EditView extends AbstractView
{
	/**
	 * Displays the record update form
	 *
	 * @param array $origArray: the array coming from the database
	 * @return string the template with substituted markers
	 */
	public function render(array $dataArray, array $origArray, array $securedArray, $cmd, $cmdKey, $mode) {
		$content = '';
		$currentArray = $origArray;
		if (isset($dataArray) && is_array($dataArray)) {
			foreach ($dataArray as $key => $value) {
				if (!is_array($value) || !empty($value)) {
					$currentArray[$key] = $value;
				}
			}
		}
		if ($cmdKey === 'password') {
			$subpartMarker = '###TEMPLATE_SETFIXED_OK_APPROVE_INVITE###';
		} else {
			$subpartMarker = '###TEMPLATE_EDIT' . ($mode ? Marker::PREVIEW_SUFFIX : '') . '###';
		}
		$templateCode = $this->marker->getSubpart($this->marker->getTemplateCode(), $subpartMarker);
		if (empty($templateCode)) {
			$errorText = LocalizationUtility::translate('internal_no_subtemplate', $this->extensionName);
			$errorText = sprintf($errorText, $subpartMarker);
			throw new Exception($errorText, Exception::MISSING_SUBPART);
		}
		$isPreview = ($mode === self::MODE_PREVIEW);
		if (!$this->conf['linkToPID'] || !$this->conf['linkToPIDAddButton'] || !$isPreview) {
			$templateCode = $this->marker->substituteSubpart($templateCode, '###SUB_LINKTOPID_ADD_BUTTON###', '');
		}
		if (!$this->conf['delete']) {
			$templateCode = $this->marker->substituteSubpart($templateCode, '###SUB_LINK_TO_DELETE###', '');
		}
		$infoFields = $this->data->getFieldList();
		$requiredFields = $this->data->getRequiredFieldsArray($cmdKey);
		$this->marker->addPasswordTransmissionMarkers($this->getUsePassword(), $this->getUsePasswordAgain());
		$templateCode = $this->marker->removeRequired($templateCode, $this->data->getFailure(), $requiredFields, $infoFields, $this->data->getSpecialFieldList(), $cmdKey, $isPreview);
		$this->marker->fillInMarkerArray($currentArray, $securedArray, '', true, 'FIELD_', true, $isPreview);
		if ($cmdKey !== 'password') {
			$this->marker->addStaticInfoMarkers($currentArray, $isPreview);
		}
		$this->marker->addTcaMarkers($currentArray, $origArray, $cmd, $cmdKey, $isPreview, $requiredFields);
		$this->marker->addLabelMarkers($currentArray, $origArray, $securedArray, array(), $requiredFields, $infoFields, $this->data->getSpecialFieldList());
		if ($cmdKey !== 'password') {
			$this->marker->addAdditionalMarkers($infoFields, $cmd, $cmdKey, $currentArray, $isPreview);
		}
		$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $isPreview);
		$this->marker->addEditFormHiddenFieldsMarkers($currentArray['uid'], Authentication::authCode($origArray, $this->conf, $this->conf['setfixed.']['EDIT.']['_FIELDLIST']), $cmd);
		$this->marker->addHiddenFieldsMarkers($cmdKey, $mode, $this->conf[$cmdKey . '.']['useEmailAsUsername'], $this->conf[$cmdKey . '.']['fields'], $currentArray);
		$this->marker->removePasswordMarkers();
		$deleteUnusedMarkers = true;
		$content .= $this->marker->substituteMarkerArray($templateCode, $this->marker->getMarkerArray(), '', false, $deleteUnusedMarkers);
		if (!$isPreview) {
			$form = $this->conf['formName'] ?: CssUtility::getClassName($this->prefixId, $this->theTable . '_form');
			$modData = $this->data->modifyDataArrForFormUpdate($currentArray, $cmdKey);
			$fields = $infoFields . ',' . $this->data->getAdditionalUpdateFields();
			$fields = implode(',', array_intersect(explode(',', $fields), GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], true)));
			$fields = SecuredData::getOpenFields($fields);
			if (!empty($fields)) {
				$updateJS = $this->getUpdateJS($modData, $form, 'FE[' . $this->theTable . ']', $fields);
			}
			$content .= $updateJS;
		}
		return $content;
	}
}