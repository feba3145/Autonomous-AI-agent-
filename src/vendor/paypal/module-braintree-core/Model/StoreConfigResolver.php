<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Sales\Model\OrderRepository;
use Magento\Backend\Model\Session\Quote as SessionQuote;

/**
 * @codeCoverageIgnore
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class StoreConfigResolver
{
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var RequestHttp
     */
    protected RequestHttp $request;

    /**
     * @var OrderRepository
     */
    protected OrderRepository $orderRepository;

    /**
     * @var SessionQuote
     */
    protected SessionQuote $sessionQuote;

    /**
     * StoreConfigResolver constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param RequestHttp $request
     * @param OrderRepository $orderRepository
     * @param SessionQuote $sessionQuote
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        RequestHttp $request,
        OrderRepository $orderRepository,
        SessionQuote $sessionQuote
    ) {
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->sessionQuote = $sessionQuote;
    }

    /**
     * Get store id for config values
     *
     * @return int|null
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getStoreId(): ?int
    {
        $currentStoreId = null;
        $currentStoreIdInAdmin = (int) $this->sessionQuote->getStoreId();
        if (!$currentStoreIdInAdmin) {
            $currentStoreId = (int) $this->storeManager->getStore()->getId();
        }
        $dataParams = $this->request->getParams();
        if (isset($dataParams['order_id'])) {
            $order = $this->orderRepository->get($dataParams['order_id']);
            if ($order->getEntityId()) {
                return (int) $order->getStoreId();
            }
        }

        return $currentStoreId ?: $currentStoreIdInAdmin;
    }
}
