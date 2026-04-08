<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\PayPalCredit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config implements ConfigInterface
{
    public const KEY_ACTIVE = 'active';
    public const KEY_UK_ACTIVATION_CODE = 'uk_activation_code';
    public const KEY_UK_MERCHANT_NAME = 'uk_merchant_name';
    public const KEY_CLIENT_ID = 'client_id';
    public const KEY_SECRET = 'secret';
    public const KEY_SANDBOX = 'sandbox';
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
     * Sets method code
     *
     * @param string $methodCode
     * @return void
     */
    public function setMethodCode($methodCode): void
    {
        $this->methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     * @return void
     */
    public function setPathPattern($pathPattern): void
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null): mixed
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
     * Get configuration field value
     *
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue(string $field, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            $field,
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
        $paypalActive = $this->getConfigValue(
            'payment/braintree_paypal/active',
            $storeId
        );
        $paypalCreditActive = $this->getConfigValue(
            'payment/braintree_paypal_credit/active',
            $storeId
        );

        // If PayPal or PayPal Credit is disabled in the admin
        if (!$paypalActive || !$paypalCreditActive) {
            return false;
        }

        // Only allowed on US and UK
        if (!$this->isUk($storeId) && !$this->isUS($storeId)) {
            return false;
        }

        // Validate configuration if UK
        if ($this->isUk($storeId)) {
            if ($this->isSandbox($storeId)) {
                $merchantId = substr(
                    $this->getConfigValue('payment/braintree/sandbox_merchant_id', $storeId),
                    -4
                );
            } else {
                $merchantId = substr(
                    $this->getConfigValue('payment/braintree/merchant_id', $storeId),
                    -4
                );
            }
            return $merchantId === $this->getActivationCode($storeId) && $this->getMerchantName($storeId);
        }

        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Calculator is only used on UK view
     *
     * @return bool
     */
    public function isCalculatorEnabled(): bool
    {
        return ($this->isUk() && $this->isActive());
    }

    /**
     * UK Merchant Name
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getMerchantName(?int $storeId = null): ?string
    {
        return $this->getValue(self::KEY_UK_MERCHANT_NAME, $storeId);
    }

    /**
     * UK Activation Code
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getActivationCode(?int $storeId = null): ?string
    {
        return $this->getValue(self::KEY_UK_ACTIVATION_CODE, $storeId);
    }

    /**
     * PayPal Sandbox mode
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSandbox(?int $storeId = null): bool
    {
        return self::KEY_SANDBOX === $this->getConfigValue('payment/braintree/environment', $storeId);
    }

    /**
     * Client ID
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->getValue(self::KEY_CLIENT_ID);
    }

    /**
     * Secret Key
     *
     * @return string|null
     */
    public function getSecret(): ?string
    {
        return $this->getValue(self::KEY_SECRET);
    }

    /**
     * Merchant Country set to GB/UK
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUk(?int $storeId = null): bool
    {
        return 'GB' === $this->getMerchantCountry($storeId);
    }

    /**
     * Merchant Country set to US
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUS(?int $storeId = null): bool
    {
        return 'US' === $this->getMerchantCountry($storeId);
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
}
