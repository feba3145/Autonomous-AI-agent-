<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Zend_Db_Select_Exception;

class SalesOrderGridPlugin
{
    /**
     * Join the fields to render the value in order grid
     *
     * @param Collection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Select_Exception
     */
    public function beforeLoad(Collection $subject, bool $printQuery = false, bool $logQuery = false): array
    {
        if (!$subject->isLoaded()) {
            $primaryKey = $subject->getResource()->getIdFieldName();
            $tableName = $subject->getResource()->getTable('braintree_transaction_details');
            $salesOrderTable = $subject->getResource()->getTable('sales_order');

            if (!array_key_exists('braintree_transaction_details', $subject->getSelect()->getPart('from'))) {
                $subject->getSelect()->joinLeft(
                    $tableName,
                    $tableName . '.order_id = main_table.' . $primaryKey,
                    $tableName . '.transaction_source'
                );
            }
            $subject->getSelect()->joinLeft(
                $salesOrderTable,
                $salesOrderTable . '.entity_id = main_table.' . $primaryKey,
                $salesOrderTable . '.dispute_status'
            );
            $wherePart = $subject->getSelect()->getPart('where');
            if (!empty($wherePart)) {
                /** @var string $condition */
                foreach ($wherePart as $key => $condition) {
                    if (str_contains($condition, '`main_table`.`dispute_status`')) {
                        $wherePart[$key] = str_replace(
                            "`main_table`.`dispute_status`",
                            "`sales_order`.`dispute_status`",
                            $condition
                        );
                    }
                    if (!str_contains($condition, "`created_at`")) {
                        continue;
                    }
                    $wherePart[$key] = str_replace("`created_at`", "`main_table`.`created_at`", $condition);
                }
                $subject->getSelect()->setPart('where', $wherePart);
            }
        }

        return [$printQuery, $logQuery];
    }
}
