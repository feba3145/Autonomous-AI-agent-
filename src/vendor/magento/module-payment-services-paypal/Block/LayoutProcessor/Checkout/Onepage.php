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

namespace Magento\PaymentServicesPaypal\Block\LayoutProcessor\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Removes the express payment methods if none are enabled.
 */
class Onepage implements LayoutProcessorInterface
{
    /**
     * @param Config $config
     * @param CompositeConfigProvider $configProvider
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly CompositeConfigProvider $configProvider,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $jsLayout
     * @return array
     * @throws NoSuchEntityException
     */
    public function process($jsLayout): array
    {
        // Enable/Disable Express Payment methods.
        return $this->toggleExpressPayments($jsLayout);
    }

    /**
     * Remove express payment methods if all methods are disabled in the config.
     *
     * @param array $jsLayout
     * @return array
     * @throws NoSuchEntityException
     */
    private function toggleExpressPayments(array $jsLayout): array
    {
        $isStartCheckoutEnabled = $this->isExpressCheckoutEnabled();

        if ($isStartCheckoutEnabled) {
            // If the quote is not virtual remove the express checkout methods from billing-step to prevent them from
            // being displayed twice. If the quote is virtual the shipping-step is never shown so no processing needed.
            if (!$this->isQuoteVirtual()) {
                $jsLayout = $this->unsetStartCheckoutExpress($jsLayout, 'billing-step');
            }

            return $jsLayout;
        }

        $jsLayout = $this->unsetStartCheckoutExpress($jsLayout, 'billing-step');
        $jsLayout = $this->unsetStartCheckoutExpress($jsLayout, 'shipping-step');

        return $jsLayout;
    }

    /**
     * Check if the current quote is virtual.
     *
     * @return bool
     */
    private function isQuoteVirtual(): bool
    {
        try {
            $checkoutConfig = $this->configProvider->getConfig();
            $quoteId = $checkoutConfig['quoteData']['entity_id'] ?? null;

            if ($quoteId) {
                $quote = $this->cartRepository->get((int)$quoteId);
                return $quote->isVirtual();
            }
        } catch (NoSuchEntityException $e) {
            return false;
        }

        return false;
    }

    /**
     * Returns true if at least one express checkout method is enabled in the config, false otherwise.
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isExpressCheckoutEnabled(): bool
    {
        $location = strtolower(Config::START_OF_CHECKOUT_LOCATION);
        return $this->config->isApplePayLocationEnabled($location)
            || $this->config->isGooglePayLocationEnabled($location)
            || $this->config->isLocationEnabled($location);
    }

    /**
     * Removes the express payment methods from the given location in the checkout.
     *
     * @param array $jsLayout
     * @param string $location
     * @return array
     */
    private function unsetStartCheckoutExpress(array $jsLayout, string $location): array
    {
        if (isset($jsLayout['components']['checkout']['children']['steps']['children'][$location]['children']
            ['shippingAddress']['children']['payment-services-express-payments'])
        ) {
            unset($jsLayout['components']['checkout']['children']['steps']['children'][$location]['children']
                ['shippingAddress']['children']['payment-services-express-payments']);
        }

        return $jsLayout;
    }
}
