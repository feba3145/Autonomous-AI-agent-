<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\GooglePay;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_SKIP_ORDER_REVIEW_STEP = 'skip_order_review_step';

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->getValue(self::KEY_SKIP_ORDER_REVIEW_STEP);
    }
}
