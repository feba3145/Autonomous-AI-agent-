<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Report\ConditionAppliers;

use Braintree\TextNode;

/**
 * Text applier
 */
class Text implements ApplierInterface
{
    /**
     * @inheritDoc
     */
    public function apply($field, $condition, $value): bool
    {
        $result = false;

        $value = trim($value, "% \r\n\t");
        switch ($condition) {
            case ApplierInterface::EQ:
                $field->is($value);
                $result = true;
                break;
            case ApplierInterface::LIKE:
                $field->contains($value);
                $result = true;
                break;
        }

        return $result;
    }
}
