<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use InvalidArgumentException;
use PayPal\Braintree\Gateway\Request\CustomerDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class CustomerDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDOMock;

    /**
     * @var OrderAdapterInterface|MockObject
     */
    private OrderAdapterInterface|MockObject $orderMock;

    /**
     * @var CustomerDataBuilder
     */
    private CustomerDataBuilder $builder;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReaderMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $this->orderMock = $this->createMock(OrderAdapterInterface::class);
        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->builder = new CustomerDataBuilder($this->subjectReaderMock);
    }

    /**
     */
    public function testBuildReadPaymentException()
    {
        $this->markTestSkipped('Skip this test');
        $this->expectException(InvalidArgumentException::class);

        $buildSubject = [
            'payment' => null,
        ];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willThrowException(new InvalidArgumentException());

        $this->builder->build($buildSubject);
    }

    /**
     * @param array $billingData
     * @param array $expectedResult
     *
     * @dataProvider dataProviderBuild
     * @throws Exception
     */
    public function testBuild(array $billingData, array $expectedResult)
    {
        $billingMock = $this->getBillingMock($billingData);

        $this->paymentDOMock->expects(static::once())
            ->method('getOrder')
            ->willReturn($this->orderMock);
        $this->orderMock->expects(static::once())
            ->method('getBillingAddress')
            ->willReturn($billingMock);

        $buildSubject = [
            'payment' => $this->paymentDOMock,
        ];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDOMock);

        self::assertEquals($expectedResult, $this->builder->build($buildSubject));
    }

    /**
     * @return array
     */
    public static function dataProviderBuild(): array
    {
        return [
            [
                [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'company' => 'Magento',
                    'phone' => '555-555-555',
                    'email' => 'john@magento.com'
                ],
                [
                    CustomerDataBuilder::CUSTOMER => [
                        CustomerDataBuilder::FIRST_NAME => 'John',
                        CustomerDataBuilder::LAST_NAME => 'Smith',
                        CustomerDataBuilder::COMPANY => 'Magento',
                        CustomerDataBuilder::PHONE => '555-555-555',
                        CustomerDataBuilder::EMAIL => 'john@magento.com',
                    ]
                ]
            ]
        ];
    }

    /**
     * @param array $billingData
     * @return AddressAdapterInterface|MockObject
     * @throws Exception
     */
    private function getBillingMock(array $billingData): AddressAdapterInterface|MockObject
    {
        $addressMock = $this->createMock(AddressAdapterInterface::class);

        $addressMock->expects(static::once())
            ->method('getFirstname')
            ->willReturn($billingData['first_name']);
        $addressMock->expects(static::once())
            ->method('getLastname')
            ->willReturn($billingData['last_name']);
        $addressMock->expects(static::once())
            ->method('getCompany')
            ->willReturn($billingData['company']);
        $addressMock->expects(static::once())
            ->method('getTelephone')
            ->willReturn($billingData['phone']);
        $addressMock->expects(static::once())
            ->method('getEmail')
            ->willReturn($billingData['email']);

        return $addressMock;
    }
}
