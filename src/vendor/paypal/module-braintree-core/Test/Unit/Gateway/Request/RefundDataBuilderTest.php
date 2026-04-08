<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use InvalidArgumentException;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Gateway\Request\RefundDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class RefundDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReader;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    /**
     * @var RefundDataBuilder
     */
    private RefundDataBuilder $dataBuilder;

    protected function setUp(): void
    {
        $this->subjectReader = $this->getMockBuilder(
            SubjectReader::class
        )->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->dataBuilder = new RefundDataBuilder($this->subjectReader, $this->logger);
    }

    /**
     * @throws Exception
     */
    public function testBuild()
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentModel = $this->getMockBuilder(
            Payment::class
        )->disableOriginalConstructor()
            ->getMock();

        $buildSubject = ['payment' => $paymentDO, 'amount' => 12.358];
        $transactionId = 'xsd7n';

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($paymentDO);
        $paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($paymentModel);
        $paymentModel->expects(static::once())
            ->method('getParentTransactionId')
            ->willReturn($transactionId);
        $this->subjectReader->expects(static::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willReturn($buildSubject['amount']);

        static::assertEquals(
            [
                'transaction_id' => $transactionId,
                PaymentDataBuilder::AMOUNT => '12.36'
            ],
            $this->dataBuilder->build($buildSubject)
        );
    }

    /**
     * @throws Exception
     */
    public function testBuildNullAmount()
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentModel = $this->getMockBuilder(
            Payment::class
        )->disableOriginalConstructor()
            ->getMock();

        $buildSubject = ['payment' => $paymentDO];
        $transactionId = 'xsd7n';

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($paymentDO);
        $paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($paymentModel);
        $paymentModel->expects(static::once())
            ->method('getParentTransactionId')
            ->willReturn($transactionId);
        $this->subjectReader->expects(static::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willThrowException(new InvalidArgumentException());

        static::assertEquals(
            [
                'transaction_id' => $transactionId,
                PaymentDataBuilder::AMOUNT => null
            ],
            $this->dataBuilder->build($buildSubject)
        );
    }

    /**
     * @throws Exception
     */
    public function testBuildCutOffLegacyTransactionIdPostfix()
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentModel = $this->getMockBuilder(
            Payment::class
        )->disableOriginalConstructor()
            ->getMock();

        $buildSubject = ['payment' => $paymentDO];
        $legacyTxnId = 'xsd7n-' . TransactionInterface::TYPE_CAPTURE;
        $transactionId = 'xsd7n';

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($paymentDO);
        $paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($paymentModel);
        $paymentModel->expects(static::once())
            ->method('getParentTransactionId')
            ->willReturn($legacyTxnId);
        $this->subjectReader->expects(static::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willThrowException(new InvalidArgumentException());

        static::assertEquals(
            [
                'transaction_id' => $transactionId,
                PaymentDataBuilder::AMOUNT => null
            ],
            $this->dataBuilder->build($buildSubject)
        );
    }
}
