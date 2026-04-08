<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Api\Data;

/**
 * Interface AuthDataInterface
 * @api
 **/
interface AuthDataInterface
{
    /**
     * Braintree client token
     *
     * @return string|null
     */
    public function getClientToken(): ?string;

    /**
     * Merchant display name
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * URL To success page
     *
     * @return string
     */
    public function getActionSuccess(): string;

    /**
     * Is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool;

    /**
     * Get current store code
     *
     * @return string
     */
    public function getStoreCode(): string;

    /**
     * Set Braintree client token
     *
     * @param string|null $value
     * @return string|null
     */
    public function setClientToken(string|null $value): ?string;

    /**
     * Set Merchant display name
     *
     * @param string|null $value
     * @return string|null
     */
    public function setDisplayName(string|null $value): ?string;

    /**
     * Set URL To success page
     *
     * @param string|null $value
     * @return string|null
     */
    public function setActionSuccess(string|null $value): ?string;

    /**
     * Set if user is logged in
     *
     * @param bool $value
     * @return bool
     */
    public function setIsLoggedIn(bool $value): bool;

    /**
     * Set current store code
     *
     * @param string|null $value
     * @return string|null
     */
    public function setStoreCode(string|null $value): ?string;
}
