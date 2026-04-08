<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Config\Source\PayPalMessages;

use Magento\Framework\Data\OptionSourceInterface;

class Logo implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'primary', 'label' => __('primary')],
            ['value' => 'alternative', 'label' => __('alternative')],
            ['value' => 'inline', 'label' => __('inline')],
            ['value' => 'none', 'label' => __('none')]
        ];
    }
}
