<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesBase\Model\SdkContent;

use Magento\Framework\DataObject;

/**
 * Payment Services JS SDK (file) content cache entry.
 */
class CacheEntry extends DataObject
{
    public const CONTENT = 'content';
    public const TIMESTAMP = 'timestamp';

    /**
     * Returns the cached Payment Services JS SDK (file) content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * Sets the cached Payment Services JS SDK (file) content
     *
     * @param string $content
     * @return CacheEntry
     */
    public function setContent(string $content): CacheEntry
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * Returns the epoch timestamp in seconds when this cache entry was created.
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->getData(self::TIMESTAMP);
    }

    /**
     * Sets the epoch timestamp in seconds when this cache entry was created.
     *
     * @param int $timestamp
     * @return CacheEntry
     */
    public function setTimestamp(int $timestamp): CacheEntry
    {
        return $this->setData(self::TIMESTAMP, $timestamp);
    }
}
