<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Paypal;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalCredit\Config as PayPalCreditConfig;
use PayPal\Braintree\Gateway\Config\PayPalPayLater\Config as PayPalPayLaterConfig;
use PayPal\Braintree\Model\Ui\ConfigProvider;

/**
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Button extends Template implements ShortcutInterface
{
    public const ALIAS_ELEMENT_INDEX = 'alias';
    public const BUTTON_ELEMENT_INDEX = 'button_id';

    /**
     * Button constructor
     *
     * @param Context $context
     * @param ResolverInterface $localeResolver
     * @param Session $checkoutSession
     * @param Config $config
     * @param PayPalCreditConfig $payPalCreditConfig
     * @param PayPalPayLaterConfig $payPalPayLaterConfig
     * @param BraintreeConfig $braintreeConfig
     * @param ConfigProvider $configProvider
     * @param MethodInterface $payment
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param CustomerSession $customerSession
     * @param Registry $registry
     * @param Currency $currency
     * @param TaxHelper $taxHelper
     * @param RequestInterface $request
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        protected ResolverInterface $localeResolver,
        protected readonly Session $checkoutSession,
        protected readonly Config $config,
        protected readonly PayPalCreditConfig $payPalCreditConfig,
        protected readonly PayPalPayLaterConfig $payPalPayLaterConfig,
        protected readonly BraintreeConfig $braintreeConfig,
        protected readonly ConfigProvider $configProvider,
        protected readonly MethodInterface $payment,
        protected readonly DefaultConfigProvider $defaultConfigProvider,
        protected readonly CustomerSession $customerSession,
        protected readonly Registry $registry,
        protected readonly Currency $currency,
        protected readonly TaxHelper $taxHelper,
        protected readonly RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX);
    }

    /**
     * Get Container Id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX);
    }

    /**
     * Get Locale
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * Get currency
     *
     * @return string|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrency(): ?string
    {
        return $this->checkoutSession->getQuote()->getCurrency()->getBaseCurrencyCode();
    }

    /**
     * Get amount
     *
     * @return float
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getAmount(): float
    {
        return (float) $this->checkoutSession->getQuote()->getBaseGrandTotal();
    }

    /**
     * Is active
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isActive(): bool
    {
        return $this->payment->isAvailable($this->checkoutSession->getQuote()) &&
            $this->config->isDisplayShoppingCart();
    }

    /**
     * Is PayPal credit active
     *
     * @return bool
     */
    public function isCreditActive(): bool
    {
        return $this->payPalCreditConfig->isActive();
    }

    /**
     * Is PayPal pay later active
     *
     * @return bool
     */
    public function isPayLaterActive(): bool
    {
        return $this->payPalPayLaterConfig->isActive();
    }

    /**
     * Is Pay Later message active
     *
     * @param string $type
     * @return bool
     */
    public function isPayLaterMessageActive(string $type): bool
    {
        return $this->payPalPayLaterConfig->isMessageActive($type);
    }

    /**
     * Is show PayPal Button
     *
     * @param string $type
     * @param string $location
     * @return bool
     */
    public function showPayPalButton(string $type, string $location): bool
    {
        return $this->config->showPayPalButton($type, $location);
    }
    /**
     * Is Pay Later button active
     *
     * @param string $type
     * @return bool
     */
    public function isPayLaterButtonActive(string $type): bool
    {
        return $this->payPalPayLaterConfig->isButtonActive($type);
    }

    /**
     * Is PayPal vault active
     *
     * @return bool
     */
    public function isPayPalVaultActive(): bool
    {
        return $this->payPalPayLaterConfig->isPayPalVaultActive();
    }

    /**
     * Get Merchant Name
     *
     * @return string|null
     */
    public function getMerchantName(): ?string
    {
        return $this->config->getMerchantName();
    }

    /**
     * Get Button Shape
     *
     * @param string $type
     * @return string
     */
    public function getButtonShape(string $type): string
    {
        return $this->config->getButtonShape(Config::BUTTON_AREA_CART, $type);
    }

    /**
     * Get Button Color
     *
     * @param string $type
     * @return string
     */
    public function getButtonColor(string $type): string
    {
        if ($type === 'credit') {
            return $this->config->getCreditButtonColor(Config::BUTTON_AREA_CART);
        }
        return $this->config->getButtonColor(Config::BUTTON_AREA_CART, $type);
    }

    /**
     * Get Button Size
     *
     * @param string $type
     * @return string
     * @deprecated as Size field is redundant
     * @see no alternatives
     */
    public function getButtonSize(string $type): string
    {
        return $this->config->getButtonSize(Config::BUTTON_AREA_CART, $type);
    }

    /**
     * Get Button Label
     *
     * @param string $type
     * @return string
     */
    public function getButtonLabel(string $type): string
    {
        return $this->config->getButtonLabel(Config::BUTTON_AREA_CART, $type);
    }

    /**
     * Get Environment
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        return $this->braintreeConfig->getEnvironment();
    }

    /**
     * Get Client Token
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): ?string
    {
        return $this->configProvider->getClientToken();
    }

    /**
     * Get Action Success
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->skipOrderReviewStep()
            ? $this->getUrl('checkout/onepage/success', ['_secure' => true])
            : $this->getUrl(ConfigProvider::CODE . '/paypal/review', ['_secure' => true]);
    }

    /**
     * Get Disabled Funding
     *
     * @return array
     */
    public function getDisabledFunding(): array
    {
        return [
            'card' => $this->config->isFundingOptionCardDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_CART),
            'elv' => $this->config->isFundingOptionElvDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_CART)
        ];
    }

    /**
     * Get Extra Class name
     *
     * @return string
     */
    public function getExtraClassname(): string
    {
        return $this->getIsCart() ? 'cart' : 'minicart';
    }

    /**
     * Is Required Billing Address
     *
     * @return bool
     */
    public function isRequiredBillingAddress(): bool
    {
        return (bool) $this->config->isRequiredBillingAddress();
    }

    /**
     * Get Merchant Country
     *
     * @return string|null
     */
    public function getMerchantCountry(): ?string
    {
        return $this->payPalPayLaterConfig->getMerchantCountry();
    }

    /**
     * Get button styling
     *
     * @return array
     */
    public function getMessageStyles(): array
    {
        return $this->config->getMessageStyles(Config::BUTTON_AREA_CART);
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
     * Is customer logged in?
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
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
     * Get button config
     *
     * @return array
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getButtonConfig(): array
    {
        return [
            'clientToken' => $this->getClientToken(),
            'currency' => $this->getCurrency(),
            'environment' => $this->getEnvironment(),
            'merchantCountry' => $this->getMerchantCountry(),
            'isCreditActive' => $this->isCreditActive(),
            'skipOrderReviewStep' => $this->skipOrderReviewStep(),
            'pageType' => $this->request->getFullActionName() === 'checkout_cart_index' ? 'cart' : 'mini-cart',
        ];
    }

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->config->skipOrderReviewStep();
    }
}
