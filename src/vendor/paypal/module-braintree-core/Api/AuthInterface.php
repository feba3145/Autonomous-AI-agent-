<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Api;

/**
 * Interface AuthInterface
 * @api
 **/
interface AuthInterface
{
    /**
     * Returns details required to be able to submit a payment with Apple Pay
     *
     * @return \PayPal\Braintree\Api\Data\AuthDataInterface
     */
    public function get(): \PayPal\Braintree\Api\Data\AuthDataInterface;
}
