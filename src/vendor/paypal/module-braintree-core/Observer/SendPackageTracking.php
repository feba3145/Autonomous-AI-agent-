<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Tracking\SendTracking;

class SendPackageTracking implements ObserverInterface
{
    /**
     * @param Config $config
     * @param SendTracking $sendTracking
     */
    public function __construct(
        private readonly Config $config,
        private readonly SendTracking $sendTracking
    ) {
    }

    /**
     * Send shipment tracking information
     *
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $track = $observer->getData('data_object');
        $shipment = $track->getShipment();
        $order = $shipment->getOrder();
        $payment = $order->getPayment();
        if (!$this->config->isShippingTrackingEnabled()
            || !$order->getInvoiceCollection()->getSize()
            || str_contains($track->getDescription() ?? '', "<sent_to_paypal>")
            || !$track->getData('tracking_flag')
            || !str_contains($order->getPayment()->getMethod(), 'braintree_paypal')) {
            return;
        }

        $items = [];
        try {
            foreach ($shipment->getItems() as $item) {
                if (!$item->getQty()) {
                    continue;
                }
                $items[] = [
                    'name' => substr($item->getName(), 0, 127),
                    'productCode' => substr($item->getSku(), 0, 127),
                    'quantity' => $item->getQty()
                ];
            }
            $this->sendTracking->execute($payment->getLastTransId(), $track, $items);
        } catch (Exception $e) {
            // cannot leave a comment for a shipment here so do nothing :(
        }
    }
}
