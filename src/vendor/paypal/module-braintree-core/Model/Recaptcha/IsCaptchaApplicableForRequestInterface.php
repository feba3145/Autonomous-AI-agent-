<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Recaptcha;

use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;

interface IsCaptchaApplicableForRequestInterface
{
    /**
     * Determine whether Captcha should be used for request.
     *
     * @param EndpointInterface $endpoint
     * @return bool
     */
    public function execute(EndpointInterface $endpoint): bool;
}
