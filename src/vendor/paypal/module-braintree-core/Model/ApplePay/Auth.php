<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay;

use PayPal\Braintree\Api\AuthInterface;
use PayPal\Braintree\Api\Data\AuthDataInterface;
use PayPal\Braintree\Api\Data\AuthDataInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Auth implements AuthInterface
{
    /**
     * @var AuthDataInterfaceFactory $authData
     */
    private AuthDataInterfaceFactory $authData;

    /**
     * @var Ui\ConfigProvider $configProvider
     */
    private Ui\ConfigProvider $configProvider;

    /**
     * @var UrlInterface $url
     */
    private UrlInterface $url;

    /**
     * @var CustomerSession $customerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private StoreManagerInterface $storeManager;

    /**
     * Auth constructor
     *
     * @param AuthDataInterfaceFactory $authData
     * @param Ui\ConfigProvider $configProvider
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        AuthDataInterfaceFactory $authData,
        Ui\ConfigProvider $configProvider,
        UrlInterface $url,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->authData = $authData;
        $this->configProvider = $configProvider;
        $this->url = $url;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManagerInterface;
    }

    /**
     * @inheritdoc
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get(): AuthDataInterface
    {
        /** @var AuthDataInterface $data */
        $data = $this->authData->create();
        $data->setClientToken($this->getClientToken());
        $data->setDisplayName($this->getDisplayName());
        $data->setActionSuccess($this->getActionSuccess());
        $data->setIsLoggedIn($this->isLoggedIn());
        $data->setStoreCode($this->getStoreCode());

        return $data;
    }

    /**
     * Get client token
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function getClientToken(): ?string
    {
        return $this->configProvider->getClientToken();
    }

    /**
     * Get display name
     *
     * @return string|null
     */
    protected function getDisplayName(): ?string
    {
        return $this->configProvider->getMerchantName();
    }

    /**
     * Get action success url
     *
     * @return string
     */
    protected function getActionSuccess(): string
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * Check if logged in
     *
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * Get store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }
}
