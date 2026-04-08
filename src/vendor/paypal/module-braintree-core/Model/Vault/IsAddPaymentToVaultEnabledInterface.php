<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Vault;

interface IsAddPaymentToVaultEnabledInterface
{
    /**
     * Is adding payment method to vault enabled.
     *
     * @param string $paymentMethod
     * @param int|null $storeId
     * @return bool
     */
    public function execute(string $paymentMethod, ?int $storeId = null): bool;
}
