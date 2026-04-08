<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\InstantPurchase\CreditCard;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Braintree vaulted credit cards formatter
 *
 * Class TokenFormatter
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    /**
     * Most used credit card types
     * @var array
     */
    public static array $baseCardTypes = [
        'AE' => 'American Express',
        'VI' => 'Visa',
        'MC' => 'MasterCard',
        'DI' => 'Discover',
        'JBC' => 'JBC',
        'MI' => 'Maestro',
    ];

    /**
     * @inheritdoc
     */
    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
            throw new \InvalidArgumentException('Invalid Braintree credit card token details.');
        }

        $ccType = self::$baseCardTypes[$details['type']] ?? $details['type'];

        return sprintf(
            '%s: %s, %s: %s (%s: %s)',
            __('Credit Card'),
            $ccType,
            __('ending'),
            $details['maskedCC'],
            __('expires'),
            $details['expirationDate']
        );
    }
}
