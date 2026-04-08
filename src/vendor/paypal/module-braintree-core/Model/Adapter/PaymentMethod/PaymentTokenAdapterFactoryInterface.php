<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Adapter\PaymentMethod;

use Braintree\CreditCard;
use Braintree\PayPalAccount;

interface PaymentTokenAdapterFactoryInterface
{
    /**
     * Create payment token adapter
     *
     * @param string $paymentMethodCode
     * @param CreditCard|PayPalAccount $paymentMethod
     * @return PaymentTokenAdapterInterface
     */
    public function create(
        string $paymentMethodCode,
        CreditCard|PayPalAccount $paymentMethod
    ): PaymentTokenAdapterInterface;
}
