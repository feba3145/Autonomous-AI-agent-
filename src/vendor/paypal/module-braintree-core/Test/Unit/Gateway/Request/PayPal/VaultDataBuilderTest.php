<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request\PayPal;

use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\PayPal\VaultDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class VaultDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReader;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDataObject;

    /**
     * @var InfoInterface|MockObject
     */
    private InfoInterface|MockObject $paymentInfo;

    /**
     * @var VaultDataBuilder
     */
    private VaultDataBuilder $builder;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->paymentDataObject = $this->createMock(PaymentDataObjectInterface::class);

        $this->paymentInfo = $this->createMock(InfoInterface::class);

        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['readPayment'])
            ->getMock();

        $this->builder = new VaultDataBuilder($this->subjectReader);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Request\PayPal\VaultDataBuilder::build
     * @param array $additionalInfo
     * @param array $expected
     * @dataProvider buildDataProvider
     */
    public function testBuild(array $additionalInfo, array $expected)
    {
        $subject = [
            'payment' => $this->paymentDataObject
        ];

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($this->paymentDataObject);

        $this->paymentDataObject->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->paymentInfo);

        $this->paymentInfo->expects(static::once())
            ->method('getAdditionalInformation')
            ->willReturn($additionalInfo);

        $actual = $this->builder->build($subject);
        static::assertEquals($expected, $actual);
    }

    /**
     * Get variations to test build method
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [
                'additionalInfo' => [
                    VaultConfigProvider::IS_ACTIVE_CODE => true
                ],
                'expected' => [
                    'options' => [
                        'storeInVaultOnSuccess' => true
                    ]
                ]
            ],
            [
                'additionalInfo' => [
                    VaultConfigProvider::IS_ACTIVE_CODE => false
                ],
                'expected' => []
            ],
            [
                'additionalInfo' => [],
                'expected' => []
            ],
        ];
    }
}
