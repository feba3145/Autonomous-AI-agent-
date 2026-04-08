<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Adminhtml\Virtual;

use Magento\Backend\Block\Widget\Form\Container;

/**
 * @api
 * @since 100.0.2
 */
class Form extends Container
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_blockGroup = 'PayPal_Braintree';
        $this->_controller = 'adminhtml_virtual';
        parent::_construct();

        $this->removeButton('back');
        $this->removeButton('reset');
        $this->removeButton('save');
        $this->addButton(
            'save',
            [
                'label' => __('Take Payment'),
                'class' => 'save primary',
                'data_attribute' => [
                    'mage-init' => ['button' => ['event' => 'takePayment', 'target' => '#payment_form_braintree']],
                ]
            ],
            1
        );
    }
}
