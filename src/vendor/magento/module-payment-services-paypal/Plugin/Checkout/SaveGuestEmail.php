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

namespace Magento\PaymentServicesPaypal\Plugin\Checkout;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\ApmConfigProvider;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteRetriever;

class SaveGuestEmail
{
    /**
     * @param QuoteRetriever $quoteRetriever
     * @param CartRepositoryInterface $cartRepository
     * @param array $allowedPaymentMethods
     */
    public function __construct(
        private readonly QuoteRetriever $quoteRetriever,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly array $allowedPaymentMethods = []
    ) {
    }

    /**
     * Save guest email address to quote for 'payment_services_paypal_apm' payment method only
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param bool $result
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @return bool
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        bool $result,
        string $cartId,
        string $email,
        PaymentInterface $paymentMethod
    ): bool {
        if ($this->shouldProcess($paymentMethod->getMethod())) {
            $quote = $this->quoteRetriever->getQuoteByMaskedId($cartId);
            $quote->setCustomerEmail($email);
            $this->cartRepository->save($quote);
        }

        return $result;
    }

    /**
     * Check if the payment method should be processed
     *
     * @param string $paymentMethodCode
     * @return bool
     */
    private function shouldProcess(string $paymentMethodCode): bool
    {
        return in_array($paymentMethodCode, $this->allowedPaymentMethods, true);
    }
}
