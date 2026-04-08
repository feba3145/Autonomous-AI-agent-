<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Credit\Calculator;

use Magento\Framework\View\Element\Template;
use PayPal\Braintree\Gateway\Config\PayPalCredit\Config as PayPalCreditConfig;

/**
 * @api
 * @since 100.0.2
 */
class Cart extends Template
{
    /**
     * @var PayPalCreditConfig $config
     */
    private PayPalCreditConfig $config;

    /**
     * Product constructor
     *
     * @param Template\Context $context
     * @param PayPalCreditConfig $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        PayPalCreditConfig $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if ($this->config->isCalculatorEnabled()) {
            return parent::_toHtml();
        }

        return '';
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
}
