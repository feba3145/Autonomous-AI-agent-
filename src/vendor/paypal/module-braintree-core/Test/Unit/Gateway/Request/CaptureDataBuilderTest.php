<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\CaptureDataBuilder;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class CaptureDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CaptureDataBuilder
     */
    private CaptureDataBuilder $builder;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDO;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReaderMock;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configMock;

    /**
     * @var OrderAdapterInterface|MockObject
     */
    private OrderAdapterInterface|MockObject $order;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->order = $this->getMockBuilder(OrderAdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->builder = new CaptureDataBuilder($this->subjectReaderMock, $this->configMock);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Request\CaptureDataBuilder::build
     */
    public function testBuildWithException()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No authorization transaction to proceed capture.');

        $amount = 10.00;
        $buildSubject = [
            'payment' => $this->paymentDO,
            'amount' => $amount
        ];

        $this->payment->expects(static::once())
            ->method('getCcTransId')
            ->willReturn('');

        $this->paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->payment);

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDO);

        $this->builder->build($buildSubject);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Request\CaptureDataBuilder::build
     * @throws LocalizedException
     */
    public function testBuild()
    {
        $transactionId = 'b3b99d';
        $amount = 10.00;
        $orderId = '000000002';
        $merchantAccountId = 'test';

        $expected = [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'orderId' => $orderId,
            'merchantAccountId' => $merchantAccountId
        ];

        $buildSubject = [
            'payment' => $this->paymentDO,
            'amount' => $amount
        ];

        $this->payment->expects(static::once())
            ->method('getCcTransId')
            ->willReturn($transactionId);

        $this->paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->payment);

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDO);
        $this->subjectReaderMock->expects(self::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willReturn($amount);

        $this->paymentDO->expects(static::any())
            ->method('getOrder')
            ->willReturn($this->order);

        $this->order->expects(static::once())
            ->method('getOrderIncrementId')
            ->willReturn($orderId);

        $this->configMock->expects(static::once())
            ->method('getMerchantAccountId')
            ->willReturn($merchantAccountId);

        $this->order->expects(static::once())
            ->method('getStoreId')
            ->willReturn($orderId);

        static::assertEquals($expected, $this->builder->build($buildSubject));
    }
}
