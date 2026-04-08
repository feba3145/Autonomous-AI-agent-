<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Vault\PaymentToken;

use Magento\Vault\Api\Data\PaymentTokenInterface;

interface GeneratePublicHashInterface
{
    /**
     * Generate a public hash key.
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    public function execute(PaymentTokenInterface $paymentToken): string;
}
