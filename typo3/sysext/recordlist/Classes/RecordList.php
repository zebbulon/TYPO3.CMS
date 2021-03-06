<?php
namespace TYPO3\CMS\Recordlist;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Script Class for the Web > List module; rendering the listing of records on a page
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class RecordList {

	/**
	 * Page Id for which to make the listing
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Pointer - for browsing list of records.
	 *
	 * @var int
	 */
	public $pointer;

	/**
	 * Thumbnails or not
	 *
	 * @var string
	 */
	public $imagemode;

	/**
	 * Which table to make extended listing for
	 *
	 * @var string
	 */
	public $table;

	/**
	 * Search-fields
	 *
	 * @var string
	 */
	public $search_field;

	/**
	 * Search-levels
	 *
	 * @var int
	 */
	public $search_levels;

	/**
	 * Show-limit
	 *
	 * @var int
	 */
	public $showLimit;

	/**
	 * Return URL
	 *
	 * @var string
	 */
	public $returnUrl;

	/**
	 * Clear-cache flag - if set, clears page cache for current id.
	 *
	 * @var bool
	 */
	public $clear_cache;

	/**
	 * Command: Eg. "delete" or "setCB" (for TCEmain / clipboard operations)
	 *
	 * @var string
	 */
	public $cmd;

	/**
	 * Table on which the cmd-action is performed.
	 *
	 * @var string
	 */
	public $cmd_table;

	/**
	 * Page select perms clause
	 *
	 * @var int
	 */
	public $perms_clause;

	/**
	 * Module TSconfig
	 *
	 * @var array
	 */
	public $modTSconfig;

	/**
	 * Current ids page record
	 *
	 * @var array
	 */
	public $pageinfo;

	/**
	 * Document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * Module configuration
	 *
	 * @var array
	 * @deprecated since TYPO3 CMS 7, will be removed in CMS 8.
	 */
	public $MCONF = array();

	/**
	 * Menu configuration
	 *
	 * @var array
	 */
	public $MOD_MENU = array();

	/**
	 * Module settings (session variable)
	 *
	 * @var array
	 */
	public $MOD_SETTINGS = array();

	/**
	 * Module output accumulation
	 *
	 * @var string
	 */
	public $content;

	/**
	 * The name of the module
	 *
	 * @var string
	 */
	protected $moduleName = 'web_list';

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_mod_web_list.xlf');
	}

	/**
	 * Initializing the module
	 *
	 * @return void
	 */
	public function init() {
		$this->perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
		// Get session data
		$sessionData = $GLOBALS['BE_USER']->getSessionData(\TYPO3\CMS\Recordlist\RecordList::class);
		$this->search_field = !empty($sessionData['search_field']) ? $sessionData['search_field'] : '';
		// GPvars:
		$this->id = (int)GeneralUtility::_GP('id');
		$this->pointer = GeneralUtility::_GP('pointer');
		$this->imagemode = GeneralUtility::_GP('imagemode');
		$this->table = GeneralUtility::_GP('table');
		if (!empty(GeneralUtility::_GP('search_field'))) {
			$this->search_field = GeneralUtility::_GP('search_field');
			$sessionData['search_field'] = $this->search_field;
		}
		$this->search_levels = (int)GeneralUtility::_GP('search_levels');
		$this->showLimit = GeneralUtility::_GP('showLimit');
		$this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
		$this->clear_cache = GeneralUtility::_GP('clear_cache');
		$this->cmd = GeneralUtility::_GP('cmd');
		$this->cmd_table = GeneralUtility::_GP('cmd_table');
		if (!empty(GeneralUtility::_GP('search')) && empty(GeneralUtility::_GP('search_field'))) {
			$this->search_field = '';
			$sessionData['search_field'] = $this->search_field;
		}
		// Initialize menu
		$this->menuConfig();
		// Store session data
		$GLOBALS['BE_USER']->setAndSaveSessionData(\TYPO3\CMS\Recordlist\RecordList::class, $sessionData);
	}

	/**
	 * Initialize function menu array
	 *
	 * @return void
	 */
	public function menuConfig() {
		// MENU-ITEMS:
		$this->MOD_MENU = array(
			'bigControlPanel' => '',
			'clipBoard' => '',
			'localization' => ''
		);
		// Loading module configuration:
		$this->modTSconfig = BackendUtility::getModTSconfig($this->id, 'mod.' . $this->moduleName);
		// Clean up settings:
		$this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), $this->moduleName);
	}

	/**
	 * Clears page cache for the current id, $this->id
	 *
	 * @return void
	 */
	public function clearCache() {
		if ($this->clear_cache) {
			$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
			$tce->stripslashes_values = 0;
			$tce->start(array(), array());
			$tce->clear_cacheCmd($this->id);
		}
	}

	/**
	 * Main function, starting the rendering of the list.
	 *
	 * @return void
	 */
	public function main() {
		// Start document template object:
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:recordlist/Resources/Private/Templates/db_list.html');
		$this->doc->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/AjaxDataHandler');
		// Loading current page record and checking access:
		$this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		// Apply predefined values for hidden checkboxes
		// Set predefined value for DisplayBigControlPanel:
		if ($this->modTSconfig['properties']['enableDisplayBigControlPanel'] === 'activated') {
			$this->MOD_SETTINGS['bigControlPanel'] = TRUE;
		} elseif ($this->modTSconfig['properties']['enableDisplayBigControlPanel'] === 'deactivated') {
			$this->MOD_SETTINGS['bigControlPanel'] = FALSE;
		}
		// Set predefined value for Clipboard:
		if ($this->modTSconfig['properties']['enableClipBoard'] === 'activated') {
			$this->MOD_SETTINGS['clipBoard'] = TRUE;
		} elseif ($this->modTSconfig['properties']['enableClipBoard'] === 'deactivated') {
			$this->MOD_SETTINGS['clipBoard'] = FALSE;
		}
		// Set predefined value for LocalizationView:
		if ($this->modTSconfig['properties']['enableLocalizationView'] === 'activated') {
			$this->MOD_SETTINGS['localization'] = TRUE;
		} elseif ($this->modTSconfig['properties']['enableLocalizationView'] === 'deactivated') {
			$this->MOD_SETTINGS['localization'] = FALSE;
		}

		// Initialize the dblist object:
		/** @var $dblist \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList */
		$dblist = GeneralUtility::makeInstance(\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class);
		$dblist->backPath = $GLOBALS['BACK_PATH'];
		$dblist->script = BackendUtility::getModuleUrl('web_list', array(), '');
		$dblist->calcPerms = $GLOBALS['BE_USER']->calcPerms($this->pageinfo);
		$dblist->thumbs = $GLOBALS['BE_USER']->uc['thumbnailsByDefault'];
		$dblist->returnUrl = $this->returnUrl;
		$dblist->allFields = $this->MOD_SETTINGS['bigControlPanel'] || $this->table ? 1 : 0;
		$dblist->localizationView = $this->MOD_SETTINGS['localization'];
		$dblist->showClipboard = 1;
		$dblist->disableSingleTableView = $this->modTSconfig['properties']['disableSingleTableView'];
		$dblist->listOnlyInSingleTableMode = $this->modTSconfig['properties']['listOnlyInSingleTableView'];
		$dblist->hideTables = $this->modTSconfig['properties']['hideTables'];
		$dblist->hideTranslations = $this->modTSconfig['properties']['hideTranslations'];
		$dblist->tableTSconfigOverTCA = $this->modTSconfig['properties']['table.'];
		$dblist->allowedNewTables = GeneralUtility::trimExplode(',', $this->modTSconfig['properties']['allowedNewTables'], TRUE);
		$dblist->deniedNewTables = GeneralUtility::trimExplode(',', $this->modTSconfig['properties']['deniedNewTables'], TRUE);
		$dblist->newWizards = $this->modTSconfig['properties']['newWizards'] ? 1 : 0;
		$dblist->pageRow = $this->pageinfo;
		$dblist->counter++;
		$dblist->MOD_MENU = array('bigControlPanel' => '', 'clipBoard' => '', 'localization' => '');
		$dblist->modTSconfig = $this->modTSconfig;
		$clickTitleMode = trim($this->modTSconfig['properties']['clickTitleMode']);
		$dblist->clickTitleMode = $clickTitleMode === '' ? 'edit' : $clickTitleMode;
		// Clipboard is initialized:
		// Start clipboard
		$dblist->clipObj = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Clipboard\Clipboard::class);
		// Initialize - reads the clipboard content from the user session
		$dblist->clipObj->initializeClipboard();
		// Clipboard actions are handled:
		// CB is the clipboard command array
		$CB = GeneralUtility::_GET('CB');
		if ($this->cmd == 'setCB') {
			// CBH is all the fields selected for the clipboard, CBC is the checkbox fields which were checked.
			// By merging we get a full array of checked/unchecked elements
			// This is set to the 'el' array of the CB after being parsed so only the table in question is registered.
			$CB['el'] = $dblist->clipObj->cleanUpCBC(array_merge((array)GeneralUtility::_POST('CBH'), (array)GeneralUtility::_POST('CBC')), $this->cmd_table);
		}
		if (!$this->MOD_SETTINGS['clipBoard']) {
			// If the clipboard is NOT shown, set the pad to 'normal'.
			$CB['setP'] = 'normal';
		}
		// Execute commands.
		$dblist->clipObj->setCmd($CB);
		// Clean up pad
		$dblist->clipObj->cleanCurrent();
		// Save the clipboard content
		$dblist->clipObj->endClipboard();
		// This flag will prevent the clipboard panel in being shown.
		// It is set, if the clickmenu-layer is active AND the extended view is not enabled.
		$dblist->dontShowClipControlPanels = (!$this->MOD_SETTINGS['bigControlPanel'] && $dblist->clipObj->current == 'normal' && !$this->modTSconfig['properties']['showClipControlPanelsDespiteOfCMlayers']);
		// If there is access to the page or root page is used for searching, then render the list contents and set up the document template object:
		if ($access || ($this->id === 0 && $this->search_levels > 0 && strlen($this->search_field) > 0)) {
			// Deleting records...:
			// Has not to do with the clipboard but is simply the delete action. The clipboard object is used to clean up the submitted entries to only the selected table.
			if ($this->cmd == 'delete') {
				$items = $dblist->clipObj->cleanUpCBC(GeneralUtility::_POST('CBC'), $this->cmd_table, 1);
				if (count($items)) {
					$cmd = array();
					foreach ($items as $iK => $value) {
						$iKParts = explode('|', $iK);
						$cmd[$iKParts[0]][$iKParts[1]]['delete'] = 1;
					}
					$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
					$tce->stripslashes_values = 0;
					$tce->start(array(), $cmd);
					$tce->process_cmdmap();
					if (isset($cmd['pages'])) {
						BackendUtility::setUpdateSignal('updatePageTree');
					}
					$tce->printLogErrorMessages(GeneralUtility::getIndpEnv('REQUEST_URI'));
				}
			}
			// Initialize the listing object, dblist, for rendering the list:
			$this->pointer = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->pointer, 0, 100000);
			$dblist->start($this->id, $this->table, $this->pointer, $this->search_field, $this->search_levels, $this->showLimit);
			$dblist->setDispFields();
			// Render versioning selector:
			if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('version')) {
				$dblist->HTMLcode .= $this->doc->getVersionSelector($this->id);
			}
			// Render the list of tables:
			$dblist->generateList();
			$listUrl = substr($dblist->listURL(), strlen($GLOBALS['BACK_PATH']));
			// Add JavaScript functions to the page:
			$this->doc->JScode = $this->doc->wrapScriptTags('
				function jumpExt(URL,anchor) {	//
					var anc = anchor?anchor:"";
					window.location.href = URL+(T3_THIS_LOCATION?"&returnUrl="+T3_THIS_LOCATION:"")+anc;
					return false;
				}
				function jumpSelf(URL) {	//
					window.location.href = URL+(T3_RETURN_URL?"&returnUrl="+T3_RETURN_URL:"");
					return false;
				}

				function setHighlight(id) {	//
					top.fsMod.recentIds["web"]=id;
					top.fsMod.navFrameHighlightedID["web"]="pages"+id+"_"+top.fsMod.currentBank;	// For highlighting

					if (top.content && top.content.nav_frame && top.content.nav_frame.refresh_nav) {
						top.content.nav_frame.refresh_nav();
					}
				}
				' . $this->doc->redirectUrls($listUrl) . '
				' . $dblist->CBfunctions() . '
				function editRecords(table,idList,addParams,CBflag) {	//
					window.location.href="' . $GLOBALS['BACK_PATH'] . 'alt_doc.php?returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')) . '&edit["+table+"]["+idList+"]=edit"+addParams;
				}
				function editList(table,idList) {	//
					var list="";

						// Checking how many is checked, how many is not
					var pointer=0;
					var pos = idList.indexOf(",");
					while (pos!=-1) {
						if (cbValue(table+"|"+idList.substr(pointer,pos-pointer))) {
							list+=idList.substr(pointer,pos-pointer)+",";
						}
						pointer=pos+1;
						pos = idList.indexOf(",",pointer);
					}
					if (cbValue(table+"|"+idList.substr(pointer))) {
						list+=idList.substr(pointer)+",";
					}

					return list ? list : idList;
				}

				if (top.fsMod) top.fsMod.recentIds["web"] = ' . (int)$this->id . ';
			');
			// Setting up the context sensitive menu:
			$this->doc->getContextMenuCode();
		}
		// access
		// Begin to compile the whole page, starting out with page header:
		if (!$this->id) {
			$this->body = $this->doc->header($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
		} else {
			$this->body = $this->doc->header($this->pageinfo['title']);
		}

		if (!empty($dblist->HTMLcode)) {
			$output = $dblist->HTMLcode;
		} else {
			$output = $flashMessage = GeneralUtility::makeInstance(
				FlashMessage::class,
				$GLOBALS['LANG']->getLL('noRecordsOnThisPage'),
				'',
				FlashMessage::INFO
			)->render();
		}

		$this->body .= '<form action="' . htmlspecialchars($dblist->listURL()) . '" method="post" name="dblistForm">';
		$this->body .= $output;
		$this->body .= '<input type="hidden" name="cmd_table" /><input type="hidden" name="cmd" /></form>';
		// If a listing was produced, create the page footer with search form etc:
		if ($dblist->HTMLcode) {
			// Making field select box (when extended view for a single table is enabled):
			if ($dblist->table) {
				$this->body .= $dblist->fieldSelectBox($dblist->table);
			}
			// Adding checkbox options for extended listing and clipboard display:
			$this->body .= '

					<!--
						Listing options for extended view, clipboard and localization view
					-->
					<div id="typo3-listOptions">
						<form action="" method="post">';

			// Add "display bigControlPanel" checkbox:
			if ($this->modTSconfig['properties']['enableDisplayBigControlPanel'] === 'selectable') {
				$this->body .= '<div class="checkbox">' .
					'<label for="checkLargeControl">' .
					BackendUtility::getFuncCheck($this->id, 'SET[bigControlPanel]', $this->MOD_SETTINGS['bigControlPanel'], '', $this->table ? '&table=' . $this->table : '', 'id="checkLargeControl"') .
					BackendUtility::wrapInHelp('xMOD_csh_corebe', 'list_options', $GLOBALS['LANG']->getLL('largeControl', TRUE)) .
					'</label>' .
					'</div>';
			}

			// Add "clipboard" checkbox:
			if ($this->modTSconfig['properties']['enableClipBoard'] === 'selectable') {
				if ($dblist->showClipboard) {
					$this->body .= '<div class="checkbox">' .
						'<label for="checkShowClipBoard">' .
						BackendUtility::getFuncCheck($this->id, 'SET[clipBoard]', $this->MOD_SETTINGS['clipBoard'], '', $this->table ? '&table=' . $this->table : '', 'id="checkShowClipBoard"') .
						BackendUtility::wrapInHelp('xMOD_csh_corebe', 'list_options', $GLOBALS['LANG']->getLL('showClipBoard', TRUE)) .
						'</label>' .
						'</div>';
				}
			}

			// Add "localization view" checkbox:
			if ($this->modTSconfig['properties']['enableLocalizationView'] === 'selectable') {
				$this->body .= '<div class="checkbox">' .
					'<label for="checkLocalization">' .
					BackendUtility::getFuncCheck($this->id, 'SET[localization]', $this->MOD_SETTINGS['localization'], '', $this->table ? '&table=' . $this->table : '', 'id="checkLocalization"') .
					BackendUtility::wrapInHelp('xMOD_csh_corebe', 'list_options', $GLOBALS['LANG']->getLL('localization', TRUE)) .
					'</label>' .
					'</div>';
			}

			$this->body .= '
						</form>
					</div>';
		}
		// Printing clipboard if enabled
		if ($this->MOD_SETTINGS['clipBoard'] && $dblist->showClipboard && ($dblist->HTMLcode || $dblist->clipObj->hasElements())) {
			$this->body .= '<div class="db_list-dashboard">' . $dblist->clipObj->printClipboard() . '</div>';
		}
		// Additional footer content
		$footerContentHook = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['recordlist/mod1/index.php']['drawFooterHook'];
		if (is_array($footerContentHook)) {
			foreach ($footerContentHook as $hook) {
				$params = array();
				$this->body .= GeneralUtility::callUserFunction($hook, $params, $this);
			}
		}
		// Setting up the buttons and markers for docheader
		$docHeaderButtons = $dblist->getButtons();
		$markers = array(
			'CSH' => $docHeaderButtons['csh'],
			'CONTENT' => $this->body,
			'EXTRACONTAINERCLASS' => $this->table ? 'singletable' : '',
			'BUTTONLIST_ADDITIONAL' => '',
			'SEARCHBOX' => '',
			'BUTTONLIST_ADDITIONAL' => ''
		);
		// searchbox toolbar
		if (!$this->modTSconfig['properties']['disableSearchBox'] && ($dblist->HTMLcode || !empty($dblist->searchString))) {
			$markers['SEARCHBOX'] = $dblist->getSearchBox();
			$markers['BUTTONLIST_ADDITIONAL'] = '<a href="#" onclick="toggleSearchToolbox(); return false;" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.title.searchIcon', TRUE) . '">'.\TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('apps-toolbar-menu-search').'</a>';
		}
		// Build the <body> for the module
		$this->content = $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		// Renders the module page
		$this->content = $this->doc->render('DB list', $this->content);
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

}
