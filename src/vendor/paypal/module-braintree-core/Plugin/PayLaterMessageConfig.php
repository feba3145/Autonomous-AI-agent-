<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalPayLater\Config as PayLaterConfig;
use PayPal\Braintree\Model\Ui\ConfigProvider;

class PayLaterMessageConfig
{
    /**
     * @param Config $config
     * @param ConfigProvider $configProvider
     * @param PayLaterConfig $payLaterConfig
     * @param StoreManagerInterface $storageManager
     */
    public function __construct(
        protected readonly Config $config,
        protected readonly ConfigProvider $configProvider,
        protected readonly PayLaterConfig $payLaterConfig,
        protected readonly StoreManagerInterface $storageManager
    ) {
    }

    /**
     * Add paylater message configuration.
     *
     * @param Sidebar $subject
     * @param array $result
     * @return array
     */
    public function afterGetConfig(Sidebar $subject, array $result): array
    {
        $result['payPalBraintreeClientToken'] = $this->configProvider->getClientToken();

        if ($this->payLaterConfig->isMessageActive('cart')) {
            $result['payPalBraintreePaylaterMessageConfig'] = $this->config->getMessageStyles('cart');
            $result['paypalBraintreeCurrencyCode'] = $this->storageManager->getStore()->getCurrentCurrencyCode();
        }

        return $result;
    }
}
