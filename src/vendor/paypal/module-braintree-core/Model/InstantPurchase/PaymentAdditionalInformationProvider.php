<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\InstantPurchase;

use PayPal\Braintree\Gateway\Command\GetPaymentNonceCommand;
use Magento\InstantPurchase\PaymentMethodIntegration\PaymentAdditionalInformationProviderInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Provides additional information of Braintree specific payment method for Instant Purchase.
 *
 * Class PaymentAdditionalInformationProvider
 */
class PaymentAdditionalInformationProvider implements PaymentAdditionalInformationProviderInterface
{
    /**
     * @var GetPaymentNonceCommand
     */
    private GetPaymentNonceCommand $getPaymentNonceCommand;

    /**
     * PaymentAdditionalInformationProvider constructor.
     * @param GetPaymentNonceCommand $getPaymentNonceCommand
     */
    public function __construct(GetPaymentNonceCommand $getPaymentNonceCommand)
    {
        $this->getPaymentNonceCommand = $getPaymentNonceCommand;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalInformation(PaymentTokenInterface $paymentToken): array
    {
        $paymentMethodNonce = $this->getPaymentNonceCommand->execute([
            PaymentTokenInterface::CUSTOMER_ID => $paymentToken->getCustomerId(),
            PaymentTokenInterface::PUBLIC_HASH => $paymentToken->getPublicHash(),
        ])->get()['paymentMethodNonce'];

        return [
            'payment_method_nonce' => $paymentMethodNonce,
        ];
    }
}
