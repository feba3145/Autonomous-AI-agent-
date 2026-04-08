<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Request\ThreeDSecureDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\Order\AddressAdapter;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PHPUnit\Framework\MockObject\MockObject;

class ThreeDSecureDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ThreeDSecureDataBuilder
     */
    private ThreeDSecureDataBuilder $builder;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configMock;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDO;

    /**
     * @var OrderAdapter|MockObject
     */
    private OrderAdapter|MockObject $order;

    /**
     * @var AddressAdapter|MockObject
     */
    private AddressAdapter|MockObject $billingAddress;

    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReaderMock;

    protected function setUp(): void
    {
        $this->initOrderMock();

        $this->paymentDO = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'getPayment'])
            ->getMockForAbstractClass();
        $this->paymentDO->expects(static::once())
            ->method('getOrder')
            ->willReturn($this->order);

        $this->configMock = $this->getMockBuilder(Config::class)
            ->onlyMethods([
                'isVerify3DSecure',
                'is3DSAlwaysRequested',
                'getThresholdAmount',
                'get3DSecureSpecificCountries'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->builder = new ThreeDSecureDataBuilder($this->configMock, $this->subjectReaderMock);
    }

    /**
     * Test build
     *
     * @param bool $verify
     * @param bool $challengeRequested
     * @param float $thresholdAmount
     * @param string $countryId
     * @param array $countries
     * @param array $expected
     * @covers \PayPal\Braintree\Gateway\Request\ThreeDSecureDataBuilder::build
     * @dataProvider buildDataProvider
     */
    public function testBuild(
        bool $verify,
        bool $challengeRequested,
        float $thresholdAmount,
        string $countryId,
        array $countries,
        array $expected
    ) {
        $this->markTestSkipped('Skip this test');
        $buildSubject = [
            'payment' => $this->paymentDO,
            'amount' => 25
        ];

        $this->configMock->expects(static::once())
            ->method('isVerify3DSecure')
            ->willReturn($verify);

        $this->configMock->expects(static::once())
            ->method('is3DSAlwaysRequested')
            ->willReturn($challengeRequested);

        $this->configMock->expects(static::any())
            ->method('getThresholdAmount')
            ->willReturn($thresholdAmount);

        $this->configMock->expects(static::any())
            ->method('get3DSecureSpecificCountries')
            ->willReturn($countries);

        $this->billingAddress->expects(static::any())
            ->method('getCountryId')
            ->willReturn($countryId);

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($buildSubject)
            ->willReturn($this->paymentDO);
        $this->subjectReaderMock->expects(self::once())
            ->method('readAmount')
            ->with($buildSubject)
            ->willReturn(25);

        $result = $this->builder->build($buildSubject);
        static::assertEquals($expected, $result);
    }

    /**
     * Get list of variations for build test
     *
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [
                'verify' => true,
                'challengeRequested' => true,
                'amount' => 20,
                'countryId' => 'US',
                'countries' => [],
                'result' => [
                    'options' => [
                        'threeDSecure' => [
                            'required' => true
                        ]
                    ]
                ]
            ],
            [
                'verify' => true,
                'challengeRequested' => true,
                'amount' => 0,
                'countryId' => 'US',
                'countries' => ['US', 'GB'],
                'result' => [
                    'options' => [
                        'threeDSecure' => [
                            'required' => true
                        ]
                    ]
                ]
            ],
            [
                'verify' => true,
                'challengeRequested' => true,
                'amount' => 40,
                'countryId' => 'US',
                'countries' => [],
                'result' => []],
            [
                'verify' => false,
                'challengeRequested' => false,
                'amount' => 40,
                'countryId' => 'US',
                'countries' => [],
                'result' => []
            ],
            [
                'verify' => false,
                'challengeRequested' => false,
                'amount' => 20,
                'countryId' => 'US',
                'countries' => [],
                'result' => []
            ],
            [
                'verify' => true,
                'challengeRequested' => true,
                'amount' => 20,
                'countryId' => 'CA',
                'countries' => ['US', 'GB'],
                'result' => []
            ]
        ];
    }

    /**
     * Create mock object for order adapter
     */
    private function initOrderMock(): void
    {
        $this->billingAddress = $this->getMockBuilder(AddressAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCountryId'])
            ->getMock();

        $this->order = $this->getMockBuilder(OrderAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBillingAddress'])
            ->getMock();

        $this->order->expects(static::any())
            ->method('getBillingAddress')
            ->willReturn($this->billingAddress);
    }
}
