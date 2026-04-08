<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\InstantPurchase\CreditCard;

use PayPal\Braintree\Gateway\Config\Config;
use Magento\InstantPurchase\PaymentMethodIntegration\AvailabilityCheckerInterface;

/**
 * Check availability of Braintree vaulted cards for Instant Purchase
 *
 * Class AvailabilityChecker
 */
class AvailabilityChecker implements AvailabilityCheckerInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * AvailabilityChecker constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        if ($this->config->isVerify3DSecure()) {
            // Support of 3D secure has not been implemented for instant purchase yet.
            return false;
        }

        return true;
    }
}
