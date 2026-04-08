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

class ApplePayConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'apple_pay';
    private const LOCATION = 'checkout_applepay';
    public const PAYMENT_SOURCE = 'applepay';

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
        if (!$this->baseConfig->isConfigured() || !$this->config->isApplePayLocationEnabled('checkout')) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['location'] = Config::CHECKOUT_CHECKOUT_LOCATION;
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['sdkParams'] = $this->configProvider->getScriptParams(
            self::CODE,
            self::LOCATION,
            $this->getPaymentOptions()
        );
        $config['payment'][self::CODE]['buttonStyles'] = $this->config->getButtonConfiguration();
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/applepay.png');

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
        if (!$this->config->isApplePayLocationEnabled(strtolower(Config::START_OF_CHECKOUT_LOCATION))) {
            return [
                'isVisible' => false
            ];
        }

        return [
            'isVisible' => true,
            'location' => Config::START_OF_CHECKOUT_LOCATION,
            'createOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/createpaypalorder'),
            'authorizeOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/updatequote'),
            'orderReviewUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/review'),
            'cancelUrl' => $this->url->getUrl('checkout/cart'),
            'estimateShippingMethodsWhenLoggedInUrl' =>
                $this->url->getUrl('rest/V1/carts/mine/estimate-shipping-methods'),
            'estimateShippingMethodsWhenGuestUrl' => $this->url->getUrl(
                'rest/V1/guest-carts/:cartId/estimate-shipping-methods'
            ),
            'shippingInformationWhenLoggedInUrl' => $this->url->getUrl('rest/V1/carts/mine/shipping-information'),
            'shippingInformationWhenGuestUrl' =>
                $this->url->getUrl('rest/V1/guest-carts/:quoteId/shipping-information'),
            'updatePayPalOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/updatepaypalorder/'),
            'countriesUrl' => $this->url->getUrl('rest/V1/directory/countries/:countryCode'),
            'setQuoteAsInactiveUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/setquoteasinactive'),
            'placeOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/placeorder/'),
            'getOrderDetailsUrl' => $this->url->getUrl('paymentservicespaypal/order/getcurrentorder'),
            'sort' => $this->config->getSortOrder(self::CODE)
        ];
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
        $paymentOptionsBuilder->setAreButtonsEnabled($this->config->isApplePayLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsPayPalCreditEnabled(false);
        $paymentOptionsBuilder->setIsVenmoEnabled(false);
        $paymentOptionsBuilder->setIsApplePayEnabled($this->config->isApplePayLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsCreditCardEnabled(false);
        $paymentOptionsBuilder->setIsPaylaterMessageEnabled(false);
        return $paymentOptionsBuilder;
    }
}
