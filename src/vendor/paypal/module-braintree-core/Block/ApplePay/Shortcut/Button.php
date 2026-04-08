<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\ApplePay\Shortcut;

use PayPal\Braintree\Block\ApplePay\AbstractButton;
use PayPal\Braintree\Model\ApplePay\Auth;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Helper\Data as TaxHelper;

class Button extends AbstractButton implements ShortcutInterface
{
    private const ALIAS_ELEMENT_INDEX = 'alias';
    private const BUTTON_ELEMENT_INDEX = 'button_id';

    /**
     * @var DefaultConfigProvider $defaultConfigProvider
     */
    private DefaultConfigProvider $defaultConfigProvider;

    /**
     * @var TaxHelper
     */
    private TaxHelper $taxHelper;

    /**
     * Button constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param Auth $auth
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param TaxHelper $taxHelper
     * @param array $data
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        Auth $auth,
        DefaultConfigProvider $defaultConfigProvider,
        TaxHelper $taxHelper,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $payment, $auth, $data);
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->taxHelper = $taxHelper;
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX);
    }

    /**
     * Get container id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX);
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }

        return '';
    }

    /**
     * Get extra class name
     *
     * @return string
     */
    public function getExtraClassname(): string
    {
        return $this->getIsCart() ? 'cart' : 'minicart';
    }

    /**
     * Check if product prices includes tax.
     *
     * @return bool
     */
    public function priceIncludesTax(): bool
    {
        return $this->taxHelper->priceIncludesTax();
    }
}
