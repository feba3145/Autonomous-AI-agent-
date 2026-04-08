<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

class VaultCaptureDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * @var SubjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param Config $config
     */
    public function __construct(
        SubjectReader $subjectReader,
        Config $config
    ) {
        $this->subjectReader = $subjectReader;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $extensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        if ($paymentToken === null) {
            $paymentGatewayToken = false;
        } else {
            $paymentGatewayToken = $paymentToken->getGatewayToken();
        }

        return [
            PaymentDataBuilder::AMOUNT => $this->formatPrice($this->subjectReader->readAmount($buildSubject)),
            'paymentMethodToken' => $paymentGatewayToken,
            PaymentDataBuilder::MERCHANT_ACCOUNT_ID => $this->config
                ->getMerchantAccountId((int) $paymentDO->getOrder()->getStoreId())
        ];
    }
}
