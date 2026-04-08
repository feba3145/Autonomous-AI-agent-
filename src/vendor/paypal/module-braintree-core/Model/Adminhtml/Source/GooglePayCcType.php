<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Adminhtml\Source;

/** @codeCoverageIgnore
 */
class GooglePayCcType extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'VISA', 'label' => 'Visa'],
            ['value' => 'MASTERCARD', 'label' => 'MasterCard'],
            ['value' => 'AMEX', 'label' => 'AMEX'],
            ['value' => 'DISCOVER', 'label' => 'Discover'],
            ['value' => 'JCB', 'label' => 'JCB']
        ];
    }
}
