<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\GooglePay\Auth;

use PayPal\Braintree\Api\Data\AuthDataInterface;

class Data implements AuthDataInterface
{
    /**
     * @var string
     */
    private string $clientToken;

    /**
     * @var string
     */
    private string $displayName;

    /**
     * @var string
     */
    private string $actionSuccess;

    /**
     * @var bool
     */
    private bool $isLoggedIn;

    /**
     * @var string
     */
    private string $storeCode;

    /**
     * @inheritdoc
     */
    public function getClientToken(): string
    {
        return $this->clientToken;
    }

    /**
     * @inheritdoc
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @inheritdoc
     */
    public function getActionSuccess(): string
    {
        return $this->actionSuccess;
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn(): bool
    {
        return $this->isLoggedIn;
    }

    /**
     * @inheritdoc
     */
    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    /**
     * @inheritdoc
     */
    public function setClientToken(string|null $value): ?string
    {
        return $this->clientToken = $value;
    }

    /**
     * @inheritdoc
     */
    public function setDisplayName(string|null $value): ?string
    {
        return $this->displayName = $value;
    }

    /**
     * @inheritdoc
     */
    public function setActionSuccess(string|null $value): ?string
    {
        return $this->actionSuccess = $value;
    }

    /**
     * @inheritdoc
     */
    public function setIsLoggedIn(bool $value): bool
    {
        return $this->isLoggedIn = $value;
    }

    /**
     * @inheritdoc
     */
    public function setStoreCode(string|null $value): ?string
    {
        return $this->storeCode = $value;
    }
}
