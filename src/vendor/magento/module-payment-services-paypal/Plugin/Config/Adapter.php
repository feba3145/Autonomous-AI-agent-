<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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

namespace Magento\PaymentServicesPaypal\Plugin\Config;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Model\ApmConfigProvider;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\FastlaneConfigProvider;
use Magento\PaymentServicesPaypal\Model\GooglePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\PaymentServicesPaypal\Model\SmartButtonsConfigProvider;
use Magento\Payment\Model\Method\Adapter as PaymentAdapter;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Sales\Model\Order\Payment;

class Adapter
{
    private const SUPPORTED_PAYMENT_METHODS = [
        HostedFieldsConfigProvider::CODE,
        SmartButtonsConfigProvider::CODE,
        ApplePayConfigProvider::CODE,
        GooglePayConfigProvider::CODE,
        FastlaneConfigProvider::CODE,
        ApmConfigProvider::CODE
    ];

    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Return isActive for payment methods based on whether payments is enabled
     *
     * @param PaymentAdapter $subject
     * @param bool $result
     * @param string|null $storeId
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     */
    public function afterIsActive(PaymentAdapter $subject, bool $result, $storeId = null): bool
    {
        if ($this->isSupportedPaymentMethod($subject)) {
            return $this->config->isEnabled($storeId);
        } elseif ($this->isVaultedCardMethod($subject)) {
            return $this->config->isEnabled($storeId) && $this->config->isVaultEnabled($storeId);
        } else {
            return $result;
        }
    }

    /**
     * Return canCapture for payment methods based on whether order is in payment review state
     *
     * @param PaymentAdapter $subject
     * @param bool $result
     * @return bool
     * @throws LocalizedException
     */
    public function afterCanCapture(PaymentAdapter $subject, bool $result): bool
    {
        $payment = $subject->getInfoInstance();
        if ($payment instanceof Payment) {
            if ($this->isInPaymentReviewState($payment)
                && ($this->isSupportedPaymentMethod($subject) || $this->isVaultedCardMethod($subject))
            ) {
                return false;
            }
        }

        return $result;
    }

    /**
     * Check if can review payment for an order
     *
     * Make canReviewPayment return true for Payment Services payment methods
     * when async payment status updates are enabled
     *
     * @param PaymentAdapter $subject
     * @param bool $result
     * @return bool
     * @throws LocalizedException
     */
    public function afterCanReviewPayment(PaymentAdapter $subject, bool $result): bool
    {
        $payment = $subject->getInfoInstance();
        if ($payment instanceof Payment) {
            if ($this->isSupportedPaymentMethod($subject)) {
                return $this->config->isAsyncPaymentStatusUpdatesEnabled();
            }
        }

        return $result;
    }

    /**
     * Check if order is in payment review state
     *
     * @param Payment $payment
     * @return bool
     */
    private function isInPaymentReviewState(Payment $payment): bool
    {
        return $payment->getOrder()->getState() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW;
    }

    /**
     * Check if payment method belongs to Payment Services
     *
     * @param PaymentAdapter $subject
     * @return bool
     */
    private function isSupportedPaymentMethod(PaymentAdapter $subject): bool
    {
        return in_array($subject->getCode(), self::SUPPORTED_PAYMENT_METHODS);
    }

    /**
     * Check if payment method is vaulted card
     *
     * @param PaymentAdapter $subject
     * @return bool
     */
    private function isVaultedCardMethod(PaymentAdapter $subject): bool
    {
        return $subject->getCode() === HostedFieldsConfigProvider::CC_VAULT_CODE;
    }
}
