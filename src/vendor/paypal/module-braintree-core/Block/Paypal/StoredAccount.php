<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Paypal;

use Magento\Catalog\Block\ShortcutInterface;

/**
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class StoredAccount extends Button implements ShortcutInterface
{
    /**
     * Always return true as enabled check is handled in view model.
     *
     * @return bool
     * @see \PayPal\Braintree\Block\Paypal\Button::isActive
     */
    public function isActive(): bool
    {
        return true;
    }
}
