<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\BraintreeReward\Plugin\Level23Processing\PayPal;

use Braintree\TransactionLineItem;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config as PayPalConfig;
use PayPal\Braintree\Gateway\Data\Order\OrderAdapter;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\PayPal\Level23ProcessingDataBuilder;

class AddRewardPlugin
{
    /**
     * @var SubjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * @var quoteRepository
     */
    private QuoteRepository $quoteRepository;

    /**
     * @var PayPalConfig
     */
    private PayPalConfig $payPalConfig;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param QuoteRepository $quoteRepository
     * @param PayPalConfig $payPalConfig
     */
    public function __construct(
        SubjectReader $subjectReader,
        QuoteRepository $quoteRepository,
        PayPalConfig $payPalConfig
    ) {
        $this->subjectReader = $subjectReader;
        $this->quoteRepository = $quoteRepository;
        $this->payPalConfig = $payPalConfig;
    }

    /**
     * Add 'Reward Points' as Line Items for the PayPal transactions
     *
     * @param Level23ProcessingDataBuilder $subject
     * @param array $result
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterBuild(
        Level23ProcessingDataBuilder $subject,
        array $result,
        array $buildSubject
    ): array {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();
        /** @var OrderAdapter $order */
        $order = $paymentDO->getOrder();

        $ppRewardLineItems = $this->payPalConfig->canSendCartLineItemsForPayPal();

        if (isset($result[Level23ProcessingDataBuilder::KEY_LINE_ITEMS])
            && $ppRewardLineItems
        ) {
            $lineItems = $result[Level23ProcessingDataBuilder::KEY_LINE_ITEMS];

            /** Render quote from order to get reward currency amount */
            $quote = $this->quoteRepository->get($order->getQuoteId());

            /**
             * Adds Reward Currency Amount as credit LineItems for the PayPal
             * transaction if reward amount is greater than 0(Zero)
             * to manage the totals with server-side implementation
             */
            if ($quote->getBaseRewardCurrencyAmount()) {
                $rewardCurrencyAmount = $subject->numberToString(
                    abs((float)$quote->getBaseRewardCurrencyAmount()),
                    2
                );
                if ($rewardCurrencyAmount > 0) {
                    $storeCreditItems[] = [
                        'name' => 'Reward Points Redeemed',
                        'kind' => TransactionLineItem::CREDIT,
                        'quantity' => 1.00,
                        'unitAmount' => $rewardCurrencyAmount,
                        'totalAmount' => $rewardCurrencyAmount
                    ];

                    $lineItems = array_merge($lineItems, $storeCreditItems);
                }
            }

            if (count($lineItems) < 250) {
                $result[Level23ProcessingDataBuilder::KEY_LINE_ITEMS] = $lineItems;
            } else {
                unset($result[Level23ProcessingDataBuilder::KEY_LINE_ITEMS]);
            }
        }

        return $result;
    }
}
