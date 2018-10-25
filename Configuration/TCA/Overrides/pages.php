<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TCA']['pages']['columns']['fe_group']['config']['foreign_table_where'] = ' AND fe_groups.sys_language_uid IN (-1,0) ORDER BY fe_groups.title';