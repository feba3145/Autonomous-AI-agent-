<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Validator;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Validation\ValidationResult;

interface AddressValidatorInterface
{
    /**
     * Validate Address data.
     *
     * @param AddressInterface $address
     * @return ValidationResult
     */
    public function validate(AddressInterface $address): ValidationResult;
}
