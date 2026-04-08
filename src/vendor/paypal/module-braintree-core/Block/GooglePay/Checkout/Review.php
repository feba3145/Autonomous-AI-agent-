<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Block\GooglePay\Checkout;

use Magento\Paypal\Block\Express;

/**
 * @api
 * @since 100.0.2
 */
class Review extends Express\Review
{
    /**
     * @var string
     */
    protected $_controllerPath = 'braintree/googlepay'; // @codingStandardsIgnoreLine
}
