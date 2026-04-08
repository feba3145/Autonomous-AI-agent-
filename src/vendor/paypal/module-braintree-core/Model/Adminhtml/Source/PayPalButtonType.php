<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class PayPalButtonType implements ArrayInterface
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
                'value' => 'paypal',
                'label' => __('PayPal Button'),
            ],
            [
                'value' => 'paylater',
                'label' => __('PayPal Pay Later Button'),
            ],
            [
                'value' => 'credit',
                'label' => __('PayPal Credit Button')
            ]
        ];
    }
}
