<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Api\Data;

/**
 * Interface TransactionDetail
 **/
interface TransactionDetailDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const TRANSACTION_SOURCE = 'transaction_source';

    /**
     * Get transaction id
     *
     * @return int|string|null
     */
    public function getId(): int|string|null;

    /**
     * Get order id
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Get transaction source
     *
     * @return string
     */
    public function getTransactionSource(): string;

    /**
     * Set transaction id
     *
     * @param mixed $id
     * @return self
     */
    public function setId($id): TransactionDetailDataInterface;

    /**
     * Set order id
     *
     * @param int $orderId
     * @return self
     */
    public function setOrderId(int $orderId): TransactionDetailDataInterface;

    /**
     * Set transaction source
     *
     * @param string $transactionSource
     * @return self
     */
    public function setTransactionSource(string $transactionSource): TransactionDetailDataInterface;
}
