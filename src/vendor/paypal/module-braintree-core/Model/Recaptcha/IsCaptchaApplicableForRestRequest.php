<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Recaptcha;

use Magento\Framework\Webapi\Rest\Request;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use PayPal\Braintree\Model\ApplePay\Ui\ConfigProvider;
use PayPal\Braintree\Model\Ui\PayPal\ConfigProvider as PayPalConfigProvider;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider as GooglePayConfigProvider;

class IsCaptchaApplicableForRestRequest implements IsCaptchaApplicableForRequestInterface
{
    public const DISABLE_RECAPTCHA_FOR = [
        ConfigProvider::METHOD_CODE,
        PayPalConfigProvider::PAYPAL_CODE,
        GooglePayConfigProvider::METHOD_CODE
    ];

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Determine whether Captcha should be used for request.
     *
     * Currently, it is only used for the REST Place order request and disables Captcha for Apple Pay as not required.
     *
     * @param EndpointInterface $endpoint
     * @return bool
     */
    public function execute(EndpointInterface $endpoint): bool
    {
        // Should check for REST API checkout place order endpoint.
        if ($endpoint->getServiceMethod() !== 'savePaymentInformationAndPlaceOrder') {
            return true;
        }

        $requestData = $this->request->getRequestData();

        // Should check for captcha only & only if payment method is not Apple Pay, Google Pay and PayPal.
        return isset($requestData['paymentMethod']['method'])
            && !in_array($requestData['paymentMethod']['method'], self::DISABLE_RECAPTCHA_FOR);
    }
}
