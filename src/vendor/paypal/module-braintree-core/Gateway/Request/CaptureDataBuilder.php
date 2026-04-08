<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

class CaptureDataBuilder implements BuilderInterface
{
    use Formatter;

    public const TRANSACTION_ID = 'transaction_id';

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
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $payment = $paymentDO->getPayment();

        $transactionId = $payment->getCcTransId();
        if (!$transactionId) {
            throw new LocalizedException(__('No authorization transaction to proceed capture.'));
        }

        return [
            self::TRANSACTION_ID => $transactionId,
            PaymentDataBuilder::AMOUNT => $this->formatPrice($this->subjectReader->readAmount($buildSubject)),
            PaymentDataBuilder::ORDER_ID => $paymentDO->getOrder()->getOrderIncrementId(),
            PaymentDataBuilder::MERCHANT_ACCOUNT_ID => $this->config
                ->getMerchantAccountId((int) $paymentDO->getOrder()->getStoreId())
        ];
    }
}
