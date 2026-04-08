<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Request;

use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Observer\DataAssignObserver;
use Magento\Payment\Gateway\Request\BuilderInterface;

class DeviceDataBuilder implements BuilderInterface
{
    public const DEVICE_DATA = 'deviceData';

    /**
     * @var string $deviceDataKey
     */
    private static string $deviceDataKey = 'deviceData';

    /**
     * @var SubjectReader $subjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * DeviceDataBuilder constructor
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $result = [];
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $data = $payment->getAdditionalInformation();

        if (!empty($data[DataAssignObserver::DEVICE_DATA])) {
            $result[self::$deviceDataKey] = $data[DataAssignObserver::DEVICE_DATA];
        }

        return $result;
    }
}
