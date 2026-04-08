<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Helper;

use PayPal\Braintree\Helper\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Directory\Model\ResourceModel\Country\Collection;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;

class CountryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Collection|MockObject
     */
    private MockObject|Collection $collection;

    /**
     * @var Country
     */
    private Country $helper;

    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $collectionFactory = $this->getCollectionFactoryMock();

        $this->helper = $this->objectManager->getObject(Country::class, [
            'factory' => $collectionFactory
        ]);
    }

    public function testGetCountries()
    {
        $this->collection->expects(static::once())
            ->method('toOptionArray')
            ->willReturn([
                ['value' => 'US', 'label' => 'United States'],
                ['value' => 'UK', 'label' => 'United Kingdom'],
            ]);

        $this->helper->getCountries();

        $this->collection->expects(static::never())
            ->method('toOptionArray');

        $this->helper->getCountries();
    }

    /**
     * Create mock for country collection factory
     *
     * @return CollectionFactory|MockObject
     */
    protected function getCollectionFactoryMock(): CollectionFactory|MockObject
    {
        $this->collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'loadData', 'toOptionArray', '__wakeup'])
            ->getMock();

        $this->collection->expects(static::any())
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this->collection->expects(static::any())
            ->method('loadData')
            ->willReturnSelf();

        $collectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $collectionFactory->expects(static::once())
            ->method('create')
            ->willReturn($this->collection);

        return $collectionFactory;
    }
}
