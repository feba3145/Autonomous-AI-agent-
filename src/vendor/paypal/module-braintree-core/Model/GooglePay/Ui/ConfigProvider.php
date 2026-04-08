<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\GooglePay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Config\GooglePay\Config as GooglePayConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Model\GooglePay\Config;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Tax\Helper\Data as TaxHelper;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'braintree_googlepay';
    public const METHOD_VAULT_CODE = 'braintree_googlepay_vault';

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
     * @var GooglePayConfig
     */
    protected GooglePayConfig $googlePayConfig;

    /**
     * @var string
     */
    private string $clientToken = '';

    /**
     * @var string
     */
    private string $fileId = 'PayPal_Braintree::images/GooglePay_AcceptanceMark.png';

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
     * @param GooglePayConfig $googlePayConfig
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        Config $config,
        BraintreeAdapter $adapter,
        Repository $assetRepo,
        BraintreeConfig $braintreeConfig,
        GooglePayConfig $googlePayConfig,
        TaxHelper $taxHelper
    ) {
        $this->config = $config;
        $this->adapter = $adapter;
        $this->assetRepo = $assetRepo;
        $this->braintreeConfig = $braintreeConfig;
        $this->googlePayConfig = $googlePayConfig;
        $this->taxHelper = $taxHelper;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::METHOD_CODE => [
                    'environment' => $this->getEnvironment(),
                    'clientToken' => $this->getClientToken(),
                    'merchantId' => $this->getMerchantId(),
                    'cardTypes' => $this->getAvailableCardTypes(),
                    'btnColor' => $this->getBtnColor(),
                    'paymentMarkSrc' => $this->getPaymentMarkSrc(),
                    'vaultCode' => self::METHOD_VAULT_CODE,
                    'skipOrderReviewStep' => $this->skipOrderReviewStep(),
                    'priceIncludesTax' => $this->taxHelper->priceIncludesTax(),
                ]
            ]
        ];
    }

    /**
     * Generate a new client token if necessary
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): string
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
     * Get environment
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        return $this->config->getEnvironment();
    }

    /**
     * Get merchant name
     *
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->config->getMerchantId();
    }

    /**
     * Get button color
     *
     * @return int
     */
    public function getBtnColor(): int
    {
        return $this->config->getBtnColor();
    }

    /**
     * Get available card types
     *
     * @return array
     */
    public function getAvailableCardTypes(): array
    {
        return $this->config->getAvailableCardTypes();
    }

    /**
     * Get the url to the payment mark image
     *
     * @return string
     */
    public function getPaymentMarkSrc(): string
    {
        return $this->assetRepo->getUrl($this->fileId);
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
            $this->fileId,
            ['_secure' => true]
        );

        $this->icon = [
            'url' => $asset->getUrl(),
            'width' => 47,
            'height' => 25,
            'title' => __('Google Pay'),
        ];

        return $this->icon;
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
}
