<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Adminhtml\Virtual;

use PayPal\Braintree\Block\Payment;

/**
 * @api
 * @since 100.0.2
 */
class Script extends Payment
{
    /**
     * Get method code
     *
     * @return string
     */
    public function getMethodCode(): string
    {
        return 'braintree';
    }

    /**
     * Check if vault enabled
     *
     * @return bool
     */
    public function isVaultEnabled(): bool
    {
        return false;
    }

    /**
     * Has verification
     *
     * @return bool
     */
    public function hasVerification(): bool
    {
        return true;
    }
}
