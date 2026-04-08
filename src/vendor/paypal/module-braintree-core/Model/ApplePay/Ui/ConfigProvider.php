<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay\Ui;

use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Model\ApplePay\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Helper\Data as TaxHelper;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'braintree_applepay';
    public const METHOD_VAULT_CODE = 'braintree_applepay_vault';
    private const METHOD_KEY_ACTIVE = 'payment/braintree_applepay/active';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var BraintreeAdapter
     */
    private BraintreeAdapter $adapter;

    /**
     * @var Repository
     */
    private Repository $assetRepo;

    /**
     * @var BraintreeConfig
     */
    private BraintreeConfig $braintreeConfig;

    /**
     * @var string
     */
    private string $clientToken = '';

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var TaxHelper
     */
    private TaxHelper $taxHelper;

    /**
     * @var array
     */
    private array $icon = [];

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param BraintreeAdapter $adapter
     * @param Repository $assetRepo
     * @param BraintreeConfig $braintreeConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        Config $config,
        BraintreeAdapter $adapter,
        Repository $assetRepo,
        BraintreeConfig $braintreeConfig,
        ScopeConfigInterface $scopeConfig,
        TaxHelper $taxHelper
    ) {
        $this->config = $config;
        $this->adapter = $adapter;
        $this->assetRepo = $assetRepo;
        $this->braintreeConfig = $braintreeConfig;
        $this->scopeConfig = $scopeConfig;
        $this->taxHelper = $taxHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::METHOD_CODE => [
                    'clientToken' => $this->getClientToken(),
                    'merchantName' => $this->getMerchantName(),
                    'paymentMarkSrc' => $this->getPaymentMarkSrc(),
                    'priceIncludesTax' => $this->taxHelper->priceIncludesTax(),
                    'vaultCode' => self::METHOD_VAULT_CODE
                ]
            ]
        ];
    }

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::METHOD_KEY_ACTIVE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Generate a new client token if necessary
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): ?string
    {
        if (empty($this->clientToken)) {
            $params = [];

            $merchantAccountId = $this->braintreeConfig->getMerchantAccountId();
            if (!empty($merchantAccountId)) {
                $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
            }

            $this->clientToken = $this->adapter->generate($params);
        }

        return $this->clientToken;
    }

    /**
     * Get merchant name
     *
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->config->getMerchantName();
    }

    /**
     * Get the url to the payment mark image
     *
     * @return string
     */
    public function getPaymentMarkSrc(): string
    {
        return $this->assetRepo->getUrl('PayPal_Braintree::images/applepaymark.svg');
    }

    /**
     * Get icons for available payment methods
     *
     * @return array
     * @throws LocalizedException
     */
    public function getIcon(): array
    {
        if (!empty($this->icon)) {
            return $this->icon;
        }

        $asset = $this->assetRepo->createAsset(
            'PayPal_Braintree::images/applepaymark.svg',
            ['_secure' => true]
        );

        $this->icon = [
            'url' => $asset->getUrl(),
            'width' => 47,
            'height' => 30,
            'title' => __('Apple Pay'),
        ];

        return $this->icon;
    }
}
