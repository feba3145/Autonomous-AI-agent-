<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Gateway\Request\AddressDataBuilder;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class UpdatePaymentMethod extends Action implements ActionInterface, HttpGetActionInterface
{
    /**
     * @var BraintreeAdapter
     */
    private BraintreeAdapter $adapter;

    /**
     * @var PaymentTokenManagementInterface
     */
    private PaymentTokenManagementInterface $tokenManagement;

    /**
     * @var SessionManagerInterface
     */
    private SessionManagerInterface $session;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * UpdatePaymentMethod constructor.
     *
     * @param Context $context
     * @param BraintreeAdapter $adapter
     * @param PaymentTokenManagementInterface $tokenManagement
     * @param SessionManagerInterface $session
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        BraintreeAdapter $adapter,
        PaymentTokenManagementInterface $tokenManagement,
        SessionManagerInterface $session,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->adapter = $adapter;
        $this->tokenManagement = $tokenManagement;
        $this->session = $session;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Execute update payment method
     *
     * @return ResponseInterface|ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $publicHash = $this->getRequest()->getParam('public_hash');
        $nonce = $this->getRequest()->getParam('nonce');

        $customerId = $this->session->getCustomerId();
        $billingAddressRequest = [];
        if ($this->checkoutSession->getQuoteId()) {
            $quote = $this->checkoutSession->getQuote();
            $billingAddress = $quote->getBillingAddress();
            if ($billingAddress) {
                $street = $billingAddress->getStreet();
                $streetAddress = array_shift($street);
                $extendedAddress = implode(', ', $street);

                $billingAddressRequest = [
                    AddressDataBuilder::FIRST_NAME => $billingAddress->getFirstname(),
                    AddressDataBuilder::LAST_NAME => $billingAddress->getLastname(),
                    AddressDataBuilder::COMPANY => $billingAddress->getCompany(),
                    AddressDataBuilder::STREET_ADDRESS => $streetAddress,
                    AddressDataBuilder::EXTENDED_ADDRESS => $extendedAddress,
                    AddressDataBuilder::LOCALITY => $billingAddress->getCity(),
                    AddressDataBuilder::REGION => $billingAddress->getRegionCode(),
                    AddressDataBuilder::POSTAL_CODE => $billingAddress->getPostcode(),
                    AddressDataBuilder::COUNTRY_CODE => $billingAddress->getCountryId()
                ];
            }
        }

        $paymentToken = $this->tokenManagement->getByPublicHash($publicHash, $customerId);

        $result = $this->adapter->updatePaymentMethod(
            $paymentToken->getGatewayToken(),
            [
                'paymentMethodNonce' => $nonce,
                'options' => [
                    'verifyCard' => true
                ],
                'billingAddress' => $billingAddressRequest
            ]
        );

        $response->setData(['success' => (bool) $result->success]);

        return $response;
    }
}
