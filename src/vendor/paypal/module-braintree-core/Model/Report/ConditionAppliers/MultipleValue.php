<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report\ConditionAppliers;

use Braintree\MultipleValueNode;

/**
 * MultipleValue applier
 */
class MultipleValue implements ApplierInterface
{
    /**
     * @inheritDoc
     */
    public function apply($field, $condition, $value): bool
    {
        $result = false;

        switch ($condition) {
            case ApplierInterface::IN:
                $field->in($value);
                $result = true;
                break;
            case ApplierInterface::EQ:
                $field->is($value);
                $result = true;
                break;
            case ApplierInterface::LIKE:
                $value = trim($value, "% \r\n\t");
                $field->is($value);
                $result = true;
                break;
        }

        return $result;
    }
}
