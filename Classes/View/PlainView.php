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
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use TYPO3\CMS\Core\Html\HtmlParser;

/**
 * Display functions
 */

class PlainView extends AbstractView
{
	/**
	 * Initializes a template, filling values for data and labels
	 *
	 * @param string $subpartMarker: the template subpart marker
	 * @param array $dataArray: the incoming data array, if any
	 * @return string the template with substituted parts and markers
	 */
	public function render($subpartMarker, array $dataArray, array $origArray, array $securedArray, $cmd, $cmdKey) {
		$content = '';
		$templateCode = HtmlParser::getSubpart($this->marker->getTemplateCode(), $subpartMarker);
		if (empty($templateCode)) {
			$errorText = LocalizationUtility::translate('internal_no_subtemplate', $this->extensionName);
			$errorText = sprintf($errorText, $subpartMarker);
			throw new Exception($errorText, Exception::MISSING_SUBPART);
		}
		$viewOnly = true;
		$requiredFields = $this->data->getRequiredFieldsArray($cmdKey);
		$templateCode = $this->marker->removeRequired($templateCode, $this->data->getFailure(), $requiredFields, $this->data->getFieldList(), $this->data->getSpecialFieldList(), $cmdKey, $viewOnly);
		$this->marker->fillInMarkerArray($dataArray, $securedArray, '');
		$this->marker->addStaticInfoMarkers($dataArray, $viewOnly);
		$this->marker->addTcaMarkers($dataArray, $origArray, $cmd, $cmdKey, $viewOnly, $requiredFields);
		$this->marker->addLabelMarkers($dataArray, $origArray, $securedArray, array(), $this->data->getRequiredFieldsArray($cmdKey), $this->data->getFieldList(), $this->data->getSpecialFieldList());
		$templateCode = $this->marker->removeStaticInfoSubparts($templateCode, $viewOnly);
		$this->marker->removePasswordMarkers();
		$uppercase = false;
		$deleteUnusedMarkers = true;
		$content .= HtmlParser::substituteMarkerArray($templateCode, $this->marker->getMarkerArray(), '', $uppercase, $deleteUnusedMarkers);
		return $content;
	}
}