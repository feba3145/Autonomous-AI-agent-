<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class SettlementDataBuilder implements BuilderInterface
{
    public const SUBMIT_FOR_SETTLEMENT = 'submitForSettlement';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        return [
            'options' => [
                self::SUBMIT_FOR_SETTLEMENT => true
            ]
        ];
    }
}
