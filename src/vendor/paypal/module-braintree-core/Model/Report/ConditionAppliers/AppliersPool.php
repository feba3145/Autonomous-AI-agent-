<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report\ConditionAppliers;

use InvalidArgumentException;

class AppliersPool
{
    /**
     * @var ApplierInterface[]
     */
    private array $appliersPool = [];

    /**
     * AppliersPool constructor.
     * @param ApplierInterface[] $appliers
     */
    public function __construct(array $appliers)
    {
        $this->appliersPool = $appliers;
        $this->checkAppliers();
    }

    /**
     * Check appliers types
     *
     * @return void
     */
    private function checkAppliers(): void
    {
        foreach ($this->appliersPool as $applier) {
            if (!($applier instanceof ApplierInterface)) {
                throw new InvalidArgumentException('Report filter applier must implement ApplierInterface');
            }
        }
    }

    /**
     * Get condition applier for filter
     *
     * @param object $filter
     * @return null|ApplierInterface
     */
    public function getApplier(object $filter): ?ApplierInterface
    {
        if (is_object($filter)) {
            $filterClass = get_class($filter);
            if (array_key_exists($filterClass, $this->appliersPool)) {
                return $this->appliersPool[$filterClass];
            }
        }
        return null;
    }
}
