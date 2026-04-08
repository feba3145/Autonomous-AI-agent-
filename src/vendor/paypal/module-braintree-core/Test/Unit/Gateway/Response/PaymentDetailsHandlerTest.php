<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Response;

use Braintree\Transaction;
use Magento\Framework\App\State;
use PayPal\Braintree\Gateway\Response\PaymentDetailsHandler;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class PaymentDetailsHandlerTest extends \PHPUnit\Framework\TestCase
{
    public const TRANSACTION_ID = '432erwwe';

    /**
     * @var PaymentDetailsHandler
     */
    private PaymentDetailsHandler $paymentHandler;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReader;

    /**
     * @var State|MockObject
     */
    private State|MockObject $appState;

    protected function setUp(): void
    {
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setCcTransId',
                'setLastTransId',
                'setAdditionalInformation'
            ])
            ->getMock();
        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->appState = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->payment->expects(static::once())
            ->method('setCcTransId');
        $this->payment->expects(static::once())
            ->method('setLastTransId');
        $this->payment->expects(static::any())
            ->method('setAdditionalInformation');

        $this->paymentHandler = new PaymentDetailsHandler($this->subjectReader, $this->appState);
    }

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

        $this->paymentHandler->handle($subject, $response);
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
            'id' => self::TRANSACTION_ID,
            'avsPostalCodeResponseCode' => 'M',
            'avsStreetAddressResponseCode' => 'M',
            'cvvResponseCode' => 'M',
            'processorAuthorizationCode' => 'W1V8XK',
            'processorResponseCode' => '1000',
            'processorResponseText' => 'Approved'
        ];

        return Transaction::factory($attributes);
    }
}
