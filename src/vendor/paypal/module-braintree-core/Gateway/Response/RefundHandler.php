<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Response;

use Braintree\Transaction;
use Magento\Sales\Model\Order\Payment;

class RefundHandler extends VoidHandler
{
    /**
     * Set original refund transaction ID instead of '-refund' suffix to parent transaction ID
     *
     * @param Payment $orderPayment
     * @param Transaction $transaction
     * @return void
     */
    protected function setTransactionId(
        Payment $orderPayment,
        Transaction $transaction
    ): void {
        $orderPayment->setTransactionId($transaction->id);
        $orderPayment->getCreditmemo()->setTransactionId($transaction->id);
    }

    /**
     * Whether parent transaction should be closed
     *
     * @param Payment $orderPayment
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldCloseParentTransaction(Payment $orderPayment): bool
    {
        return !(bool)$orderPayment->getCreditmemo()->getInvoice()->canRefund();
    }
}
