<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Gateway\Config\PayPal\Config;

class SetDataForPackageTracking implements ObserverInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Set shipment tracking flag to yes if eligible
     *
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $track = $observer->getData('data_object');
        $payment = $track->getShipment()->getOrder()->getPayment();
        if (!str_contains($payment->getMethod(), 'braintree_paypal') || !$this->config->isShippingTrackingEnabled()) {
            return;
        }
        if (!str_contains($track->getDescription() ?? '', '<tracking_sent>')) {
            $track->setData('tracking_flag', true);
        }
        $track->setDescription($track->getDescription() . '<tracking_sent>');
    }
}
