<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Response;

use Braintree\Transaction;
use PayPal\Braintree\Gateway\Response\CardDetailsHandler;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PHPUnit\Framework\MockObject\MockObject;

class CardDetailsHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CardDetailsHandler
     */
    private CardDetailsHandler $cardHandler;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReaderMock;

    protected function setUp(): void
    {
        $this->initConfigMock();
        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->cardHandler = new CardDetailsHandler($this->config, $this->subjectReaderMock);
    }

    public function testHandle()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $transaction = $this->getBraintreeTransaction();

        $subject = ['payment' => $paymentData];
        $response = ['object' => $transaction];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);
        $this->subjectReaderMock->expects(self::once())
            ->method('readTransaction')
            ->with($response)
            ->willReturn($transaction);

        $this->payment->expects(static::once())
            ->method('setCcLast4');
        $this->payment->expects(static::once())
            ->method('setCcExpMonth');
        $this->payment->expects(static::once())
            ->method('setCcExpYear');
        $this->payment->expects(static::once())
            ->method('setCcType');
        $this->payment->expects(static::exactly(2))
            ->method('setAdditionalInformation');

        $this->cardHandler->handle($subject, $response);
    }

    /**
     * Create mock for gateway config
     */
    private function initConfigMock(): void
    {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCctypesMapper'])
            ->getMock();

        $this->config->expects(static::once())
            ->method('getCctypesMapper')
            ->willReturn([
                'american-express' => 'AE',
                'discover' => 'DI',
                'jcb' => 'JCB',
                'mastercard' => 'MC',
                'master-card' => 'MC',
                'visa' => 'VI'
            ]);
    }

    /**
     * Create mock for payment data object and order payment
     * @return MockObject
     */
    private function getPaymentDataObjectMock(): MockObject
    {
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setCcLast4',
                'setCcExpMonth',
                'setCcExpYear',
                'setCcType',
                'setAdditionalInformation',
            ])
            ->getMock();

        $mock = $this->getMockBuilder(PaymentDataObject::class)
            ->onlyMethods(['getPayment'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->payment);

        return $mock;
    }

    /**
     * Create Braintree transaction
     * @return Transaction
     */
    private function getBraintreeTransaction(): Transaction
    {
        $attributes = [
            'creditCard' => [
                'bin' => '5421',
                'cardType' => 'American Express',
                'expirationMonth' => 12,
                'expirationYear' => 21,
                'last4' => 1231
            ]
        ];

        return Transaction::factory($attributes);
    }
}
