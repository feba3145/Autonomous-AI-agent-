<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Ui\Component\Report\Filters\Type;

use Magento\Ui\Component\Filters\Type\Date;

class DateRange extends Date
{
    /**
     * Braintree date format
     *
     * @var string
     */
    protected static $dateFormat = 'Y-m-d\TH:i:00O';
}
