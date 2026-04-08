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

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientFactory as HttpClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Laminas\Uri\Uri;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\HttpUserAgent;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Payment Services JS SDK (file) content provider
 */
class Provider
{
    private const CACHE_KEY = 'payment_services_js_sdk_%s';
    private const CACHE_TAG = 'payment_services_js_sdk';
    private const SOFT_CACHE_TTL = 43200; // 12 hours
    private const HARD_CACHE_TTL = 604800; // 1 week

    /**
     * @param Base64Json $base64Json
     * @param CacheEntryFactory $cacheEntryFactory
     * @param CacheInterface $cache
     * @param Config $config
     * @param HttpClientFactory $clientFactory
     * @param HttpUserAgent $userAgent
     * @param LoggerInterface $logger
     * @param Uri $uri
     */
    public function __construct(
        private readonly Base64Json $base64Json,
        private readonly CacheEntryFactory $cacheEntryFactory,
        private readonly CacheInterface $cache,
        private readonly Config $config,
        private readonly HttpClientFactory $clientFactory,
        private readonly HttpUserAgent $userAgent,
        private readonly LoggerInterface $logger,
        private readonly Uri $uri,
    ) {
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function get(): string
    {
        $client = $this->createHttpClient();

        $urls = [
            $this->config->getPaymentSDKUrl(),
            $this->config->getPaymentSDKFallbackUrl(),
        ];

        return $this->doGet($client, $urls);
    }

    /**
     * @return HttpClient
     * @throws LocalizedException
     */
    private function createHttpClient(): HttpClient
    {
        return $this->clientFactory->create([
            'config' => [
                RequestOptions::HTTP_ERRORS => true,
                'headers' => ['User-Agent' => $this->userAgent->get()],
            ],
        ]);
    }

    /**
     * @param HttpClient $client
     * @param string[] $urls
     * @return string
     * @throws LocalizedException
     */
    private function doGet(HttpClient $client, array $urls): string
    {
        $cacheKey = $this->getCacheKey($urls[0]);
        $cacheEntry = $this->getCacheEntry($cacheKey);

        // Hard cache miss (no cached entry available)
        if (!$cacheEntry) {
            $this->logger->debug('Hard Payments JS SDK cache miss. Key=' . $cacheKey . '.');
            return $this->refreshCacheAndReturn($client, $urls, $cacheKey);
        }

        // Cache hit (cached entry is fresh)
        if ($this->isCacheEntryFresh($cacheEntry)) {
            $this->logger->debug('Payments JS SDK cache hit. Key=' . $cacheKey . '.');
            return $cacheEntry->getContent();
        }

        // Soft cache miss (cached entry is stale)
        try {
            $this->logger->debug('Soft Payments JS SDK cache miss. Key=' . $cacheKey . '.');
            return $this->refreshCacheAndReturn($client, $urls, $cacheKey);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Returning stale Payments JS SDK after failed refresh attempt. '
                . 'Reason: ' . $e->getMessage() . '.'
            );
            return $cacheEntry->getContent();
        }
    }

    /**
     * @param string $cacheKey
     * @return CacheEntry|null
     */
    private function getCacheEntry(string $cacheKey) : ?CacheEntry
    {
        try {
            $cachedSdk = $this->cache->load($cacheKey);
            if (!$cachedSdk) {
                return null;
            }
            $data = $this->base64Json->unserialize($cachedSdk);
            return $this->cacheEntryFactory->create(['data' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load Payments JS SDK from cache. Reason: ' . $e->getMessage() . '.');
            return null;
        }
    }

    /**
     * @param CacheEntry $cacheEntry
     * @return bool
     */
    private function isCacheEntryFresh(CacheEntry $cacheEntry): bool
    {
        return time() - $cacheEntry->getTimestamp() < self::SOFT_CACHE_TTL;
    }

    /**
     * @param HttpClient $client
     * @param string[] $urls
     * @param string $cacheKey
     * @return string
     * @throws LocalizedException
     */
    private function refreshCacheAndReturn(HttpClient $client, array $urls, string $cacheKey): string
    {
        $response = $this->pullSdk($client, $urls);

        /* @var $cacheEntry CacheEntry */
        $cacheEntry = $this->cacheEntryFactory->create()
            ->setContent($response->getBody()->getContents())
            ->setTimestamp(time());

        try {
            $data = $this->base64Json->serialize($cacheEntry->getData());
            $this->cache->save($data, $cacheKey, [self::CACHE_TAG], self::HARD_CACHE_TTL);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cache Payments JS SDK. Reason: ' . $e->getMessage() . '.');
        }

        return $cacheEntry->getContent();
    }

    /**
     * @param HttpClient $client
     * @param array $urls
     * @return ResponseInterface
     * @throws LocalizedException
     */
    private function pullSdk(HttpCLient $client, array $urls): ResponseInterface
    {
        foreach ($urls as $url) {
            try {
                return $client->get($url);
            } catch (GuzzleException $e) {
                $this->logger->warning(
                    'Failed to pull Payments JS SDK from ' . $url . '. Reason: ' . $e->getMessage() . '.'
                );
            }
        }
        throw new LocalizedException(
            __('Unable to retrieve Payments JS SDK from any of the configured URLs.')
        );
    }

    /**
     * @param string $url
     * @return string
     */
    private function getCacheKey(string $url): string
    {
        $parsedUrl = $this->uri->parse($url);

        $path = $parsedUrl->getPath();
        $query = $parsedUrl->getQuery();

        // md5() here is not for cryptographic use
        // phpcs:ignore Magento2.Security.InsecureFunction
        return sprintf(self::CACHE_KEY, md5($path . '?' . $query));
    }
}
