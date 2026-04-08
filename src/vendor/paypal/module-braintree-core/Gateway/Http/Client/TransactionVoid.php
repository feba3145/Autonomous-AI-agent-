<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Http\Client;

use Braintree\Result\Error;
use Braintree\Result\Successful;

class TransactionVoid extends AbstractTransaction
{
    /**
     * Process http request
     *
     * @param array $data
     * @return Error|Successful
     */
    protected function process(array $data)
    {
        return $this->adapter->void($data['transaction_id']);
    }
}
