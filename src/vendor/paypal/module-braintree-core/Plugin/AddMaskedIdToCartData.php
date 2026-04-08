<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\CustomerData\Cart as Subject;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class AddMaskedIdToCartData
{
    public const GUEST_MASKED_ID_KEY = 'braintree_masked_id';

    /**
     * Cart constructor
     *
     * @param Session $checkoutSession
     * @param QuoteIdToMaskedQuoteIdInterface $maskedQuote
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly QuoteIdToMaskedQuoteIdInterface $maskedQuote,
    ) {
    }

    /**
     * Intercept getSectionData and add masked ID if available.
     *
     * @param Subject $subject
     * @param array $result
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterGetSectionData(
        Subject $subject,
        array $result
    ): array {
        $quote = $this->checkoutSession->getQuote();
        $quoteId = $this->checkoutSession->getQuoteId();

        if ($quote &&
            $quoteId != null) {
            $maskedId = $this->maskedQuote->execute((int)$quoteId);
            $result[self::GUEST_MASKED_ID_KEY] = $maskedId;
        }

        return $result;
    }
}
