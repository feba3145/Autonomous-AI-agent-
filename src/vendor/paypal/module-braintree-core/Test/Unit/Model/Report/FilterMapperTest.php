<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Model\Report;

use Braintree\RangeNode;
use Braintree\TextNode;
use PayPal\Braintree\Model\Adapter\BraintreeSearchAdapter;
use PayPal\Braintree\Model\Report\ConditionAppliers\ApplierInterface;
use PayPal\Braintree\Model\Report\ConditionAppliers\AppliersPool;
use PayPal\Braintree\Model\Report\FilterMapper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for class \PayPal\Braintree\Model\Report\FilterMapper
 */
class FilterMapperTest extends TestCase
{
    /**
     * @var BraintreeSearchAdapter|MockObject
     */
    private $braintreeSearchAdapterMock;

    /**
     * @var AppliersPool|MockObject
     */
    private $appliersPoolMock;

    /**
     * @var ApplierInterface|MockObject
     */
    private $applierMock;

    /**
     * Setup
     */
    protected function setUp(): void
    {
        $methods = [
            'id',
            'merchantAccountId',
            'orderId',
            'paypalPaymentId',
            'createdUsing',
            'type',
            'createdAt',
            'amount',
            'status',
            'settlementBatchId',
            'paymentInstrumentType',
        ];

        $this->braintreeSearchAdapterMock = $this->getMockBuilder(BraintreeSearchAdapter::class)
            ->onlyMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->appliersPoolMock = $this->getMockBuilder(AppliersPool::class)
            ->onlyMethods(['getApplier'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->applierMock = $this->getMockBuilder(ApplierInterface::class)
            ->onlyMethods(['apply'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    /**
     * Positive test
     */
    public function testGetFilterPositiveApply()
    {
        $this->applierMock->expects($this->exactly(3))
            ->method('apply')
            ->willReturn(true);

        $this->appliersPoolMock->expects($this->exactly(3))
            ->method('getApplier')
            ->willReturn($this->applierMock);

        $mapper = new FilterMapper($this->appliersPoolMock, $this->braintreeSearchAdapterMock);

        $result = $mapper->getFilter('id', ['eq' => 'value']);
        $this->assertInstanceOf(TextNode::class, $result);

        $result = $mapper->getFilter('orderId', ['eq' => 'value']);
        $this->assertInstanceOf(TextNode::class, $result);

        $result = $mapper->getFilter('amount', ['eq' => 'value']);
        $this->assertInstanceOf(RangeNode::class, $result);
    }

    /**
     * Negative test
     */
    public function testGetFilterNegativeApply()
    {
        $this->applierMock->expects($this->never())
            ->method('apply')
            ->willReturn(true);

        $this->appliersPoolMock->expects($this->once())
            ->method('getApplier')
            ->willReturn($this->applierMock);

        $mapper = new FilterMapper($this->appliersPoolMock, $this->braintreeSearchAdapterMock);
        $result = $mapper->getFilter('orderId', []);
        $this->assertNull($result);
    }
}
