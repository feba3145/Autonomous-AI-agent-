<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use PayPal\Braintree\Gateway\Config\Config;

class DescriptorDataBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    private static $descriptorKey = 'descriptor';

    /**
     * @var Config
     */
    private $config;

    /**
     * DescriptorDataBuilder constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build(array $buildSubject): array
    {
        $values = $this->config->getDynamicDescriptors();
        return !empty($values) ? [self::$descriptorKey => $values] : [];
    }
}
