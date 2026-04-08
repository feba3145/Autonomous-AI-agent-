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

namespace Magento\PaymentServicesPaypal\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApmMethods implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bancontact', 'label' => __('Bancontact')],
            ['value' => 'blik', 'label' => __('BLIK')],
            ['value' => 'eps', 'label' => __('eps')],
            ['value' => 'ideal', 'label' => __('iDEAL')],
            ['value' => 'mybank', 'label' => __('MyBank')],
            ['value' => 'p24', 'label' => __('Przelewy24')],
        ];
    }
}
