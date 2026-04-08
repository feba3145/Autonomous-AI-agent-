<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class Location implements ArrayInterface
{
    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'cart',
                'label' => __('Mini-Cart and Cart Page'),
            ],
            [
                'value' => 'checkout',
                'label' => __('Checkout Page'),
            ],
            [
                'value' => 'productpage',
                'label' => __('Product Page')
            ]
        ];
    }
}
