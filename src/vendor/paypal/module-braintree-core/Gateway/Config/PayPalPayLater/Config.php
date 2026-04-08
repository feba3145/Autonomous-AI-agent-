<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\PayPalPayLater;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;
use \Magento\Paypal\Model\Config as PPConfig;

class Config implements ConfigInterface
{
    public const KEY_ACTIVE = 'active';
    public const DEFAULT_PATH_PATTERN = 'payment/%s/%s';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var string|null
     */
    private ?string $methodCode;

    /**
     * @var string|null
     */
    private ?string $pathPattern;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->methodCode = $methodCode;
        $this->pathPattern = $pathPattern;
    }

    /**
     * @inheritdoc
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * @inheritdoc
     */
    public function setMethodCode($methodCode)
    {
        $this->methodCode = $methodCode;
    }

    /**
     * Get configuration field value
     *
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue(string $field, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritdoc
     */
    public function getValue($field, $storeId = null)
    {
        if (null === $this->methodCode || null === $this->pathPattern) {
            return null;
        }

        return $this->scopeConfig->getValue(
            sprintf($this->pathPattern, $this->methodCode, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Payment configuration status
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive(?int $storeId = null): bool
    {
        $paypalActive = $this->getConfigValue('payment/braintree_paypal/active', $storeId);
        $paypalPayLaterActive = $this->getConfigValue('payment/braintree_paypal_paylater/active', $storeId);

        // If PayPal or PayPal Pay Later is disabled in the admin
        if (!$paypalActive || !$paypalPayLaterActive) {
            return false;
        }

        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Get PayPal pay later message configuration status
     *
     * @param string $buttonType
     * @return bool
     */
    public function isMessageActive(string $buttonType): bool
    {
        $paypalActive = $this->getConfigValue("payment/braintree_paypal/active");
        $paypalPayLaterMessageActive = $this->getConfigValue(
            "payment/braintree_paypal/button_location_" . $buttonType . "_type_messaging_show"
        );
        // If PayPal or PayPal Pay Later is disabled in the admin
        if (!$paypalActive || !$paypalPayLaterMessageActive) {
            return false;
        }

        if (!in_array($this->getMerchantCountry(), ['GB','FR','US','DE', 'AU', 'ES', 'IT'])) {
            return false;
        }

        return (bool) $paypalPayLaterMessageActive;
    }

    /**
     * Get PayPal pay later button configuration status
     *
     * @param string $buttonType
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isButtonActive(string $buttonType): bool
    {
        $paypalActive = $this->getConfigValue("payment/braintree_paypal/active");
        $paypalPayLaterActive = $this->getConfigValue("payment/braintree_paypal_paylater/active");
        $paypalPayLaterButtonShow = $this->getConfigValue(
            "payment/braintree_paypal/button_location_checkout_type_paylater_show"
        );

        // If PayPal or PayPal Pay Later is disabled in the admin
        if (!$paypalActive || !$paypalPayLaterActive || !$paypalPayLaterButtonShow) {
            return false;
        }

        return (bool) $paypalPayLaterButtonShow;
    }

    /**
     * Merchant Country set to US
     *
     * @return bool
     */
    public function isUS(): bool
    {
        return 'US' === $this->getMerchantCountry();
    }

    /**
     * Merchant Country
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getMerchantCountry(?int $storeId = null): ?string
    {
        return $this->getConfigValue('paypal/general/merchant_country', $storeId);
    }

    /**
     * Get PayPal Vault status
     *
     * @return bool
     */
    public function isPayPalVaultActive(): bool
    {
        return (bool) $this->getConfigValue('payment/braintree_paypal_vault/active');
    }
}
