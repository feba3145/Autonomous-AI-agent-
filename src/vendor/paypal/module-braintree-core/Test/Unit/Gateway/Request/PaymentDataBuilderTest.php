<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use InvalidArgumentException;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Observer\DataAssignObserver;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    private const PAYMENT_METHOD_NONCE = 'nonce';
    private const MERCHANT_ACCOUNT_ID = '245345';

    /**
     * @var PaymentDataBuilder
     */
    private PaymentDataBuilder $builder;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configMock;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $paymentMock;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDO;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReaderMock;

    /**
     * @var OrderAdapterInterface|MockObject
     */
    private OrderAdapterInterface|MockObject $orderMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock = $this->createMock(OrderAdapterInterface::class);

        $this->builder = new PaymentDataBuilder($this->configMock, $this->subjectReaderMock);
    }

    /**
     */
    public function testBuildReadPaymentException()
    {
        $this->markTestSkipped('Skip this test');
        $this->expectException(InvalidArgumentException::class);

        $buildSubject = [];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willThrowException(new InvalidArgumentException());

        $this->builder->build($buildSubject);
    }

    /**
     */
    public function testBuildReadAmountException()
    {
        $this->markTestSkipped('Skip this test');
        $this->expectException(InvalidArgumentException::class);

        $buildSubject = [
            'payment' => $this->paymentDO,
            'amount' => null
        ];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDO);
        $this->subjectReaderMock->expects(self::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willThrowException(new InvalidArgumentException());

        $this->builder->build($buildSubject);
    }

    public function testBuild()
    {
        $additionalData = [
            [
                DataAssignObserver::PAYMENT_METHOD_NONCE,
                self::PAYMENT_METHOD_NONCE
            ]
        ];

        $expectedResult = [
            PaymentDataBuilder::AMOUNT  => 10.00,
            PaymentDataBuilder::PAYMENT_METHOD_NONCE  => self::PAYMENT_METHOD_NONCE,
            PaymentDataBuilder::ORDER_ID => '000000101',
            PaymentDataBuilder::MERCHANT_ACCOUNT_ID  => self::MERCHANT_ACCOUNT_ID,
        ];

        $buildSubject = [
            'payment' => $this->paymentDO,
            'amount' => 10.00
        ];

        $this->paymentMock->expects(static::exactly(count($additionalData)))
            ->method('getAdditionalInformation')
            ->willReturnMap($additionalData);

        $this->configMock->expects(static::once())
            ->method('getMerchantAccountId')
            ->willReturn(self::MERCHANT_ACCOUNT_ID);

        $this->paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentDO->expects(static::once())
            ->method('getOrder')
            ->willReturn($this->orderMock);

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDO);
        $this->subjectReaderMock->expects(self::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willReturn(10.00);

        $this->orderMock->expects(static::once())
            ->method('getOrderIncrementId')
            ->willReturn('000000101');

        static::assertEquals(
            $expectedResult,
            $this->builder->build($buildSubject)
        );
    }
}
