<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Paypal\Helper;

use Magento\Quote\Model\Quote;

/**
 * Abstract class AbstractHelper
 */
abstract class AbstractHelper
{
    /**
     * Make sure addresses will be saved without validation errors
     *
     * @param Quote $quote
     * @return void
     */
    protected function disabledQuoteAddressValidation(Quote $quote)
    {
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setShouldIgnoreValidation(true);

        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShouldIgnoreValidation(true);
            if (!$billingAddress->getEmail()) {
                $billingAddress->setSameAsBilling(1);
            }
        }
    }
}
