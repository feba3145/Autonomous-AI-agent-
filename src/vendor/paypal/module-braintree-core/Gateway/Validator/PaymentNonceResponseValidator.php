<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Validator;

class PaymentNonceResponseValidator extends GeneralResponseValidator
{
    /**
     * Get response validators for payment nonce
     *
     * @return array
     */
    protected function getResponseValidators(): array
    {
        return array_merge(
            parent::getResponseValidators(),
            [
                static function ($response) {
                    return [
                        !empty($response->paymentMethodNonce) && !empty($response->paymentMethodNonce->nonce),
                        [__('Payment method nonce can\'t be retrieved.')]
                    ];
                }
            ]
        );
    }
}
