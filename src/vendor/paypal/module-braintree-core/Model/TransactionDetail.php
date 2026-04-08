<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model;

use Magento\Framework\Model\AbstractModel;
use PayPal\Braintree\Api\Data\TransactionDetailDataInterface;
use PayPal\Braintree\Model\ResourceModel\TransactionDetail as TransactionDetailResource;

class TransactionDetail extends AbstractModel implements TransactionDetailDataInterface
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void // @codingStandardsIgnoreLine
    {
        $this->_init(TransactionDetailResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getId(): int|string|null
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): int
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function getTransactionSource(): string
    {
        return $this->getData(self::TRANSACTION_SOURCE);
    }

    /**
     * @inheritdoc
     */
    public function setId($id): TransactionDetailDataInterface
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(int $orderId): TransactionDetailDataInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function setTransactionSource(string $transactionSource): TransactionDetailDataInterface
    {
        return $this->setData(self::TRANSACTION_SOURCE, $transactionSource);
    }
}
