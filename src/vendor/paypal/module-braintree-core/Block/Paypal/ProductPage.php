<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Paypal;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Ui\ConfigProvider;

/**
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class ProductPage extends Button
{
    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return $this->config->isProductPageButtonEnabled();
    }

    /**
     * Get Currency
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrency(): string
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get currency symbol
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrencySymbol(): string
    {
        return $this->currency->load($this->getCurrency())->getCurrencySymbol();
    }

    /**
     * Get final amount of product
     *
     * @return float
     */
    public function getAmount(): float
    {
        /** @var Product $product */
        $product = $this->registry->registry('product');
        if ($product) {
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                return (float) $product->getFinalPrice();
            }
            if ($product->getTypeId() === Grouped::TYPE_CODE) {
                $groupedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
                return (float) $groupedProducts[0]->getPrice();
            }

            return (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        }

        return 100.00; // TODO There must be a better return value than this?
    }

    /**
     * Get container Id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return 'oneclick';
    }

    /**
     * Get location
     *
     * @return string
     */
    public function getLocation(): string
    {
        return 'productpage';
    }

    /**
     * Get action success url
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        //return $this->getUrl('braintree/paypal/oneclick', ['_secure' => true]);
        return $this->skipOrderReviewStep()
            ? $this->getUrl('checkout/onepage/success', ['_secure' => true])
            : $this->getUrl('braintree/paypal/review', ['_secure' => true]);
    }

    /**
     * Get button shape
     *
     * @param string $type
     * @return string
     */
    public function getButtonShape(string $type): string
    {
        return $this->config->getButtonShape(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * Get button color
     *
     * @param string $type
     * @return string
     */
    public function getButtonColor(string $type): string
    {
        if ($type === 'credit') {
            return $this->config->getCreditButtonColor(Config::BUTTON_AREA_PDP);
        }
        return $this->config->getButtonColor(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * Get button size
     *
     * @param string $type
     * @return string
     * @deprecated as Size field is redundant
     * @see no alternatives
     */
    public function getButtonSize(string $type): string
    {
        return $this->config->getButtonSize(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * Get button label
     *
     * @param string $type
     * @return string
     */
    public function getButtonLabel(string $type): string
    {
        return $this->config->getButtonLabel(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * @inheritDoc
     */
    public function getDisabledFunding(): array
    {
        return [
            'card' => $this->config->isFundingOptionCardDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_PDP),
            'elv' => $this->config->isFundingOptionElvDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_PDP)
        ];
    }

    /**
     * Get button config
     *
     * @return array
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getButtonConfig(): array
    {
        return [
            'clientToken' => $this->getClientToken(),
            'currency' => $this->getCurrency(),
            'environment' => $this->getEnvironment(),
            'merchantCountry' => $this->getMerchantCountry(),
            'isCreditActive' => $this->isCreditActive(),
            'skipOrderReviewStep' => $this->skipOrderReviewStep(),
            'pageType' => 'product-details',
        ];
    }

    /**
     * Get button styling
     *
     * @return array
     */
    public function getMessageStyles(): array
    {
        return $this->config->getMessageStyles(Config::BUTTON_AREA_PDP);
    }
}
