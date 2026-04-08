<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Ui\PayPal;

use Braintree\Result\Error;
use Braintree\Result\Successful;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalCredit\Config as CreditConfig;
use PayPal\Braintree\Gateway\Config\PayPalPayLater\Config as PayLaterConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigProvider implements ConfigProviderInterface
{
    public const PAYPAL_CODE = 'braintree_paypal';
    public const PAYPAL_CREDIT_CODE = 'braintree_paypal_credit';
    public const PAYPAL_PAYLATER_CODE = 'braintree_paypal_paylater';
    public const PAYPAL_VAULT_CODE = 'braintree_paypal_vault';

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param CreditConfig $creditConfig
     * @param PayLaterConfig $payLaterConfig
     * @param ResolverInterface $resolver
     * @param BraintreeConfig $braintreeConfig
     * @param BraintreeAdapter $braintreeAdapter
     * @param TaxHelper $taxHelper
     * @param string $clientToken
     */
    public function __construct(
        protected readonly Config $config,
        protected readonly CreditConfig $creditConfig,
        protected readonly PayLaterConfig $payLaterConfig,
        protected readonly ResolverInterface $resolver,
        protected readonly BraintreeConfig $braintreeConfig,
        protected readonly BraintreeAdapter $braintreeAdapter,
        protected readonly TaxHelper $taxHelper,
        protected string $clientToken = ''
    ) {
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $locale = $this->getLocaleForPayPal();

        return [
            'payment' => [
                self::PAYPAL_CODE => [
                    'isActive' => $this->config->isActive(),
                    'clientToken' => $this->getClientToken(),
                    'title' => $this->config->getTitle(),
                    'isAllowShippingAddressOverride' => $this->config->isAllowToEditShippingAddress(),
                    'merchantName' => $this->config->getMerchantName(),
                    'environment' => $this->braintreeConfig->getEnvironment(),
                    'merchantCountry' => $this->config->getMerchantCountry(),
                    'locale' => $locale,
                    'paymentAcceptanceMarkSrc' =>
                        'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-medium.png',
                    'vaultCode' => self::PAYPAL_VAULT_CODE,
                    'paymentIcon' => $this->config->getPayPalIcon(),
                    'style' => $this->getButtonStyles(),
                    'isRequiredBillingAddress' => $this->config->isRequiredBillingAddress(),
                    'skipOrderReviewStep' => $this->config->skipOrderReviewStep()
                ],

                self::PAYPAL_CREDIT_CODE => [
                    'isActive' => $this->creditConfig->isActive(),
                    'title' => __('PayPal Credit'),
                    'isAllowShippingAddressOverride' => $this->config->isAllowToEditShippingAddress(),
                    'merchantName' => $this->config->getMerchantName(),
                    'environment' => $this->braintreeConfig->getEnvironment(),
                    'merchantCountry' => $this->config->getMerchantCountry(),
                    'locale' => $locale,
                    'paymentAcceptanceMarkSrc' =>
                        'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-medium.png',
                    'paymentIcon' => $this->config->getPayPalIcon(),
                    'style' => $this->getButtonStyles('credit'),
                    'isRequiredBillingAddress' => $this->config->isRequiredBillingAddress(),
                    'skipOrderReviewStep' => $this->config->skipOrderReviewStep()
                ],

                self::PAYPAL_PAYLATER_CODE => [
                    'isActive' => $this->payLaterConfig->isButtonActive('checkout'),
                    'title' => __('PayPal PayLater'),
                    'isAllowShippingAddressOverride' => $this->config->isAllowToEditShippingAddress(),
                    'merchantName' => $this->config->getMerchantName(),
                    'environment' => $this->braintreeConfig->getEnvironment(),
                    'merchantCountry' => $this->config->getMerchantCountry(),
                    'locale' => $locale,
                    'paymentAcceptanceMarkSrc' =>
                        'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-medium.png',
                    'paymentIcon' => $this->config->getPayPalIcon(),
                    'isMessageActive' => $this->payLaterConfig->isMessageActive('checkout'),
                    'style' => $this->getButtonStyles('paylater'),
                    'messageStyles' => $this->getMessageStyles(),
                    'isRequiredBillingAddress' => $this->config->isRequiredBillingAddress(),
                    'skipOrderReviewStep' => $this->config->skipOrderReviewStep()
                ]
            ]
        ];
    }

    /**
     * Generate a new client token if necessary
     *
     * @return Error|Successful|string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): Error|Successful|string|null
    {
        if (empty($this->clientToken)) {
            $params = [];

            $merchantAccountId = $this->braintreeConfig->getMerchantAccountId();
            if (!empty($merchantAccountId)) {
                $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
            }

            $this->clientToken = $this->braintreeAdapter->generate($params);
        }

        return $this->clientToken;
    }

    /**
     * Get Locale for PayPal
     *
     * @return string
     */
    private function getLocaleForPayPal(): string
    {
        $locale = $this->resolver->getLocale();
        if (in_array($locale, ['nb_NO', 'nn_NO'])) {
            $locale = 'no_NO';
        }

        return $locale;
    }

    /**
     * Get button styles
     *
     * @param string $type
     * @return array
     */
    private function getButtonStyles(string $type = 'paypal'): array
    {
        if ($type === 'credit') {
            $color = $this->config->getCreditButtonColor(Config::BUTTON_AREA_CHECKOUT);
        } else {
            $color = $this->config->getButtonColor(Config::BUTTON_AREA_CHECKOUT, $type);
        }

        return [
            'label' => $this->config->getButtonLabel(Config::BUTTON_AREA_CHECKOUT, $type),
            'color' => $color,
            'shape' => $this->config->getButtonShape(Config::BUTTON_AREA_CHECKOUT, $type)
        ];
    }

    /**
     * Get Pay Later message styles
     *
     * @return array
     */
    private function getMessageStyles(): array
    {
        return [
            'text_align' => $this->config->getMessagingStyle(
                Config::BUTTON_AREA_CHECKOUT,
                'messaging',
                'text_align'
            ),
            'text_color' => $this->config->getMessagingStyle(
                Config::BUTTON_AREA_CHECKOUT,
                'messaging',
                'text_color'
            )
        ];
    }
}
