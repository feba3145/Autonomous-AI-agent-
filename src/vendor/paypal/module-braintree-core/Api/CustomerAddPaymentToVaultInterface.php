<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Api;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Validation\ValidationException;
use PayPal\Braintree\Api\Data\PaymentInterface;

interface CustomerAddPaymentToVaultInterface
{
    /**
     * Vault a Payment nonce for a customer.
     *
     * Billing address is optional but advised for Card vaulting.
     *
     * @param int $customerId
     * @param PaymentInterface $payment
     * @param AddressInterface|null $billingAddress
     * @return bool
     * @throws ValidationException
     */
    public function execute(
        int $customerId,
        PaymentInterface $payment,
        ?AddressInterface $billingAddress = null
    ): bool;
}
