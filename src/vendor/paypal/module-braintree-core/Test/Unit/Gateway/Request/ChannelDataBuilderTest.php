<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use PayPal\Braintree\Gateway\Request\ChannelDataBuilder;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ChannelDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ChannelDataBuilder
     */
    private ChannelDataBuilder $builder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->builder = new ChannelDataBuilder();
    }

    public function testBuild()
    {
        $expected = [
            'channel' => 'Magento2GeneBT'
        ];
        self::assertEquals($expected, $this->builder->build([]));
    }
}
