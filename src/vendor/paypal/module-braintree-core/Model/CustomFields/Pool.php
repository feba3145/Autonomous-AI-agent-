<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\CustomFields;

use InvalidArgumentException;

class Pool
{
    /**
     * @var array
     */
    protected array $fieldsPool;

    /**
     * CustomFieldsDataBuilder constructor.
     * @param array $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fieldsPool = $fields;
        $this->checkFields();
    }

    /**
     * Get fields
     *
     * @param array $buildSubject
     * @return array
     */
    public function getFields(array $buildSubject): array
    {
        $result = [];

        /** @var CustomFieldInterface $field */
        foreach ($this->fieldsPool as $field) {
            $result[ $field->getApiName() ] = $field->getValue($buildSubject);
        }

        return $result;
    }

    /**
     * Check fields
     *
     * @return bool
     */
    protected function checkFields(): bool
    {
        foreach ($this->fieldsPool as $field) {
            if (!($field instanceof CustomFieldInterface)) {
                throw new InvalidArgumentException('Custom field must implement CustomFieldInterface');
            }
        }
        return true;
    }
}
