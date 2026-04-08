<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class QuoteVaultPaymentImportDataBefore extends AbstractDataAssignObserver
{
    private const INPUT_ARGUMENT_KEY = 'input';
    private const PAYMENT_ARGUMENT_KEY = 'payment';
    private const VAULT_PAYMENT_METHOD_CODE = 'payment_services_paypal_vault';

    /**
     * Sensitive fields that should be preserved from the quote
     * and not accepted from storefront
     *
     * @var string[]
     */
    private $sensitiveFieldsList = [
        'paypal_order_amount'
    ];

    /**
     * Copy sensitive data from quote payment to input data to preserve it during quote data import process
     *
     * This is required because Payment Services stores extra data in quote payment additional information
     * after creating PayPal order and we need this information during checkout process.
     *
     * The issue is that everything we store there is lost during the call to set-payment-information endpoint
     * when using a vaulted card as it only stores additional data provided in the request
     * and does not merge it with existing additional data in quote payment.
     *
     * \Magento\Vault\Observer\PaymentTokenAssigner::execute overrides additional data
     * without merging it with existing data
     *
     * @param Observer $observer
     * @return void
     * @see SaveAdditionalData
     */
    public function execute(Observer $observer): void
    {
        $paymentInfo = $this->readPaymentArgument($observer);
        if (!$this->shouldRun($paymentInfo)) {
            return;
        }

        $data = $this->readInputArgument($observer);
        $sensitiveData = [];
        $originalData = $paymentInfo->getAdditionalInformation() ?? [];

        foreach ($this->sensitiveFieldsList as $field) {
            if (isset($originalData[$field])) {
                $sensitiveData[$field] = $originalData[$field];
            }
        }

        if (!empty($sensitiveData)) {
            $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA) ?? [];
            $additionalData = array_merge($additionalData, $sensitiveData);

            $data->setData(PaymentInterface::KEY_ADDITIONAL_DATA, $additionalData);
        }
    }

    /**
     * Reads input argument
     *
     * @param Observer $observer
     * @return DataObject
     */
    private function readInputArgument(Observer $observer): DataObject
    {
        return $this->readArgument($observer, self::INPUT_ARGUMENT_KEY, DataObject::class);
    }

    /**
     * Reads payment argument
     *
     * @param Observer $observer
     * @return InfoInterface
     */
    private function readPaymentArgument(Observer $observer): InfoInterface
    {
        return $this->readArgument($observer, self::PAYMENT_ARGUMENT_KEY, InfoInterface::class);
    }

    /**
     * Check if the observer should run for the given payment method
     *
     * @param InfoInterface $info
     * @return bool
     */
    private function shouldRun(InfoInterface $info): bool
    {
        return $info->getMethod() === self::VAULT_PAYMENT_METHOD_CODE;
    }
}
