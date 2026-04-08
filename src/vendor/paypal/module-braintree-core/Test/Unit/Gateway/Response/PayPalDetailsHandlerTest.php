<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Response;

use Braintree\Transaction;
use PayPal\Braintree\Gateway\Response\PayPalDetailsHandler;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class PayPalDetailsHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PayPalDetailsHandler|MockObject
     */
    private PayPalDetailsHandler|MockObject $payPalHandler;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReader;

    protected function setUp(): void
    {
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setAdditionalInformation',
            ])
            ->getMock();
        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->payPalHandler = new PayPalDetailsHandler($this->subjectReader);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Response\PayPalDetailsHandler::handle
     */
    public function testHandle()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $transaction = $this->getBraintreeTransaction();

        $subject = ['payment' => $paymentData];
        $response = ['object' => $transaction];

        $this->subjectReader->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);
        $this->subjectReader->expects(self::once())
            ->method('readTransaction')
            ->with($response)
            ->willReturn($transaction);
        $this->subjectReader->expects(static::once())
            ->method('readPayPal')
            ->with($transaction)
            ->willReturn($transaction->paypal);

        $this->payment->expects(static::exactly(2))
            ->method('setAdditionalInformation');

        $this->payPalHandler->handle($subject, $response);
    }

    /**
     * Create mock for payment data object and order payment
     * @return MockObject
     */
    private function getPaymentDataObjectMock(): MockObject
    {
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
            'id' => '23ui8be',
            'paypal' => [
                'paymentId' => 'u239dkv6n2lds',
                'payerEmail' => 'example@test.com'
            ]
        ];

        return Transaction::factory($attributes);
    }
}
