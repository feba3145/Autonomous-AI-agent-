<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Adminhtml\System\Config;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;

class PayLaterMessageCheckout extends PayLaterMessage
{
    /**
     * @var string
     */
    protected $_template = 'PayPal_Braintree::system/config/preview.phtml';

    /**
     * Get button config
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getButtonConfig(): array
    {
        $config = parent::getButtonConfig();

        $config['showPayLaterMessaging'] = true;

        return $config;
    }
}
