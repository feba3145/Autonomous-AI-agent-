<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\ApplePay;

use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Api\Data\AuthDataInterface;
use PayPal\Braintree\Model\ApplePay\Auth;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;

abstract class AbstractButton extends Template
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var MethodInterface
     */
    private MethodInterface $payment;

    /**
     * @var AuthDataInterface|Auth
     */
    private AuthDataInterface|Auth $auth;

    /**
     * Button constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param Auth $auth
     * @param array $data
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        Auth $auth,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->payment = $payment;
        $this->auth = $auth->get();
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string // @codingStandardsIgnoreLine
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Check if payment method is available
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isActive(): bool
    {
        return $this->payment->isAvailable($this->checkoutSession->getQuote());
    }

    /**
     * Merchant name to display in popup
     *
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->auth->getDisplayName();
    }

    /**
     * Braintree API token
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): ?string
    {
        return $this->auth->getClientToken();
    }

    /**
     * URL To success page
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->auth->getActionSuccess();
    }

    /**
     * Is customer logged in flag
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->auth->isLoggedIn();
    }

    /**
     * Cart grand total
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAmount(): float
    {
        return (float) $this->checkoutSession->getQuote()->getBaseGrandTotal();
    }

    /**
     * Get store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode(): string
    {
        return $this->auth->getStoreCode();
    }
}
