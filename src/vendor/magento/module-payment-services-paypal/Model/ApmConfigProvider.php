<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\ConfigProvider as BaseConfigProvider;
use Magento\PaymentServicesPaypal\Model\Adminhtml\Source\ApmMethods;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

class ApmConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'apm';
    private const LOCATION = 'checkout_apm';

    private const THRESHOLD_BANCONTACT = ['min' => 1];
    private const THRESHOLD_BLIK = ['min' => 1];
    private const THRESHOLD_EPS = ['min' => 1];
    private const THRESHOLD_IDEAL = ['min' => 0.01];
    private const THRESHOLD_MYBANK = [];
    private const THRESHOLD_P24 = ['min' => 1];

    /**
     * @param Config $config
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     * @param BaseConfigProvider $configProvider
     * @param ApmMethods $apmMethods
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly BaseConfig $baseConfig,
        private readonly BaseConfigProvider $configProvider,
        private readonly ApmMethods $apmMethods,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     *
     * @throws NoSuchEntityException|LocalizedException
     */
    public function getConfig(): array
    {
        $config = $this->configProvider->getConfig();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $allowedMethods = $this->config->getAllowedApmMethods($storeId);
        if (!$this->baseConfig->isConfigured()
            || !$this->config->isApmEnabled($storeId)
            || empty($allowedMethods)
        ) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['location'] = Config::CHECKOUT_CHECKOUT_LOCATION;
        $config['payment'][self::CODE]['sdkParams'] = $this->configProvider->getScriptParams(
            self::CODE,
            self::LOCATION,
            $this->getPaymentOptions($storeId, $allowedMethods)
        );
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['allowedMethods'] = $allowedMethods;
        $config['payment'][self::CODE]['titles'] = $this->getApmTitles();
        $config['payment'][self::CODE]['thresholds'] = $this->getApmThresholds();

        return $config;
    }

    /**
     * Get payment options
     *
     * @param int|null $storeId
     * @param string|null $allowedMethods
     * @return PaymentOptionsBuilder
     * @throws NoSuchEntityException
     */
    private function getPaymentOptions(?int $storeId, ?string $allowedMethods): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder = $this->configProvider->getPaymentOptions();

        if ($this->config->isApmEnabled($storeId) && !empty($allowedMethods)) {
            $selectedMethods = explode(',', $allowedMethods);
            $paymentOptionsBuilder->setAreAPMsEnabled($this->config->isApmEnabled($storeId));
            $paymentOptionsBuilder->setSelectedAPMs($selectedMethods);
            $paymentOptionsBuilder->setAreButtonsEnabled(false);
            $paymentOptionsBuilder->setIsPayPalCreditEnabled(false);
            $paymentOptionsBuilder->setIsVenmoEnabled(false);
            $paymentOptionsBuilder->setIsApplePayEnabled(false);
            $paymentOptionsBuilder->setIsGooglePayEnabled(false);
            $paymentOptionsBuilder->setIsCreditCardEnabled(false);
            $paymentOptionsBuilder->setIsPaylaterMessageEnabled(false);
            $paymentOptionsBuilder->setIsFastlaneEnabled(false);
        }

        return $paymentOptionsBuilder;
    }

    /**
     * Return an associated array [apm_code => apm_title, ...]
     *
     * @return array
     */
    private function getApmTitles(): array
    {
        $apmMethods = $this->apmMethods->toOptionArray();
        $result = [];
        foreach ($apmMethods as $apm) {
            $result[$apm['value']] = $apm['label'];
        }
        return $result;
    }

    /**
     * Return an associated array [apm_code => [min => 0, max => 100], ...]
     *
     * @return array
     */
    private function getApmThresholds(): array
    {
        $apmMethods = $this->apmMethods->toOptionArray();
        $result = [];
        foreach ($apmMethods as $apm) {
            $result[$apm['value']] = constant('self::THRESHOLD_' . strtoupper($apm['value']));
        }
        return $result;
    }
}
