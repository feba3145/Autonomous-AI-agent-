<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Label implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'paypal', 'label' => __('PayPal')],
            ['value' => 'checkout', 'label' => __('Checkout')],
            ['value' => 'buynow', 'label' => __('Buy Now')],
            ['value' => 'pay', 'label' => __('Pay')]
        ];
    }
}
