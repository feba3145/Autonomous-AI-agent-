<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Adminhtml\System\Config;

class PayLaterMessage extends Preview
{
    /**
     * @var string
     */
    protected $_template = 'PayPal_Braintree::system/config/paylater-message.phtml';
}
