<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report;

use PayPal\Braintree\Model\Adapter\BraintreeSearchAdapter;
use PayPal\Braintree\Model\Report\ConditionAppliers\AppliersPool;

class FilterMapper
{
    /**
     * @var array
     */
    private array $searchFieldsToFiltersMap = [];

    /**
     * @var AppliersPool
     */
    private AppliersPool $appliersPool;

    /**
     * @var BraintreeSearchAdapter
     */
    private BraintreeSearchAdapter $braintreeSearchAdapter;

    /**
     * @param AppliersPool $appliersPool
     * @param BraintreeSearchAdapter $braintreeSearchAdapter
     */
    public function __construct(
        AppliersPool $appliersPool,
        BraintreeSearchAdapter $braintreeSearchAdapter
    ) {
        $this->appliersPool = $appliersPool;
        $this->braintreeSearchAdapter = $braintreeSearchAdapter;
        $this->initFieldsToFiltersMap();
    }

    /**
     * Init fields map with Braintree filters
     *
     * @return void
     */
    private function initFieldsToFiltersMap(): void
    {
        $this->searchFieldsToFiltersMap = [
            'id' => $this->braintreeSearchAdapter->id(),
            'merchantAccountId' => $this->braintreeSearchAdapter->merchantAccountId(),
            'orderId' => $this->braintreeSearchAdapter->orderId(),
            'paypalDetails_paymentId' => $this->braintreeSearchAdapter->paypalPaymentId(),
            'createdUsing' => $this->braintreeSearchAdapter->createdUsing(),
            'type' => $this->braintreeSearchAdapter->type(),
            'createdAt' => $this->braintreeSearchAdapter->createdAt(),
            'amount' => $this->braintreeSearchAdapter->amount(),
            'status' => $this->braintreeSearchAdapter->status(),
            'settlementBatchId' => $this->braintreeSearchAdapter->settlementBatchId(),
            'paymentInstrumentType' => $this->braintreeSearchAdapter->paymentInstrumentType()
        ];
    }

    /**
     * Get filter with applied conditions
     *
     * @param string $field
     * @param array $conditionMap
     * @return null|object
     */
    public function getFilter(string $field, array $conditionMap): ?object
    {
        if (!isset($this->searchFieldsToFiltersMap[$field])) {
            return null;
        }

        $fieldFilter = $this->searchFieldsToFiltersMap[$field];
        if ($this->applyConditions($fieldFilter, $conditionMap)) {
            return $fieldFilter;
        }

        return null;
    }

    /**
     * Apply conditions to filter
     *
     * @param object $fieldFilter
     * @param array $conditionMap
     * @return bool
     */
    private function applyConditions(object $fieldFilter, array $conditionMap): bool
    {
        $applier = $this->appliersPool->getApplier($fieldFilter);

        $conditionsAppliedCounter = 0;
        foreach ($conditionMap as $conditionKey => $value) {
            if ($applier->apply($fieldFilter, $conditionKey, $value)) {
                $conditionsAppliedCounter ++;
            }
        }

        return $conditionsAppliedCounter > 0;
    }
}
