<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Controller\Adminhtml\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;

class GetNonce extends \PayPal\Braintree\Controller\Payment\GetNonce implements HttpGetActionInterface
{
}
