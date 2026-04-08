<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Model\Report;

use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Report\FilterMapper;
use PayPal\Braintree\Model\Report\TransactionsCollection;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for class \PayPal\Braintree\Model\Report\TransactionsCollection
 */
class TransactionsCollectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var BraintreeAdapter|MockObject
     */
    private BraintreeAdapter|MockObject $braintreeAdapterMock;

    /**
     * @var EntityFactoryInterface|MockObject
     */
    private MockObject|EntityFactoryInterface $entityFactoryMock;

    /**
     * @var FilterMapper|MockObject
     */
    private FilterMapper|MockObject $filterMapperMock;

    /**
     * @var DocumentInterface|MockObject
     */
    private DocumentInterface|MockObject $transactionMapMock;

    /**
     * Setup
     */
    protected function setUp(): void
    {
        $this->transactionMapMock = $this->getMockBuilder(DocumentInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->entityFactoryMock = $this->getMockBuilder(EntityFactoryInterface::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->filterMapperMock = $this->getMockBuilder(FilterMapper::class)
            ->onlyMethods(['getFilter'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->braintreeAdapterMock = $this->getMockBuilder(BraintreeAdapter::class)
            ->onlyMethods(['search'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get items
     *
     * @throws LocalizedException
     */
    public function testGetItems()
    {
        $this->markTestSkipped('Skip this test');
        $this->filterMapperMock->expects($this->once())
            ->method('getFilter')
            ->willReturn(new BraintreeSearchNodeStub());

        $this->braintreeAdapterMock->expects($this->once())
            ->method('search')
            ->willReturn(['transaction1', 'transaction2']);

        $this->entityFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturn($this->transactionMapMock);

        $collection = new TransactionsCollection(
            $this->entityFactoryMock,
            $this->braintreeAdapterMock,
            $this->filterMapperMock
        );

        $collection->addFieldToFilter('orderId', ['like' => '0']);
        $items = $collection->getItems();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(DocumentInterface::class, $items[1]);
    }

    /**
     * Get empty result
     * @throws LocalizedException
     */
    public function testGetItemsEmptyCollection()
    {
        $this->filterMapperMock->expects($this->once())
            ->method('getFilter')
            ->willReturn(new BraintreeSearchNodeStub());

        $this->braintreeAdapterMock->expects($this->once())
            ->method('search')
            ->willReturn(null);

        $this->entityFactoryMock->expects($this->never())
            ->method('create')
            ->willReturn($this->transactionMapMock);

        $collection = new TransactionsCollection(
            $this->entityFactoryMock,
            $this->braintreeAdapterMock,
            $this->filterMapperMock
        );

        $collection->addFieldToFilter('orderId', ['like' => '0']);
        $items = $collection->getItems();
        $this->assertCount(0, $items);
    }

    /**
     * Get items with limit
     * @throws LocalizedException
     */
    public function testGetItemsWithLimit()
    {
        $this->markTestSkipped('Skip this test');
        $transations = range(1, TransactionsCollection::TRANSACTION_MAXIMUM_COUNT + 10);

        $this->filterMapperMock->expects($this->once())
            ->method('getFilter')
            ->willReturn(new BraintreeSearchNodeStub());

        $this->braintreeAdapterMock->expects($this->once())
            ->method('search')
            ->willReturn($transations);

        $this->entityFactoryMock->expects($this->exactly(TransactionsCollection::TRANSACTION_MAXIMUM_COUNT))
            ->method('create')
            ->willReturn($this->transactionMapMock);

        $collection = new TransactionsCollection(
            $this->entityFactoryMock,
            $this->braintreeAdapterMock,
            $this->filterMapperMock
        );
        $collection->setPageSize(TransactionsCollection::TRANSACTION_MAXIMUM_COUNT);

        $collection->addFieldToFilter('orderId', ['like' => '0']);
        $items = $collection->getItems();
        $this->assertEquals(TransactionsCollection::TRANSACTION_MAXIMUM_COUNT, count($items));
        $this->assertInstanceOf(DocumentInterface::class, $items[1]);
    }

    /**
     * Get items with limit
     * @throws LocalizedException
     */
    public function testGetItemsWithNullLimit()
    {
        $this->markTestSkipped('Skip this test');
        $transations = range(1, TransactionsCollection::TRANSACTION_MAXIMUM_COUNT + 10);

        $this->filterMapperMock->expects($this->once())
            ->method('getFilter')
            ->willReturn(new BraintreeSearchNodeStub());

        $this->braintreeAdapterMock->expects($this->once())
            ->method('search')
            ->willReturn($transations);

        $this->entityFactoryMock->expects($this->exactly(TransactionsCollection::TRANSACTION_MAXIMUM_COUNT))
            ->method('create')
            ->willReturn($this->transactionMapMock);

        $collection = new TransactionsCollection(
            $this->entityFactoryMock,
            $this->braintreeAdapterMock,
            $this->filterMapperMock
        );
        $collection->setPageSize(null);

        $collection->addFieldToFilter('orderId', ['like' => '0']);
        $items = $collection->getItems();
        $this->assertEquals(TransactionsCollection::TRANSACTION_MAXIMUM_COUNT, count($items));
        $this->assertInstanceOf(DocumentInterface::class, $items[1]);
    }

    /**
     * Add fields to filter
     *
     * @param $field
     * @param $condition
     * @param $filterMapperCall
     * @param $expectedCondition
     * @return void
     * @throws LocalizedException
     * @dataProvider addToFilterDataProvider
     */
    public function testAddToFilter($field, $condition, $filterMapperCall, $expectedCondition)
    {
        $this->filterMapperMock->expects(static::exactly($filterMapperCall))
            ->method('getFilter')
            ->with($field, $expectedCondition)
            ->willReturn(new BraintreeSearchNodeStub());

        $collection = new TransactionsCollection(
            $this->entityFactoryMock,
            $this->braintreeAdapterMock,
            $this->filterMapperMock
        );

        static::assertInstanceOf(
            TransactionsCollection::class,
            $collection->addFieldToFilter($field, $condition)
        );
    }

    /**
     * addToFilter DataProvider
     *
     * @return array
     */
    public static function addToFilterDataProvider(): array
    {
        return [
            ['orderId', ['like' => 1], 1, ['like' => 1]],
            ['type', 'sale', 1, ['eq' => 'sale']],
            [['type', 'orderId'], [], 0, []],
        ];
    }
}
