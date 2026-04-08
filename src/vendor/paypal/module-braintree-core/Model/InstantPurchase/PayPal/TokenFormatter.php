<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\InstantPurchase\PayPal;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Braintree PayPal token formatter
 *
 * Class TokenFormatter
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    /**
     * @inheritdoc
     */
    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        if (!isset($details['payerEmail'])) {
            throw new \InvalidArgumentException('Invalid Braintree PayPal token details.');
        }

        return sprintf(
            '%s: %s',
            __('PayPal'),
            $details['payerEmail']
        );
    }
}
