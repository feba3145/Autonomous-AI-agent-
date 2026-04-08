<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Api;

use PayPal\Braintree\Api\Data\CreditPriceDataInterface;
use Magento\Framework\DataObject;

/**
 * Interface CreditPricesInterface
 * @api
 **/
interface CreditPriceRepositoryInterface
{
    /**
     * Save credit price
     *
     * @param CreditPriceDataInterface $entity
     * @return CreditPriceDataInterface
     */
    public function save(CreditPriceDataInterface $entity): CreditPriceDataInterface;

    /**
     * Get credit price by product id
     *
     * @param int $productId
     * @return CreditPriceDataInterface[]|DataObject[]
     */
    public function getByProductId(int $productId): array;

    /**
     * Get the cheapest by product id
     *
     * @param int $productId
     * @return CreditPriceDataInterface|DataObject
     */
    public function getCheapestByProductId(int $productId): CreditPriceDataInterface|DataObject;

    /**
     * Delete the credit price
     *
     * @param int $productId
     * @return CreditPriceDataInterface[]
     */
    public function deleteByProductId(int $productId): array;
}
