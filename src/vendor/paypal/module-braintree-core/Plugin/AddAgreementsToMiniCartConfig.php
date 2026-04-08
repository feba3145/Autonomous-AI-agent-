<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;

/**
 * A plugin class to add agreements ids to the mini cart config
 */
class AddAgreementsToMiniCartConfig
{
    /**
     * @var AgreementsConfigProvider
     */
    private AgreementsConfigProvider $agreementsConfigProvider;

    /**
     * @param AgreementsConfigProvider $agreementsConfigProvider
     */
    public function __construct(AgreementsConfigProvider $agreementsConfigProvider)
    {
        $this->agreementsConfigProvider = $agreementsConfigProvider;
    }

    /**
     * Get config
     *
     * @param Sidebar $subject
     * @param array $result
     * @return array
     */
    public function afterGetConfig(
        Sidebar $subject,
        array $result
    ): array {
        $checkoutAgreements = $this->agreementsConfigProvider->getConfig();
        if (isset($checkoutAgreements['checkoutAgreements']['agreements'])) {
            foreach ($checkoutAgreements['checkoutAgreements']['agreements'] as $agreement) {
                $result['agreementIds'][] = $agreement['agreementId'];
            }
        }
        return $result;
    }
}
