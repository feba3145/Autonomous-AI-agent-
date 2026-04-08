<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay\Auth;

use PayPal\Braintree\Api\Data\AuthDataInterface;

class Data implements AuthDataInterface
{
    /**
     * @var ?string $clientToken
     */
    private ?string $clientToken;

    /**
     * @var string $displayName
     */
    private string $displayName;

    /**
     * @var string $actionSuccess
     */
    private string $actionSuccess;

    /**
     * @var bool $isLoggedIn
     */
    private bool $isLoggedIn;

    /**
     * @var string $storeCode
     */
    private string $storeCode;

    /**
     * @inheritdoc
     */
    public function getClientToken(): ?string
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
    public function isLoggedIn(): bool
    {
        return $this->isLoggedIn;
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
    public function setClientToken(string|null $value): ?string
    {
        return $this->clientToken = $value;
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
    public function setDisplayName($value): ?string
    {
        return $this->displayName = $value;
    }

    /**
     * @inheritdoc
     */
    public function setIsLoggedIn($value): bool
    {
        return $this->isLoggedIn = $value;
    }

    /**
     * @inheritdoc
     */
    public function setStoreCode($value): ?string
    {
        return $this->storeCode = $value;
    }

    /**
     * @inheritdoc
     */
    public function setActionSuccess($value): ?string
    {
        return $this->actionSuccess = $value;
    }
}
