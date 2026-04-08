<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Helper;

use PayPal\Braintree\Helper\CcType;
use PayPal\Braintree\Model\Adminhtml\Source\CcType as CcTypeSource;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;

class CcTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @var CcType
     */
    private CcType $helper;

    /**
     * @var CcTypeSource|MockObject
     */
    private CcTypeSource|MockObject $ccTypeSource;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->ccTypeSource = $this->getMockBuilder(CcTypeSource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['toOptionArray'])
            ->getMock();

        $this->helper = $this->objectManager->getObject(CcType::class, [
            'ccTypeSource' => $this->ccTypeSource
        ]);
    }

    public function testGetCcTypes()
    {
        $this->ccTypeSource->expects(static::once())
            ->method('toOptionArray')
            ->willReturn([
                'label' => 'VISA', 'value' => 'VI'
            ]);

        $this->helper->getCcTypes();

        $this->ccTypeSource->expects(static::never())
            ->method('toOptionArray');

        $this->helper->getCcTypes();
    }
}
