<?php
namespace SJBR\SrFeuserRegister\Utility;

/*
 *  Copyright notice
 *
 *  (c) 2018 Stanislas Rolland <typo3(arobas)sjbr.ca>
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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Data access utility
 */
class DataUtility
{
    /**
     * Selects records based on matching a field (ei. other than UID) with a value
     *
     * @param string $theTable The table name to search, eg. "pages" or "tt_content
     * @param string $theField The fieldname to match, eg. "uid" or "alias
     * @param string $theValue The value that fieldname must match, eg. "123" or "frontpage
     * @param string $whereClause Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
     * @param string $groupBy Optional GROUP BY field(s). If none, supply blank string.
     * @param string $orderBy Optional ORDER BY field(s). If none, supply blank string.
     * @param string $limit Optional LIMIT value ([begin,]max). If none, supply blank string.
     * @return mixed Returns array (the record) if found, otherwise nothing (void)
     * @see since TYPO3 9 TYPO3\CMS\Frontend\Page\PageRepository
     */
    public static function getRecordsByField($theTable, $theField, $theValue, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '')
    {
        if (is_array($GLOBALS['TCA'][$theTable])) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($theTable);
            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            $queryBuilder->select('*')
                ->from($theTable)
                ->where($queryBuilder->expr()->eq($theField, $queryBuilder->createNamedParameter($theValue)));

            if ($whereClause !== '') {
                $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($whereClause));
            }

            if ($groupBy !== '') {
                $queryBuilder->groupBy(QueryHelper::parseGroupBy($groupBy));
            }

            if ($orderBy !== '') {
                foreach (QueryHelper::parseOrderBy($orderBy) as $orderPair) {
                    list($fieldName, $order) = $orderPair;
                    $queryBuilder->addOrderBy($fieldName, $order);
                }
            }

            if ($limit !== '') {
                if (strpos($limit, ',')) {
                    $limitOffsetAndMax = GeneralUtility::intExplode(',', $limit);
                    $queryBuilder->setFirstResult((int)$limitOffsetAndMax[0]);
                    $queryBuilder->setMaxResults((int)$limitOffsetAndMax[1]);
                } else {
                    $queryBuilder->setMaxResults((int)$limit);
                }
            }

            $rows = $queryBuilder->execute()->fetchAll();

            if (!empty($rows)) {
                return $rows;
            }
        }
        return null;
    }
}