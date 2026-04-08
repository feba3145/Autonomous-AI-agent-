<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Controller\Paypal;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Paypal\Helper\QuoteUpdater;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Used by the product page to create a quote for a single product
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OneClick extends Review implements ActionInterface
{
    /**
     * @var QuoteFactory
     */
    protected QuoteFactory $quoteFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var Validator
     */
    protected Validator $formKeyValidator;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var CartInterface
     */
    protected CartInterface $quote;

    /**
     * @var Data
     */
    protected Data $jsonHelper;

    /**
     * @var Json
     */
    protected $json;

    /**
     * OneClick constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param Session $checkoutSession
     * @param QuoteUpdater $quoteUpdater
     * @param ProductRepositoryInterface $productRepository
     * @param QuoteFactory $quoteFactory
     * @param Validator $formKeyValidator
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $checkoutSession,
        QuoteUpdater $quoteUpdater,
        ProductRepositoryInterface $productRepository,
        QuoteFactory $quoteFactory,
        Validator $formKeyValidator,
        StoreManagerInterface $storeManager,
        Json $json
    ) {
        parent::__construct(
            $context,
            $config,
            $checkoutSession,
            $quoteUpdater,
            $json
        );

        $this->productRepository = $productRepository;
        $this->quoteFactory = $quoteFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->storeManager = $storeManager;
        $this->json = $json;
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        // Convert JSON form fields to array keys & extract form_key.
        $requestData = $this->json->unserialize(
            $this->getRequest()->getPostValue('result', '{}')
        );

        if (empty($requestData)) {
            return $resultRedirect->setPath('braintree/paypal/review');
        }

        if (!empty($requestData['additionalData'])) {
            parse_str($requestData['additionalData'], $requestData['additionalData']); /* @codingStandardsIgnoreLine */
        }

        if (!empty($requestData['additionalData']['form_key'])) {
            $this->getRequest()->setParams(['form_key' => $requestData['additionalData']['form_key']]);
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage('Invalid Form key');

            return $resultRedirect->setPath($this->_redirect->getRefererUrl());
        }

        // Retrieve product form values.
        if (empty($requestData['additionalData']['product'])) {
            $this->messageManager->addErrorMessage('No product specified');

            return $resultRedirect->setPath($this->_redirect->getRefererUrl());
        }

        // Create a blank quote to just purchase this one product.
        $quote = $this->quoteFactory->create();

        /**
         * This is always set to true due to an unknown issue
         * whereby the shipping address association with the
         * quote is lost when placing order when logged in.
         */
        $quote->setCustomerIsGuest(1);

        /** @var CartItemInterface $product */
        try {
            $product = $this->productRepository->getById(
                $requestData['additionalData']['product'],
                false,
                $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addExceptionMessage($e);

            return $resultRedirect->setPath($this->_redirect->getRefererUrl());
        }

        // Add product to quote.
        $quote->setInventoryProcessed(false);
        $additionalData = new DataObject;
        $additionalData->setData($requestData['additionalData']);
        try {
            $quote->addProduct($product, $additionalData);
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e);
            return $resultRedirect->setPath($this->_redirect->getRefererUrl());
        }
        $quote->collectTotals();
        $quote->save($quote);
        $quote->setTotalsCollectedFlag(false);

        // Replace the user's current cart with this one to ensure the place order actions work correctly.
        $this->checkoutSession->setBraintreeOneClickQuoteId($quote->getId());
        $this->checkoutSession->replaceQuote($quote);

        return parent::execute();
    }

    /**
     * Set json
     *
     * @param Json $json
     * @return $this
     */
    public function setJson(Json $json): OneClick
    {
        $this->json = $json;
        return $this;
    }

    /**
     * Return this controller's quote instance.
     *
     * @return CartInterface
     */
    protected function getQuote(): CartInterface
    {
        if ($this->quote) {
            return $this->quote;
        }

        return parent::getQuote();
    }
}
