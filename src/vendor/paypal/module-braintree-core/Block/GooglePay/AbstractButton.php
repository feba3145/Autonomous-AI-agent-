<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\GooglePay;

use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PayPal\Braintree\Model\GooglePay\Auth;
use PayPal\Braintree\Gateway\Config\GooglePay\Config as GooglePayConfig;

/***/
abstract class AbstractButton extends Template
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var DefaultConfigProvider
     */
    private DefaultConfigProvider $defaultConfigProvider;

    /**
     * @var MethodInterface
     */
    private MethodInterface $payment;

    /**
     * @var Auth
     */
    private Auth $auth;

    /**
     * @var GooglePayConfig
     */
    private GooglePayConfig $googlePayConfig;

    /**
     * @var TaxHelper
     */
    private TaxHelper $taxHelper;

    /**
     * @var FormatInterface
     */
    private FormatInterface $localeFormat;

    /**
     * Button constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param MethodInterface $payment
     * @param Auth $auth
     * @param GooglePayConfig $googlePayConfig
     * @param TaxHelper $taxHelper
     * @param FormatInterface $localeFormat
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        DefaultConfigProvider $defaultConfigProvider,
        MethodInterface $payment,
        Auth $auth,
        GooglePayConfig $googlePayConfig,
        TaxHelper $taxHelper,
        FormatInterface $localeFormat,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->payment = $payment;
        $this->auth = $auth;
        $this->googlePayConfig = $googlePayConfig;
        $this->taxHelper = $taxHelper;
        $this->localeFormat = $localeFormat;
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
     * Is method active
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
    public function getMerchantId(): string
    {
        return $this->auth->getMerchantId();
    }

    /**
     * Get environment code
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        return $this->auth->getEnvironment();
    }

    /**
     * Braintree API token
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): string
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
        return $this->skipOrderReviewStep()
            ? $this->getUrl('checkout/onepage/success', ['_secure' => true])
            : $this->getUrl('braintree/googlepay/review', ['_secure' => true]);
    }

    /**
     * Currency code
     *
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): ?string
    {
        if ($this->checkoutSession->getQuote()->getCurrency()) {
            return $this->checkoutSession->getQuote()->getCurrency()->getBaseCurrencyCode();
        }

        return null;
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
     * Available card types
     *
     * @return array
     */
    public function getAvailableCardTypes(): array
    {
        return $this->auth->getAvailableCardTypes();
    }

    /**
     * BTN Color
     *
     * @return int
     */
    public function getBtnColor(): int
    {
        return $this->auth->getBtnColor();
    }

    /**
     * Get an array of the 3DSecure specific data
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get3DSecureConfigData(): array
    {
        if (!$this->auth->is3DSecureEnabled()) {
            return [
                'enabled' => false,
                'challengeRequested' => false,
                'thresholdAmount' => 0.0,
                'specificCountries' => [],
                'ipAddress' => ''
            ];
        }

        return [
            'enabled' => true,
            'challengeRequested' => $this->auth->is3DSecureAlwaysRequested(),
            'thresholdAmount' => $this->auth->get3DSecureThresholdAmount(),
            'specificCountries' => $this->auth->get3DSecureSpecificCountries(),
            'ipAddress' => $this->auth->getIpAddress()
        ];
    }

    /**
     * Get Store Code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode(): string
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }

        return '';
    }

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->googlePayConfig->skipOrderReviewStep();
    }

    /**
     * Get price format
     *
     * @return array
     */
    public function getPriceFormat(): array
    {
        return $this->localeFormat->getPriceFormat();
    }

    /**
     * Check if product prices includes tax.
     *
     * @return bool
     */
    public function priceIncludesTax(): bool
    {
        return $this->taxHelper->priceIncludesTax();
    }
}
