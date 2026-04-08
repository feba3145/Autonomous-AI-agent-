<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Request;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TransactionSourceDataBuilder implements BuilderInterface
{
    public const TRANSACTION_SOURCE = 'transactionSource';

    /**
     * @var State $state
     */
    private State $state;

    /**
     * TransactionSourceDataBuilder constructor
     *
     * @param State $state
     */
    public function __construct(State $state)
    {
        $this->state = $state;
    }

    /**
     * Set TRANSACTION_SOURCE to moto if within the admin
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build(array $buildSubject): array
    {
        if ($this->state->getAreaCode() === Area::AREA_ADMINHTML) {
            return [
                self::TRANSACTION_SOURCE => 'moto'
            ];
        }

        return [];
    }
}
