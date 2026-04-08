<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Vault\PaymentToken;

use Magento\Vault\Api\Data\PaymentTokenInterface;

interface SaveInterface
{
    /**
     * Save a payment token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return bool
     */
    public function execute(PaymentTokenInterface $paymentToken): bool;
}
