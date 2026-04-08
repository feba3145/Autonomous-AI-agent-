<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;

class GooglePayConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'google_pay';
    private const LOCATION = 'checkout_googlepay';
    public const PAYMENT_SOURCE = 'googlepay';

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
     * @inheritdoc
     */
    public function getConfig()
    {
        $config = $this->configProvider->getConfig();
        if (!$this->baseConfig->isConfigured() || !$this->config->isGooglePayLocationEnabled('checkout')) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['location'] = Config::CHECKOUT_CHECKOUT_LOCATION;

        $config['payment'][self::CODE]['mode'] = $this->config->getGooglePayMode();
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['getOrderDetailsUrl'] =
            $this->url->getUrl('paymentservicespaypal/order/getcurrentorder');
        $config['payment'][self::CODE]['threeDSMode'] = $this->config->getGooglePayThreeDS() !== "0"
            ? $this->config->getGooglePayThreeDS()
            : false;
        $config['payment'][self::CODE]['sdkParams'] = $this->configProvider->getScriptParams(
            self::CODE,
            self::LOCATION,
            $this->getPaymentOptions()
        );
        $config['payment'][self::CODE]['styles'] =
            array_merge($this->config->getButtonConfiguration(), $this->getGooglePayStyles());
        $config['payment'][self::CODE]['paymentSource'] = self::PAYMENT_SOURCE;
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/googlepay.png');

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
        if (!$this->config->isGooglePayLocationEnabled(strtolower(Config::START_OF_CHECKOUT_LOCATION))) {
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
            'sort' => $this->config->getSortOrder(self::CODE)
        ];
    }

    /**
     * Get Google Pay styles
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getGooglePayStyles() : array
    {
        return $this->config->getGooglePayStyles();
    }

    /**
     * Get payment options
     *
     * @return PaymentOptionsBuilder
     * @throws NoSuchEntityException
     */
    private function getPaymentOptions(): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder = $this->configProvider->getPaymentOptions();
        $paymentOptionsBuilder->setAreButtonsEnabled(false);
        $paymentOptionsBuilder->setIsPayPalCreditEnabled(false);
        $paymentOptionsBuilder->setIsVenmoEnabled(false);
        $paymentOptionsBuilder->setIsGooglePayEnabled($this->config->isGooglePayLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsCreditCardEnabled(false);
        $paymentOptionsBuilder->setIsPaylaterMessageEnabled(false);
        return $paymentOptionsBuilder;
    }
}
