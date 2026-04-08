<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report\ConditionAppliers;

/**
 * Braintree filter condition applier interface
 */
interface ApplierInterface
{
    public const EQ = 'eq';
    public const QTEQ = 'gteq';
    public const LTEQ = 'lteq';
    public const IN = 'in';
    public const LIKE = 'like';

    /**
     * Apply filter condition
     *
     * @param object $field
     * @param string $condition
     * @param mixed $value
     * @return bool
     */
    public function apply(object $field, string $condition, mixed $value): bool;
}
