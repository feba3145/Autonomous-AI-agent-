<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

class DisabledFundingOptions implements ArrayInterface
{
    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'card',
                'label' => __('PayPal Guest Checkout Credit Card Icons'),
            ],
            [
                'value' => 'elv',
                'label' => __('Elektronisches Lastschriftverfahren – German ELV')
            ]
        ];
    }
}
