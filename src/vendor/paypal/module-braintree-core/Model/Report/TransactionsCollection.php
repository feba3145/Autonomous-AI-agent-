<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report;

use Braintree\ResourceCollection;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Report\Row\TransactionMap;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface as BaseSearchCriteriaInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;

class TransactionsCollection extends Collection implements SearchResultInterface
{
    public const TRANSACTION_MAXIMUM_COUNT = 100;

    /**
     * Item object class name
     *
     * @var string
     */
    protected $_itemObjectClass = TransactionMap::class;

    /**
     * @var array
     */
    private array $filtersList = [];

    /**
     * @var FilterMapper
     */
    private FilterMapper $filterMapper;

    /**
     * @var BraintreeAdapter
     */
    private BraintreeAdapter $braintreeAdapter;

    /**
     * @var ResourceCollection|null
     */
    private ?ResourceCollection $collection;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param BraintreeAdapter $braintreeAdapter
     * @param FilterMapper $filterMapper
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        BraintreeAdapter $braintreeAdapter,
        FilterMapper $filterMapper
    ) {
        parent::__construct($entityFactory);
        $this->filterMapper = $filterMapper;
        $this->braintreeAdapter = $braintreeAdapter;
    }

    /**
     * Get items
     *
     * @return array
     */
    public function getItems(): array
    {
        if (!$this->fetchIdsCollection()) {
            return [];
        }

        $result = [];
        $counter = 0;
        $pageSize = $this->getPageSize();
        $skipCounter = ($this->_curPage - 1) * $pageSize;

        // To optimize the processing of large searches, data is retrieved from the server lazily.
        foreach ($this->collection as $item) {
            if ($skipCounter > 0) {
                $skipCounter --;
            } else {
                $entity = $this->_entityFactory->create($this->_itemObjectClass, ['transaction' => $item]);
                if ($entity) {
                    $result[] = $entity;

                    $counter ++;
                    if ($pageSize && $counter >= $pageSize) {
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Fetch collection from Braintree
     *
     * @return ResourceCollection|null
     */
    protected function fetchIdsCollection(): ?ResourceCollection
    {
        if (empty($this->filtersList)) {
            return null;
        }

        // Fetch all transaction IDs in order to filter
        if (empty($this->collection)) {
            $filters = $this->getFilters();
            $this->collection = $this->braintreeAdapter->search($filters);
        }

        return $this->collection;
    }

    /**
     * Set items list.
     *
     * @param DocumentInterface[] $items
     * @return self
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setItems(?array $items = null): TransactionsCollection
    {
        return $this;
    }

    /**
     * Get aggregations
     *
     * @return AggregationInterface|null
     */
    public function getAggregations(): ?AggregationInterface
    {
        return null;
    }

    /**
     * Set aggregations
     *
     * @param AggregationInterface $aggregations
     * @return self
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setAggregations($aggregations): TransactionsCollection
    {
        return $this;
    }

    /**
     * Get search criteria.
     *
     * @return SearchCriteriaInterface|null
     */
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    /**
     * Set search criteria.
     *
     * @param BaseSearchCriteriaInterface $searchCriteria
     * @return self
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setSearchCriteria(BaseSearchCriteriaInterface $searchCriteria): TransactionsCollection
    {
        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        $collection = $this->fetchIdsCollection();
        return null === $collection ? 0 : $collection->maximumCount();
    }

    /**
     * Retrieve collection page size
     *
     * @return int
     */
    public function getPageSize(): int
    {
        $pageSize = parent::getPageSize();
        return $pageSize ?? self::TRANSACTION_MAXIMUM_COUNT;
    }

    /**
     * Set total count.
     *
     * @param int $totalCount
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount): TransactionsCollection
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addFieldToFilter($field, $condition): TransactionsCollection
    {
        if (is_array($field)) {
            return $this;
        }

        if (!is_array($condition)) {
            $condition = ['eq' => $condition];
        }

        $this->addFilterToList($this->filterMapper->getFilter($field, $condition));

        return $this;
    }

    /**
     * Add filter to list
     *
     * @param object $filter
     * @return void
     */
    private function addFilterToList(object $filter): void
    {
        if (null !== $filter) {
            $this->filtersList[] = $filter;
        }
    }

    /**
     * Get filters
     *
     * @return array
     */
    private function getFilters(): array
    {
        return $this->filtersList;
    }
}
