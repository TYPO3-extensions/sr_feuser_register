<?php
namespace SJBR\SrFeuserRegister\Hooks;

/*
 *  Copyright notice
 *
 *  (c) 2017 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Hooks for file upload fields
 */
class FileUploadHooks
{
	/**
	 * Evaluates the incoming data
	 *
	 * @param string $theTable: the name of the table in use
	 * @param array $dataArray: current input array
	 * @param string $theField: the name of the field
	 * @param string $cmdKey: the current command key
	 * @param array $cmdParts: parts of the 'eval' command
	 * @param string $extensionName: name of the extension
	 * @return array array of error messages
	 */
	public function evalValues($theTable, array &$dataArray, $theField, $cmdKey, array $cmdParts, $extensionName = '')
	{
		$failureMsg = [];
		if (trim($cmdParts[0]) === 'upload') {
			if (is_array($_FILES['FE']['name'][$theTable][$theField])) {
				// Some files may not have been processed due to upload restrictions
				foreach ($_FILES['FE']['name'][$theTable][$theField] as $i => $fileName) {
					if ($fileName) {
						if (!$this->checkFilename($fileName)) {
							$fI = pathinfo($fileName);
							$fileExtension = strtolower($fI['extension']);
							$failureMsg[] = $this->getFailureText($theField, 'allowed', 'evalErrors_file_extension', $fileExtension, $extensionName);
						}
						switch ($_FILES['FE']['error'][$theTable][$theField][$i]) {
							case UPLOAD_ERR_OK:
							case UPLOAD_ERR_NO_FILE:
								break;
							case UPLOAD_ERR_INI_SIZE:
								$maxSize = ini_get('upload_max_filesize')*1024;
								$failureMsg[] = $this->getFailureText($theField, 'max_size', 'evalErrors_size_too_large', $maxSize, $extensionName);
								break;
							case UPLOAD_ERR_FORM_SIZE:
								$maxSize = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config']['max_size'] ?: '';
								$failureMsg[] = $this->getFailureText($theField, 'max_size', 'evalErrors_size_too_large', $maxSize, $extensionName);
								break;
							case UPLOAD_ERR_NO_TMP_DIR:
							case UPLOAD_ERR_CANT_WRITE:
								$failureMsg[] = $this->getFailureText($theField, 'isfile', 'evalErrors_write_permission', $fileName, $extensionName);
								break;
							default:
								$failureMsg[] = $this->getFailureText($theField, 'isfile', 'evalErrors_file_upload', $fileName, $extensionName);
								break;
						}
					}
				}
			}
			$uploadPath = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config']['uploadfolder'];
			$uploadPath = $uploadPath ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
			if (isset($dataArray[$theField]) && $uploadPath) {
				$maxSize = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageMaxSize'];
				$allowedExtArray = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['imageTypes'], true);
				$fileArray = $dataArray[$theField];
				$fileNameArray = [];
				if (is_array($fileArray)) {
					foreach ($fileArray as $k => $file) {
						$fI = pathinfo($file['name']);
						$fileExtension = strtolower($fI['extension']);
						if (empty($allowedExtArray) || in_array($fileExtension, $allowedExtArray)) {
							if ($this->isFile($file, $theTable, $theField)) {
								if (!$maxSize || ($file['size'] < ($maxSize * 1024))) {
									$fileNameArray[] = $file['name'];
								} else {
									$failureMsg[] = $this->getFailureText($theField, 'max_size', 'evalErrors_size_too_large', $maxSize, $extensionName);
									$this->deleteFile($file, $theTable, $theField, $dataArray['uid']);
								}
							}
						} else {
							$failureMsg[] = $this->getFailureText($theField, 'allowed', 'evalErrors_file_extension', $fileExtension, $extensionName);
							$this->deleteFile($file, $theTable, $theField, $dataArray['uid']);
						}
					}
					$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
					if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
						$dataArray[$theField] = count($fileNameArray);
					} else {
						$dataArray[$theField] = $fileNameArray;
					}
				}
			}
		}
		return $failureMsg;
	}

	/**
	 * Parses the incoming data
	 *
	 * @param string $theTable: the name of the table in use
	 * @param array $dataArray: the incoming data array
	 * @param mixed $dataValue: current input value
	 * @param string $theField: the name of the field
	 * @param string $cmdKey: the current command key
	 * @param array $cmdParts: parts of the 'parse' command
	 * @param string $extensionName: name of the extension
	 * @return array parsed value for the field
	 */
	public function parseValues($theTable, array $dataArray, $dataValue, $theField, $cmdKey, array $cmdParts)
	{
		if (trim($cmdParts[0]) === 'files') {
			$fieldDataArray = [];
			if (isset($dataValue)) {
				if (is_array($dataValue)) {
					$fieldDataArray = $dataValue;
				} else if (is_string($dataValue) && trim($dataValue)) {
					$fieldDataArray = GeneralUtility::trimExplode(',', $dataValue, true);
				}
			}
			return $this->processFiles($theTable, $dataArray, $theField, $fieldDataArray, $cmdKey);
		}
	}

	/**
	 * Processes uploaded files
	 *
	 * @param string $theTable: the name of the table in use
	 * @param array $dataArray: the incoming data array
	 * @param string $theField: the name of the field
	 * @param array $fieldData: field value
	 * @param string $cmdKe: the command key being processed
	 * @return array file names
	 */
	protected function processFiles($theTable, array $dataArray, $theField, array $fieldData, $cmdKey)
	{
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		$uploadPath = $fieldConfig['uploadfolder'];
		$uploadPath = $uploadPath ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
		$fileArray = [];
		if ($uploadPath) {
			if (count($fieldData)) {
				foreach ($fieldData as $file) {
					if (is_array($file)) {
						if ($this->checkFilename($file['name'])) {
							if ($file['submit_delete']) {
								$this->deleteFile($file, $theTable, $theField, $dataArray['uid']);
							} else {
								$fileArray[] = $file;
							}
						}
					} else {
						if ($fieldConfig['type'] === 'group' && $fieldConfig['internal_type'] === 'file') {
							$fileArray[] = ['name' => $file, 'size' => filesize(PATH_site . $uploadPath . '/' . $file)];
						}
					}
				}
			}
			if (is_array($_FILES['FE']['name'][$theTable][$theField])) {
				foreach($_FILES['FE']['name'][$theTable][$theField] as $i => $fileName) {
					if (
						$fileName
						&& $this->checkFilename($fileName)
						&& !$_FILES['FE']['error'][$theTable][$theField][$i]
						&& $_FILES['FE']['tmp_name'][$theTable][$theField][$i]
					) {
						$fI = pathinfo($fileName);
						if (GeneralUtility::verifyFilenameAgainstDenyPattern($fI['name'])) {
							$file = $this->createFile($_FILES['FE']['tmp_name'][$theTable][$theField][$i], $fileName, $theTable, $theField, $dataArray['uid']);
							if ($file !== false) {
								$fileArray[] = $file;
							}
						}
					}
				}
			}
		}
		return $fileArray;
	}

	/**
	 * Creates a file
	 *
	 * @param string $uploadedFileName: the name of the uploaded file (from $_FILES['FE']['tmp_name'])
	 * @param string $fileName
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the name of the field
	 * @param int $uid in the table in use
	 * @return array|bool an associative array with keys name, uid, size or false
	 */
	protected function createFile($uploadedFileName, $fileName, $theTable, $theField, $uid)
	{
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		$uploadPath = $fieldConfig['uploadfolder'];
		$uploadPath = $uploadPath ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
		if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
			// Create the file
			$fI = pathinfo($fileName);
			$newFileName = basename($fileName, '.' . $fI['extension']) . '_' . GeneralUtility::shortmd5(uniqid($fileName)) . '.' . $fI['extension'];
			$resourceFactory = ResourceFactory::getInstance();
			$folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($uploadPath);
			$fileObject = $folder->addFile(
				  $uploadedFileName,
				  $newFileName,
				  DuplicationBehavior::RENAME
			);
			$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
			// Get the record from the table in use
			$connectionTheTable = $connectionPool->getConnectionForTable($theTable);
			$row = $connectionTheTable
				->select(
					['uid', 'pid', $theField],
					$theTable,
					['uid' => (int)$uid]
				)
				->fetch();
			// Create the file reference
			$connectionFileReference = $connectionPool->getConnectionForTable('sys_file_reference');
			$connectionFileReference
				->insert(
					'sys_file_reference',
					[
						'pid' => (int)$row['pid'],
						'table_local' => 'sys_file',
						'uid_local' => (int)$fileObject->getUid(),
						'tablenames' => $theTable,
						'uid_foreign' => (int)$uid,
						'fieldname' => $theField
					]
				);
			$fileReferenceUid = $connectionFileReference->lastInsertId('sys_file_reference');
			$fileRepository = GeneralUtility::makeInstance(FileRepository::class);
			$fileReference = $fileRepository->findFileReferenceByUid((int)$fileReferenceUid);
			// Update the inline count
			$connectionTheTable
				->update(
					$theTable,
					[$theField => (int)$row[$theField]+1],
					['uid' => (int)$uid]
				);
			return is_object($fileReference) ? ['name' => $fileReference->getName(), 'uid' => $fileReference->getUid(), 'size' => $fileReference->getSize()] : false;			
		} else {
			$fileUtility = GeneralUtility::makeInstance(BasicFileUtility::class);
			$fI = pathinfo($fileName);
			$tempFileName = basename($fileName, '.' . $fI['extension']) . '_' . GeneralUtility::shortmd5(uniqid($fileName)) . '.' . $fI['extension'];
			$cleanFileName = $fileUtility->cleanFileName($tempFileName);
			$theDestFile = $fileUtility->getUniqueName($cleanFileName, PATH_site . $uploadPath . '/');
			$fI = pathinfo($theDestFile);
			GeneralUtility::upload_copy_move($uploadedFileName, $theDestFile);
			return ['name' => $fI['basename'], 'size' => filesize(PATH_site . $uploadPath . '/' . $fI['basename'])];
		}
	}

	/**
	 * Checks whether there is a file
	 *
	 * @param array $file: an associative array with keys name, uid, size and submit_delete
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the name of the field
	 * @return bool true if the file exists
	 */
	protected function isFile(array $file, $theTable, $theField)
	{
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
			$fileRepository = GeneralUtility::makeInstance(FileRepository::class);
			$fileReference = $fileRepository->findFileReferenceByUid((int)$file['uid']);
			return is_object($fileReference);		
		} else {
			$uploadPath = $fieldConfig['uploadfolder'];
			$uploadPath = $uploadPath ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
			return @is_file(PATH_site . $uploadPath . '/' . $file['name']);
		}
	}

	/**
	 * Deletes a file
	 *
	 * @param array $file: an associative array with keys name, uid, size and submit_delete
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the name of the field
	 * @param int $uid in the table in use
	 * @return void
	 */
	protected function deleteFile(array $file, $theTable, $theField, $uid)
	{
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
			if ($file['submit_delete'] && $file['uid']) {
				// Delete the file reference
				$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
				$connectionPool->getConnectionForTable('sys_file_reference')
					->delete(
						'sys_file_reference',
						['uid' => (int)$file['uid']]
					);
				// Update the inline count
				$connectionTheTable = $connectionPool->getConnectionForTable($theTable);
				$row = $connectionTheTable
					->select(
						['uid', $theField],
						$theTable,
						['uid' => (int)$uid]
					)
					->fetch();
				if ($row[$theField]) {
					$connectionTheTable
						->update(
							$theTable,
							[$theField => (int)$row[$theField]-1],
							['uid' => (int)$uid]
						);
				}
			}
		} else {
			$uploadPath = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config']['uploadfolder'];
			$uploadPath = $uploadPath ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sr_feuser_register']['uploadfolder'];
			if (@is_file(PATH_site . $uploadPath . '/' . $file['name'])) {
				@unlink(PATH_site . $uploadPath . '/' . $file['name']);
			}
		}
	}

	/**
	 * Checks for valid filenames
	 *
	 * @param string $fileName: the name of the file
	 * @return bool true, if the filename is allowed
	 */
	protected function checkFilename($fileName)
	{
		$fI = pathinfo($fileName);
		$fileExtension = strtolower($fI['extension']);
		return (strpos($fileExtension, 'php') === false) && (strpos($fileExtension, 'htaccess') === false) && (strpos($fileName, '..') === false);
	}

	/**
	 * Gets the error message to be displayed
	 *
	 * @param string $theField: the name of the field being validated
	 * @param string $theRule: the name of the validation rule being evaluated
	 * @param string $label: a default error message provided by the invoking function
	 * @param string $param: parameter for the error message
	 * @param string $extensionName: name of the extension
	 * @return string the error message to be displayed
	 */
	protected function getFailureText($theField, $theRule, $label, $param = '', $extensionName)
	{
		$failureLabel = '';
		if ($theRule) {
			$failureLabel = LocalizationUtility::translate('evalErrors_' . $theRule . '_' . $theField, $extensionName);
			$failureLabel = $failureLabel ?: LocalizationUtility::translate('evalErrors_' . $theRule, $extensionName);
		}
		if (!$failureLabel) {
			$failureLabel = LocalizationUtility::translate($label, $extensionName);
		}
		if ($param) {
			$failureLabel = sprintf($failureLabel, $param);
		}
		return $failureLabel;
	}

	/**
	 * Adds uploading markers to a marker array
	 *
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the field name
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param array $dataArray: the record array
	 * @param bool $viewOnly: whether the fields are presented for view only or for input/update
	 * @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	 * @param bool $bHtml: wheter HTML or plain text should be generated
	 * @param string $extensionName: name of the extension
	 * @param string $prefixId: the prefixId
	 * @param array $conf: the plugin configuration
	 * @return void
	 */
	public function addMarkers($theTable, $theField, $cmd, $cmdKey, $dataArray = array(), $viewOnly = false, $activity = '', $bHtml = true, $extensionName, $prefixId, array $conf)
	{
		$markerArray = [];
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		if ($fieldConfig['type'] === 'inline' && $fieldConfig['foreign_table'] === 'sys_file_reference') {
			$fileRepository = GeneralUtility::makeInstance(FileRepository::class);
			$filenameArray = [];
			if ($dataArray['uid']) {
				$fileReferences = $fileRepository->findByRelation($theTable, $theField, $dataArray['uid']);
				foreach ($fileReferences as $fileReference) {
					$filenameArray[] = [
						'name' => $fileReference->getName(),
						'uid' => $fileReference->getUid(),
						'url' => $fileReference->getPublicUrl()
					];
				}
			}
			$fileUploader = $this->buildFileUploader($theTable, $theField, $cmd, $cmdKey, $filenameArray, $viewOnly, $activity, $bHtml, $extensionName, $prefixId, $conf);
			if ($viewOnly) {
				$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] = $fileUploader;
			} else {
				$markerArray['###UPLOAD_' . $theField . '###'] = $fileUploader;
				$max_size = $fieldConfig['max_size'] * 1024;
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="MAX_FILE_SIZE" value="' . $max_size . '" />' . LF;
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $prefixId . '[fileDelete]" value="0" />' . LF;
			}
		} else {
			// TYPO3 CMS 7 LTS
			$markerArray = $this->addCompatibleMarkers($theTable, $theField, $cmd, $cmdKey, $dataArray, $viewOnly, $activity, $bHtml, $extensionName, $prefixId, $conf);
		}
		return $markerArray;
	}

	/**
	 * TYPO3 CMS 7 LTS
	 *
	 * Adds uploading markers to a marker array
	 *
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the field name
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param array $dataArray: the record array
	 * @param bool $viewOnly: whether the fields are presented for view only or for input/update
	 * @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	 * @param bool $bHtml: wheter HTML or plain text should be generated
	 * @param string $extensionName: name of the extension
	 * @param string $prefixId: the prefixId
	 * @param array $conf: the plugin configuration
	 * @return void
	 */
	public function addCompatibleMarkers($theTable, $theField, $cmd, $cmdKey, $dataArray = array(), $viewOnly = false, $activity = '', $bHtml = true, $extensionName, $prefixId, array $conf)
	{
		$markerArray = [];
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		if ($fieldConfig['type'] === 'group' && $fieldConfig['internal_type'] === 'file' && !empty($fieldConfig['uploadfolder'])) {
			$filenameArray = is_array($dataArray[$theField]) ? $dataArray[$theField] : [];
			$fileUploader = $this->buildCompatibleFileUploader($theTable, $theField, $cmd, $cmdKey, $filenameArray, $viewOnly, $activity, $bHtml, $extensionName, $prefixId, $conf);
			if ($viewOnly) {
				$markerArray['###UPLOAD_PREVIEW_' . $theField . '###'] = $fileUploader;
			} else {
				$markerArray['###UPLOAD_' . $theField . '###'] = $fileUploader;
				$max_size = $fieldConfig['max_size'] * 1024;
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="MAX_FILE_SIZE" value="' . $max_size . '" />' . LF;
				$markerArray['###HIDDENFIELDS###'] .= '<input type="hidden" name="' . $prefixId . '[fileDelete]" value="0" />' . LF;
			}
		}
		return $markerArray;
	}

	/**
	 * Builds a file uploader
	 *
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the field name
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param array $filenameArray: array of uploaded file names
	 * @param boolean $viewOnly: whether the fields are presented for view only or for input/update
	 * @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	 * @param bool $bHtml: wheter HTML or plain text should be generated
	 * @param string $extensionName: name of the extension
	 * @param string $prefixId: the prefixId
	 * @param array $conf: the plugin configuration
	 * @return string generated HTML uploading tags
	 */
	protected function buildFileUploader($theTable, $theField, $cmd, $cmdKey, array $filenameArray, $viewOnly = false, $activity = '', $bHtml = true, $extensionName, $prefixId, array $conf)
	{
		$HTMLContent = '';
		$fieldConfig = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		$size = $fieldConfig['maxitems'];
		$cmdParts = preg_split('/\[|\]/', $conf[$cmdKey . '.']['evalValues.'][$theField]);
		if (!empty($cmdParts[1])) {
			$size = min($size, (int) $cmdParts[1]);
		}
		$size = $size ? $size : 1;
		$number = $size - count($filenameArray);
		$dir = $config['uploadfolder'];
		if ($viewOnly) {
			for ($i = 0; $i < count($filenameArray); $i++) {
				$HTMLContent .= $filenameArray[$i]['name'];
				if ($activity == 'email') {
					if ($bHtml)	{
						$HTMLContent .= '<br />';
					} else {
						$HTMLContent .= chr(13) . chr(10);
					}
				} else if ($bHtml) {
					$HTMLContent .= '<a href="' . $filenameArray[$i]['url'] . '"' .
					CssUtility::classParam($prefixId, 'file-view') .
					' target="_blank" title="' . LocalizationUtility::translate('file_view', $extensionName) . '">' . LocalizationUtility::translate('file_view', $extensionName) . '</a><br />';
				}
			}
		} else {
			$formName = $this->conf['formName'] ?: CssUtility::getClassName($prefixId, $theTable . '_form');
			for ($i = 0; $i < count($filenameArray); $i++) {
				$HTMLContent .=
					$filenameArray[$i]['name']
					 . '<input type="hidden" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][name]" value="' . htmlspecialchars($filenameArray[$i]['name']) . '">'
					. '<input type="hidden" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][submit_delete]" value="0">'
					 . '<input type="hidden" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][uid]" value="' . $filenameArray[$i]['uid'] . '">'
					. '<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($conf['icon_delete']) . '"  title="' . LocalizationUtility::translate('icon_delete', $extensionName) . '" alt="' . LocalizationUtility::translate('icon_delete', $extensionName) . '"' .
					CssUtility::classParam($prefixId, 'delete-view') .
					' onclick=\'if(confirm("' . LocalizationUtility::translate('confirm_file_delete', $extensionName) . '")) { var form = window.document.getElementById("' . $formName . '"); form["' . $prefixId . '[fileDelete]"].value = 1; form["FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][submit_delete]"].value = 1; return true;} else { return false;} \' />'
					. '<a href="' . $filenameArray[$i]['url'] . '" ' .
					CssUtility::classParam($prefixId, 'file-view') .
					' target="_blank" title="' . LocalizationUtility::translate('file_view', $extensionName) . '">' .
					LocalizationUtility::translate('file_view', $extensionName) . '</a><br />';
			}
			for ($i = count($filenameArray); $i < $number + count($filenameArray); $i++) {
				$HTMLContent .= '<input id="' .
				CssUtility::getClassName($prefixId, $theField) .
				'-' . ($i - count($filenameArray)) . '" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . ']" title="' . LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'image', $extensionName) . '" size="40" type="file" ' .
				CssUtility::classParam($prefixId, 'uploader-view') .
				' /><br />';
			}
		}
		return $HTMLContent;
	}

	/**
	 * TYPO3 CMS 7 LTS
	 *
	 * Builds a file uploader
	 *
	 * @param string $theTable: the name of the table in use
	 * @param string $theField: the field name
	 * @param string $cmd: the command CODE
	 * @param string $cmdKey: the command key
	 * @param array $filenameArray: array of uploaded file names
	 * @param boolean $viewOnly: whether the fields are presented for view only or for input/update
	 * @param string $activity: 'preview', 'input' or 'email': parameter of stdWrap configuration
	 * @param bool $bHtml: wheter HTML or plain text should be generated
	 * @param string $extensionName: name of the extension
	 * @param string $prefixId: the prefixId
	 * @param array $conf: the plugin configuration
	 * @return string generated HTML uploading tags
	 */
	protected function buildCompatibleFileUploader($theTable, $theField, $cmd, $cmdKey, array $filenameArray, $viewOnly = false, $activity = '', $bHtml = true, $extensionName, $prefixId, array $conf)
	{
		$HTMLContent = '';
		$config = $GLOBALS['TCA'][$theTable]['columns'][$theField]['config'];
		$size = $config['maxitems'];
		$cmdParts = preg_split('/\[|\]/', $conf[$cmdKey . '.']['evalValues.'][$theField]);
		if (!empty($cmdParts[1])) {
			$size = min($size, (int) $cmdParts[1]);
		}
		$size = $size ? $size : 1;
		$number = $size - count($filenameArray);
		$dir = $config['uploadfolder'];
		if ($viewOnly) {
			for ($i = 0; $i < count($filenameArray); $i++) {
				$HTMLContent .= $filenameArray[$i];
				if ($activity == 'email') {
					if ($bHtml)	{
						$HTMLContent .= '<br />';
					} else {
						$HTMLContent .= chr(13) . chr(10);
					}
				} else if ($bHtml) {
					$HTMLContent .= '<a href="' . $dir . '/' . $filenameArray[$i] . '"' .
					CssUtility::classParam($prefixId, 'file-view') .
					' target="_blank" title="' . LocalizationUtility::translate('file_view', $extensionName) . '">' . LocalizationUtility::translate('file_view', $extensionName) . '</a><br />';
				}
			}
		} else {
			$formName = $this->conf['formName'] ?: CssUtility::getClassName($prefixId, $theTable . '_form');
			for ($i = 0; $i < count($filenameArray); $i++) {
				$HTMLContent .=
					$filenameArray[$i]
					. '<input type="hidden" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][name]" value="' . htmlspecialchars($filenameArray[$i]) . '">'
					. '<input type="hidden" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][submit_delete]" value="0">'
					. '<input type="image" src="' . $GLOBALS['TSFE']->tmpl->getFileName($conf['icon_delete']) . '"  title="' . LocalizationUtility::translate('icon_delete', $extensionName) . '" alt="' . LocalizationUtility::translate('icon_delete', $extensionName) . '"' .
					CssUtility::classParam($prefixId, 'delete-view') .
					' onclick=\'if(confirm("' . LocalizationUtility::translate('confirm_file_delete', $extensionName) . '")) { var form = window.document.getElementById("' . $formName . '"); form["' . $prefixId . '[fileDelete]"].value = 1; form["FE[' . $theTable . ']' . '[' . $theField . '][' . $i . '][submit_delete]"].value = 1; return true;} else { return false;} \' />'
					. '<a href="' . $dir . '/' . $filenameArray[$i] . '" ' .
					CssUtility::classParam($prefixId, 'file-view') .
					' target="_blank" title="' . LocalizationUtility::translate('file_view', $extensionName) . '">' .
					LocalizationUtility::translate('file_view', $extensionName) . '</a><br />';
			}
			for ($i = count($filenameArray); $i < $number + count($filenameArray); $i++) {
				$HTMLContent .= '<input id="' .
				CssUtility::getClassName($prefixId, $theField) .
				'-' . ($i - count($filenameArray)) . '" name="' . 'FE[' . $theTable . ']' . '[' . $theField . '][' . $i . ']" title="' . LocalizationUtility::translate('tooltip_' . (($cmd == 'invite') ? 'invitation_' : '')  . 'image', $extensionName) . '" size="40" type="file" ' .
				CssUtility::classParam($prefixId, 'uploader-view') .
				' /><br />';
			}
		}
		return $HTMLContent;
	}
}