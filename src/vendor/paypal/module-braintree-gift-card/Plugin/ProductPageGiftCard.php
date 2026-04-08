<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\BraintreeGiftCard\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use PayPal\Braintree\Block\Paypal\ProductPage;

class ProductPageGiftCard
{
    private const TYPE_GIFTCARD = 'giftcard';

    /**
     * @param Registry $registry
     */
    public function __construct(
        protected readonly Registry $registry,
    ) {
    }

    /**
     * Get amount for Gift card product
     *
     * @param ProductPage $subject
     * @param float $result
     * @return float
     */
    public function afterGetAmount(
        ProductPage $subject,
        float $result
    ): float {
        /** @var Product $product */
        $product = $this->registry->registry('product');

        if ($product && $product->getTypeId() === self::TYPE_GIFTCARD) {
            return (float) $product->getOpenAmountMin();
        }

        return $result;
    }
}
