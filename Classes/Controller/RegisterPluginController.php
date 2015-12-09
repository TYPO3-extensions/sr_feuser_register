<?php
namespace SJBR\SrFeuserRegister\Controller;

/*
 *  Copyright notice
 *
 *  (c) 1999-2003 Kasper Skårhøj <kasperYYYY@typo3.com>
 *  (c) 2004-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
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
use SJBR\SrFeuserRegister\Configuration\ConfigurationCheck;
use SJBR\SrFeuserRegister\Domain\Data;
use SJBR\SrFeuserRegister\Request\Parameters;
use SJBR\SrFeuserRegister\Security\SessionData;
use SJBR\SrFeuserRegister\Utility\CssUtility;
use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use SJBR\SrFeuserRegister\View\AbstractView;
use SJBR\SrFeuserRegister\View\Email;
use SJBR\SrFeuserRegister\View\Marker;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Front end user self-registration and profile maintenance
 */
class RegisterPluginController extends AbstractPlugin
{
	/**
	 * Content object
	 *
	 * @var ContentObjectRenderer
	 */
	public $cObj;

	/**
	 * Extension key
	 *
	 * @var string
	 */
	public $extKey = 'sr_feuser_register';

	/**
	 * Prefix used for CSS classes and variables
	 *
	 * @var string
	 */
	public $prefixId = 'tx_srfeuserregister_pi1';

	/**
	 * The table in used
	 *
	 * @var string
	 */
	protected $theTable = 'fe_users';

	/**
	 * The plugin configuration
	 *
	 * @var array
	 */
	public $conf;

	/**
	 * List of fields reserved as administration fields
	 *
	 * @var string
	 */
	protected $adminFieldList = '';

	/**
	 * A list of button label names
	 *
	 * @var string
	 */
	protected $buttonLabelsList = '';

	/**
	 * A list of other label names
	 *
	 * @var string
	 */
	protected $otherLabelsList = '';

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

	/**
	 * The email object
	 *
	 * @var Email
	 */
	protected $email;

	/**
	 * The mode (normal or preview)
	 *
	 * @var int
	 */
	protected $mode = 0;

	/**
	 * Commands that may be processed when no user is logged in
	 * @var array
	 */
	protected $noLoginCommands = array('create', 'invite', 'setfixed', 'infomail');

	/**
	 * Plugin entry script
	 *
	 * @param string $content: rendered content (not used)
	 * @param array $conf: the plugin TS configuration
	 * @return string the rendered content
	 */
	public function main($content, $conf)
	{
		$extensionName = GeneralUtility::underscoredToUpperCamelCase($this->extKey);
		$this->pi_setPiVarDefaults();
		$this->conf =& $conf;
		// Check extension requirements
		$content = ConfigurationCheck::checkRequirements($this->extKey);
		// Check installation security settings
		$content .= ConfigurationCheck::checkSecuritySettings($this->extKey);
		// Check presence of deprecated markers
		$content .= ConfigurationCheck::checkDeprecatedMarkers($this->extKey, $this->conf);
		// The table may be configured
		if (isset($this->conf['table.']) && is_array($this->conf['table.']) && $this->conf['table.']['name']) {
			$this->theTable  = $this->conf['table.']['name'];
		}
		// Check presence of configured table in TCA
		if (!is_array($GLOBALS['TCA'][$this->theTable]) || !is_array($GLOBALS['TCA'][$this->theTable]['columns'])) {
			$errorText = LocalizationUtility::translate('table_not_defined_in_TCA', $extensionName);
			$errorText = sprintf($errorText, $this->theTable);
			throw new Exception($errorText, Exception::TABLE_NOT_DEFINED);
		}
		// If no error content, proceed
		if (!$content) {
			// Validate the token and initialize request parameters
			$this->parameters = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\Request\\Parameters', $this->extKey, $this->prefixId, $this->theTable, $this->conf, $this->piVars, $this);
			if ($this->parameters->isTokenValid()) {
				// Initialize the incoming and original data
				$this->data = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\Domain\\Data', $this->extKey, $this->prefixId, $this->theTable, $this->conf, $this->cObj, $this->parameters, $this->adminFieldList);
				// Initialize the controller
				$this->initialize();
				// Initialize marker class
				$this->marker = GeneralUtility::makeInstance('SJBR\\SrFeuserRegister\\View\\Marker', $this->extKey, $this->prefixId, $this->theTable, $this->conf, $this->parameters, $this->buttonLabelsList, $this->otherLabelsList);
				// Process the request
				$content = $this->doProcessing($this->parameters->getCmd(), $this->parameters->getCmdKey(), $this->data->getOrigArray(), $this->data->getDataArray());
			} else {
				$content = LocalizationUtility::translate('invalidToken', $extensionName);
			}
		}
		return CssUtility::wrapInBaseClass($this->prefixId, $content);
	}

	/**
	 * Initialize the controller
	 */
	protected function initialize()
	{
		$origArray = array();
		$cmd = $this->parameters->getCmd();
		$dataArray = $this->data->getDataArray();
		$feUserdata = $this->parameters->getFeUserData();
		$uid = $dataArray['uid'] ? $dataArray['uid'] : ($feUserdata['rU'] ? $feUserdata['rU'] : (!in_array($cmd, $this->noLoginCommands) ? $GLOBALS['TSFE']->fe_user->user['uid'] : 0));
		if ($uid) {
			$this->data->setRecUid((int) $uid);
			$newOrigArray = $GLOBALS['TSFE']->sys_page->getRawRecord($this->theTable, (int) $uid);
			if (isset($newOrigArray) && is_array($newOrigArray)) {
				$this->data->modifyRow($newOrigArray, true);
				$origArray = $newOrigArray;
			}
		}
		$this->data->setOrigArray($origArray);
		// Set the command key
		$cmdKey = $this->setCmdKey($cmd, $uid, !empty($origArray));
		$this->parameters->setCmdKey($cmdKey);
		$this->processSettings($cmdKey);
	}

	/**
	 * Set the command key
	 *
	 * @param string $cmd: the command
	 * @param int $uid: the uid of the current record
	 * @param bool $nonEmptyRecord: true, if the current record is not empty
	 * @return string the command key
	 */
	protected function setCmdKey($cmd, $uid, $nonEmptyRecord)
	{
		$cmdKey = '';
		if ($cmd === 'edit' || $cmd === 'invite' || $cmd === 'password' || $cmd === 'infomail') {
			$cmdKey = $cmd;
		} else {
			if (($cmd === '' || $cmd === 'setfixed') && (($this->theTable !== 'fe_users' || $uid == $GLOBALS['TSFE']->fe_user->user['uid']) && $nonEmptyRecord)) {
				$cmdKey = 'edit';
			} else {
				$cmdKey = 'create';
			}
		}
		return $cmdKey;
	}

	/**
	 * Adjust some configuration settings
	 *
	 * @param string $cmdKey: the cmd key that will be used
	 * @return void
	 */
	protected function processSettings($cmdKey)
	{
		if (!ExtensionManagementUtility::isLoaded('direct_mail')) {
			$this->conf[$cmdKey.'.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1), array('module_sys_dmail_category,module_sys_dmail_newsletter')));
			$this->conf[$cmdKey . '.']['required'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'], 1), array('module_sys_dmail_category, module_sys_dmail_newsletter')));
		}
		// Make lists ready for GeneralUtility::inList which does not yet allow blanks
		$fieldConfArray = array('fields', 'required');
		foreach ($fieldConfArray as $k => $v) {
			$this->conf[$cmdKey . '.'][$v] = implode(',',  array_unique(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.'][$v], true)));
		}
		if ($this->theTable === 'fe_users') {
			// When not in edit mode, add username to lists of fields and required fields unless explicitly disabled
			if (empty($this->conf[$cmdKey.'.']['doNotEnforceUsername'])) {
				if ($cmdKey != 'edit' && $cmdKey != 'password') {
					$this->conf[$cmdKey . '.']['fields'] = implode(',', array_unique(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'] . ',username', 1)));
					$this->conf[$cmdKey . '.']['required'] = implode(',', array_unique(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'] . ',username', 1)));
				}
			}
			// When in edit mode, remove password from required fields
			if ($cmdKey === 'edit') {
				$this->conf[$cmdKey . '.']['required'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'], 1), array('password')));
			}
			if ($this->conf[$cmdKey . '.']['generateUsername'] || $cmdKey == 'password') {
				$this->conf[$cmdKey . '.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1), array('username')));
			}
			if ($cmdKey === 'invite') {
				$this->conf[$cmdKey . '.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1), array('password')));
				$this->conf[$cmdKey . '.']['required'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'], 1), array('password')));
				// Do not evaluate any password when inviting
				unset($this->conf[$cmdKey . '.']['evalValues.']['password']);
			}
			if ($this->conf[$cmdKey . '.']['useEmailAsUsername']) {
				$this->conf[$cmdKey . '.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1), array('username')));
				if ($cmdKey === 'create' || $cmdKey === 'invite') {
					$this->conf[$cmdKey . '.']['fields'] = implode(',', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'] . ',email', 1));
					$this->conf[$cmdKey . '.']['required'] = implode(',', GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['required'] . ',email', 1));
				}
				if (($cmdKey === 'edit' || $cmdKey === 'password') && ($this->conf['enableEmailConfirmation'] || $this->conf['enableAdminReview'] || $this->conf['setfixed'])) {
					$this->conf[$cmdKey . '.']['fields'] = implode(',', array_diff(GeneralUtility::trimExplode(',', $this->conf[$cmdKey . '.']['fields'], 1), array('email')));
				}
			}
			// Do not evaluate the username if it is generated or if email is used
			if ($this->conf[$cmdKey . '.']['useEmailAsUsername'] || ($this->conf[$cmdKey . '.']['generateUsername'] && $cmdKey !== 'edit' && $cmdKey !== 'password')) {
				unset($this->conf[$cmdKey . '.']['evalValues.']['username']);
			}
			// Invoke hooks that may modify the configuration
			$hookClassArray = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['configuration']) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey][$this->prefixId]['configuration'] : array();
			foreach ($hookClassArray as $classRef) {
				$hookObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($classRef);
				if (is_object($hookObject) && method_exists($hookObject, 'modifyConf')) {
					$hookObject->modifyConf($this->conf, $cmdKey);
				}
			}

			if ($cmdKey === 'invite' && $this->conf['enableAdminReview']) {
				if (
					$this->parameters->getSetfixedEnabled()
					&& is_array($this->conf['setfixed.']['ACCEPT.'])
					&& is_array($this->conf['setfixed.']['APPROVE.'])
				) {
					$this->conf['setfixed.']['APPROVE.'] = $this->conf['setfixed.']['ACCEPT.'];
				}
			}
			if ($cmdKey === 'create' && $this->conf['enableAdminReview'] && !$this->conf['enableEmailConfirmation']) {
				$this->conf['create.']['defaultValues.']['disable'] = '1';
				$this->conf['create.']['overrideValues.']['disable'] = '1';
			}
			// Infomail does not apply to fe_users
			$this->conf['infomail'] = 0;
		}

		// Adjust some evaluation settings
		if (is_array($this->conf[$cmdKey . '.']['evalValues.'])) {
			// Do not evaluate any password when inviting
			if ($cmdKey === 'invite') {
				unset($this->conf[$cmdKey . '.']['evalValues.']['password']);
			}
			// Do not evaluate the username if it is generated or if email is used
			if ($this->conf[$cmdKey . '.']['useEmailAsUsername'] || ($this->conf[$cmdKey . '.']['generateUsername'] && $cmdKey !== 'edit' && $cmdKey !== 'password')) {
				unset($this->conf[$cmdKey . '.']['evalValues.']['username']);
			}
		}
		
		// Forward the modified settings to the data object
		$this->data->setConfiguration($this->conf);
	}

	/**
	 * All actions are processed here
	 *
	 * @param string command to execute
	 * @param string command key
	 * @param array the current state in the table 
	 * @param array the incoming data
	 * @return string text to display
	 */
	public function doProcessing($cmd, $cmdKey, array $origArray, array $dataArray) {
		$finalDataArray = array();
		$securedArray = array();
		$uid = $this->data->getRecUid();

		// Check if the login user is the right one
		if (
			(
				$this->theTable === 'fe_users'
				&& (!$GLOBALS['TSFE']->loginUser || ($uid > 0 && $GLOBALS['TSFE']->fe_user->user['uid'] != $uid))
				&& !in_array($cmd, $this->noLoginCommands)
			)
		) {
			$origArray = array();
			$this->data->setOrigArray($origArray);
			$this->data->resetDataArray();
			$finalDataArray = $dataArray;
		} else if ($this->data->bNewAvailable()) {
			$finalDataArray = $dataArray;
			if ($this->theTable === 'fe_users') {
				$securedArray = SessionData::readSecuredArray($this->extKey);
			}
			ArrayUtility::mergeRecursiveWithOverrule($finalDataArray, $securedArray);
		} else {
			$finalDataArray = $dataArray;
		}
		switch ($cmd) {
			case 'create':
			case 'invite':
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\CreateActionController';
				break;
			case 'edit':
			case 'password':
			case 'login':
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\EditActionController';
				break;
			case 'delete':
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\DeleteActionController';
				break;
			case 'setfixed':
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\SetfixedActionController';
				break;
			case 'infomail':
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\InfomailActionController';
				break;
			case '':
				switch ($cmdKey) {
					case 'edit':
						$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\EditActionController';
						break;
					case 'create':
					default:
						$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\CreateActionController';
						break;
				}
				break;
			default:
				$controllerClass = 'SJBR\\SrFeuserRegister\\Controller\\CreateActionController';
		}
		$actionController = GeneralUtility::makeInstance($controllerClass, $this->extKey, $this->prefixId, $this->theTable, $this->conf, $this->data, $this->parameters, $this->marker);
		$content = $actionController->doProcessing($finalDataArray, $cmd, $cmdKey);
		return $content;
	}
}