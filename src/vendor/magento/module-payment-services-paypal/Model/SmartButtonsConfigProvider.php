<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;

class SmartButtonsConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'smart_buttons';
    private const LOCATION = 'checkout_smart_buttons';

    /**
     * @param Config $config
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly BaseConfig $baseConfig,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Get checkout configuration
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        $config = $this->configProvider->getConfig();
        if (!$this->baseConfig->isConfigured() || !$this->config->isLocationEnabled('checkout')) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['location'] = Config::CHECKOUT_CHECKOUT_LOCATION;
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['sdkParams'] = $this->configProvider->getScriptParams(
            self::CODE,
            self::LOCATION,
            $this->getPaymentOptions(self::LOCATION)
        );
        $config['payment'][self::CODE]['messageStyles'] = $this->config->getPayLaterStyling();
        $config['payment'][self::CODE]['canDisplayMessage'] = (bool) $this->config->canDisplayPayLaterMessage();
        $config['payment'][self::CODE]['buttonStyles'] = $this->config->getButtonConfiguration();
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/paypal_vertical.png');
        $config['payment'][self::CODE]['appSwitchWhenAvailable'] = $this->config->getAppSwitch();

        $config['payment'][self::CODE]['express'] = $this->getExpressPaymentConfig();

        return $config;
    }

    /**
     * Gets the configuration required for making an express payment.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getExpressPaymentConfig(): array
    {
        if (!$this->config->isLocationEnabled(strtolower(Config::START_OF_CHECKOUT_LOCATION))) {
            return [
                'isVisible' => false
            ];
        }

        return [
            'isVisible' => true,
            'location' => Config::START_OF_CHECKOUT_LOCATION,
            'createOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/createpaypalorder'),
            'authorizeOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/updatequote'),
            'getOrderDetailsUrl' => $this->url->getUrl('paymentservicespaypal/order/getcurrentorder'),
            'completeOrderUrl' => $this->url->getUrl('', ['_direct' => 'rest/V1/payment-order/completeOrder']),
            'sort' => $this->config->getSortOrder(self::CODE),
            'sdkParams' => $this->configProvider->getScriptParams(
                self::CODE,
                Config::START_OF_CHECKOUT_LOCATION,
                $this->getPaymentOptions(Config::START_OF_CHECKOUT_LOCATION)
            )
        ];
    }

    /**
     * Get payment options
     *
     * @param string $location
     * @return PaymentOptionsBuilder
     * @throws NoSuchEntityException
     */
    private function getPaymentOptions(string $location): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder = $this->configProvider->getPaymentOptions();
        $paymentOptionsBuilder->setAreButtonsEnabled($this->config->isLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsPayPalCreditEnabled($this->config->isFundingSourceEnabledByName('paypal_credit'));
        $paymentOptionsBuilder->setIsVenmoEnabled($this->config->isFundingSourceEnabledByName('venmo'));
        $paymentOptionsBuilder->setIsApplePayEnabled(false);
        if ($location === self::LOCATION) {
            $paymentOptionsBuilder->setIsPayPalCardEnabled($this->config->isFundingSourceEnabledByName('card'));
        }
        $paymentOptionsBuilder->setIsPaylaterMessageEnabled($this->config->canDisplayPayLaterMessage());

        return $paymentOptionsBuilder;
    }
}
