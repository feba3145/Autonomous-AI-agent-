<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

class GooglePayBtnColor implements ArrayInterface
{
    public const OPTION_WHITE = 0;
    public const OPTION_BLACK = 1;

    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::OPTION_WHITE,
                'label' => 'White'
            ],
            [
                'value' => self::OPTION_BLACK,
                'label' => 'Black',
            ]
        ];
    }
}
