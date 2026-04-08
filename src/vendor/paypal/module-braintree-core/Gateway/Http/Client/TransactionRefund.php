<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Http\Client;

use Braintree\Result\Error;
use Braintree\Result\Successful;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;

class TransactionRefund extends AbstractTransaction
{
    /**
     * Process http request
     *
     * @param array $data
     * @return Error|Successful
     */
    protected function process(array $data)
    {
        return $this->adapter->refund(
            $data['transaction_id'],
            (float) $data[PaymentDataBuilder::AMOUNT]
        );
    }
}
