<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Http\Client;

use PayPal\Braintree\Gateway\Request\CaptureDataBuilder;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;

class TransactionSubmitForPartialSettlement extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(array $data)
    {
        return  $this->adapter->submitForPartialSettlement(
            $data[CaptureDataBuilder::TRANSACTION_ID],
            (float) $data[PaymentDataBuilder::AMOUNT],
            [PaymentDataBuilder::ORDER_ID => $data[PaymentDataBuilder::ORDER_ID]]
        );
    }
}
