<?php
namespace SJBR\SrFeuserRegister\View;

/*
 *  Copyright notice
 *
 *  (c) 2007-2020 Stanislas Rolland <typo32020(arobas)sjbr.ca>
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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * After saving view
 */

class AfterSaveView extends AbstractView
{
	/**
	 * Displaying the page here that says, the record has been saved.
	 * You're able to include the saved values by markers.
	 *
	 * @param string $subpartMarker: the template subpart marker
	 * @param array $row: the data array, if any
	 * @param string $failure: list of field with errors
	 * @return string the template with substituted parts and markers
	 */
	public function render(array $dataArray, array $origArray, array $securedArray, $cmd, $cmdKey, $key) {
		$errorContent = '';
		// Display confirmation message
		$subpartMarker = '###TEMPLATE_' . $key . '###';
		$templateCode = $this->marker->getSubpart($this->marker->getTemplateCode(), '###TEMPLATE_' . $key . '###');
		if (empty($templateCode)) {
			$errorText = LocalizationUtility::translate('internal_no_subtemplate', $this->extensionName);
			$errorText = sprintf($errorText, $subpartMarker);
			throw new Exception($errorText, Exception::MISSING_SUBPART);
		} else {
			$viewOnly = true;
			// Remove non-included fields
			$requiredFields = $this->data->getRequiredFieldsArray($cmdKey);
			$templateCode = $this->marker->removeRequired($templateCode, $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
			$this->marker->fillInMarkerArray($dataArray, $securedArray, '', true, 'FIELD_', true);
			$this->marker->addStaticInfoMarkers($dataArray, $viewOnly);
			$this->marker->addTcaMarkers($dataArray, $origArray, $cmd, $cmdKey, $viewOnly, $requiredFields);
			$this->marker->addLabelMarkers($dataArray, $origArray, $securedArray, array(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList());
			$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $viewOnly);
			$uppercase = false;
			$deleteUnusedMarkers = true;
			$content = $this->marker->substituteMarkerArray($templateCode, $this->marker->getMarkerArray(), '', $uppercase, $deleteUnusedMarkers);
		}
		return $content;
	}
}