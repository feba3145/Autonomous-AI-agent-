<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Framework\Data\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as SalesOrderGridCollection;

class SalesOrderExportPlugin
{
    /**
     * Get Report
     *
     * @param CollectionFactory $subject
     * @param Collection $result
     * @param string|null $requestName
     * @return Collection
     */
    public function afterGetReport(CollectionFactory $subject, Collection $result, ?string $requestName): Collection
    {
        if ($requestName === 'sales_order_grid_data_source' && $result instanceof SalesOrderGridCollection) {
            $tableName = $result->getResource()->getTable('braintree_transaction_details');
            $result->getSelect()->joinLeft(
                $tableName,
                $tableName . '.order_id = main_table.entity_id',
                $tableName . '.transaction_source'
            );
        }
        return $result;
    }
}
