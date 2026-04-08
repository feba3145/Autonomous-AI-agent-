<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);
namespace Magento\PaymentServicesBase\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config as AppConfig;
use Psr\Log\LoggerInterface;

class Config
{
    private const EXTENSION_PACKAGE_NAME = 'magento/payment-services';
    private const CONFIG_PATH_ENVIRONMENT = 'payment/payment_methods/method';
    private const CONFIG_PATH_MERCHANT_ID = 'payment/payment_methods/%s_merchant_id';
    private const CONFIG_PATH_BUYER_COUNTRY = 'payment/payment_methods/buyer_country';
    private const VERSION_CACHE_KEY = 'payment-services-version';

    private const VALID_MBA_SCOPING_LEVELS = [
        ScopeInterface::SCOPE_WEBSITE,
        ScopeInterface::SCOPE_STORE,
    ];
    private const SANDBOX_ENVIRONMENT = 'sandbox';
    private const PRODUCTION_ENVIRONMENT = 'production';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ServicesConfigInterface
     */
    private $servicesConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Extension version
     *
     * @var string
     */
    private $version;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var ComposerInformation
     */
    private $composerInformation;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ServicesConfigInterface $servicesConfig
     * @param StoreManagerInterface $storeManager
     * @param CacheInterface $cache
     * @param ComposerInformation $composerInformation
     * @param LoggerInterface $log
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ServicesConfigInterface $servicesConfig,
        StoreManagerInterface $storeManager,
        CacheInterface $cache,
        ComposerInformation $composerInformation,
        LoggerInterface $log,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->servicesConfig = $servicesConfig;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
        $this->composerInformation = $composerInformation;
        $this->log = $log;
    }

    /**
     * Retrieve merchant ID from config
     *
     * @param string $environment
     * @param int|null $storeId
     * @return string
     */
    public function getMerchantId(string $environment = '', ?int $storeId = null) : string
    {
        $environment = $environment ?: $this->getEnvironmentType($storeId);
        return (string) $this->scopeConfig->getValue(
            $this->getMerchantIdConfigPath($environment),
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get SaaS environment ID
     *
     * @return null|string
     */
    public function getServicesEnvironmentId() : ?string
    {
        return $this->servicesConfig->getEnvironmentId();
    }

    /**
     * Get SaaS environment type
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironmentType($storeId = null) : string
    {
        $storeCode = $this->storeManager->getStore($storeId)->getCode();
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_ENVIRONMENT,
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );
    }

    /**
     * Get environment types for all the websites for report display
     *
     * @return string
     */
    public function getEnvironmentTypeAcrossWebsites() : string
    {
        $environment = 'sandbox';
        $websites = $this->storeManager->getWebsites();
        foreach ($websites as $website) {
            $websiteEnvironment = (string) $this->scopeConfig->getValue(
                self::CONFIG_PATH_ENVIRONMENT,
                ScopeInterface::SCOPE_WEBSITE,
                $website->getId()
            );
            if ($websiteEnvironment === 'production') {
                $environment = $websiteEnvironment;
                break;
            }
        }
        return $environment;
    }

    /**
     * Get config path for merchant ID
     *
     * @param string $environment
     * @return string
     */
    private function getMerchantIdConfigPath(string $environment): string
    {
        return sprintf(self::CONFIG_PATH_MERCHANT_ID, $environment);
    }

    /**
     * Returns the configured multi business account scoping level.
     *
     * Defaults to "website" in case of missing or invalid configuration.
     *
     * @return "website"|"store"
     */
    public function getMultiBusinessAccountScopingLevel(): string
    {
        $value = $this->scopeConfig->getValue('payment/payment_services/mba_scoping_level');
        if (!in_array($value, self::VALID_MBA_SCOPING_LEVELS)) {
            if (!empty($value)) {
                $this->log->warning(
                    'Invalid "payment/payment_services/mba_scoping_level" value: "' . $value . '".
                    Falling back to "website".'
                );
            }
            return ScopeInterface::SCOPE_WEBSITE;
        }
        return $value;
    }

    /**
     * Check is Services Connector is configured.
     *
     * We only check if the keys are configured for the environment, not that they are valid
     * Validation of the keys are done in the Saas side
     *
     * @param string $environment
     * @return bool
     */
    public function isServicesConnectorConfigured(string $environment = '') : bool
    {
        $environment = $environment ?: $this->getEnvironmentType();

        if ($environment === self::SANDBOX_ENVIRONMENT) {
            return $this->servicesConfig->getSandboxApiKey() !== null
                && $this->servicesConfig->getSandboxPrivateKey() !== '';
        }

        if ($environment === self::PRODUCTION_ENVIRONMENT) {
            return $this->servicesConfig->getProductionApiKey() !== null
                && $this->servicesConfig->getProductionPrivateKey() !== '';
        }

        return false;
    }

    /**
     * Check if payments is enabled
     *
     * @param string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null) : bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/payment_services/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if payments is configured
     *
     * @param string $environment
     * @param int|null $store
     * @return bool
     */
    public function isConfigured(string $environment = '', ?int $store = null) : bool
    {
        return $this->isEnabled($store)
            && $this->getMerchantId($environment, $store)
            && $this->isServicesConnectorConfigured($environment);
    }

    /**
     * Get extension version
     *
     * @return string
     */
    public function getVersion(): string
    {
        $this->version = $this->version ?: $this->cache->load(self::VERSION_CACHE_KEY);
        if (!$this->version) {
            $installedPackages = $this->composerInformation->getInstalledMagentoPackages();
            $extensionVersion = $installedPackages[self::EXTENSION_PACKAGE_NAME]['version'] ?? null;
            if (!empty($extensionVersion)) {
                $this->version = $extensionVersion;
            } else {
                $this->version = 'UNKNOWN';
            }
            $this->cache->save($this->version, self::VERSION_CACHE_KEY, [AppConfig::CACHE_TAG]);
        }
        return $this->version;
    }

    /**
     * Return the Payment Services SDK url
     *
     * @return string
     */
    public function getPaymentSDKUrl(): string
    {
        $sdkUrl = $this->scopeConfig->getValue('payment/payment_services/sdk_url');
        return $sdkUrl . '?ext=' . $this->getVersion();
    }

    /**
     * Return the Payment Services SDK Fallback url
     *
     * @return string
     */
    public function getPaymentSDKFallbackUrl(): string
    {
        $sdkUrl = $this->scopeConfig->getValue('payment/payment_services/sdk_fallback_url');
        return $sdkUrl . '?ext=' . $this->getVersion();
    }

    /**
     * Get buyer country config value
     *
     * @param int|null $storeId
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getBuyerCountry(?int $storeId = null): ?string
    {
        $storeCode = $this->storeManager->getStore($storeId)->getCode();
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_BUYER_COUNTRY,
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );
    }
}
