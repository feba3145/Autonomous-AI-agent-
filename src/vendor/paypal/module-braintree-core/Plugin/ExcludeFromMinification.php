<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use \Magento\Framework\View\Asset\Minification;

class ExcludeFromMinification
{
    /**
     * Exclude Google Pay url from minification
     *
     * @param Minification $subject
     * @param array $result
     * @param string $contentType
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetExcludes(Minification $subject, array $result, string $contentType): array
    {
        $result[] = '/pay.google.com/';
        return $result;
    }
}
