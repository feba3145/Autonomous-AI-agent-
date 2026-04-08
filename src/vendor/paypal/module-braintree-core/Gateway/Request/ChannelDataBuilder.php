<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\App\ProductMetadataInterface;

class ChannelDataBuilder implements BuilderInterface
{
    /**
     * @var string $channel
     */
    private static $channel = 'channel';

    /**
     * @var string $channelValue
     */
    private static $channelValue = 'Magento2GeneBT';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        return [
            self::$channel => self::$channelValue
        ];
    }
}
