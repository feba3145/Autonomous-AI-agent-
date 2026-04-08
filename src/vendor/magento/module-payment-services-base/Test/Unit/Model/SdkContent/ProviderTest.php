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

namespace Magento\PaymentServicesBase\Test\Unit\Model\SdkContent;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientFactory as HttpClientFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\HttpUserAgent;
use Magento\PaymentServicesBase\Model\SdkContent\CacheEntry;
use Magento\PaymentServicesBase\Model\SdkContent\CacheEntryFactory;
use Magento\PaymentServicesBase\Model\SdkContent\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Laminas\Uri\Uri;

/**
 * Test for Provider
 */
class ProviderTest extends TestCase
{
    private const SDK_URL = 'https://example.com/sdk.js?ext=1.0.0';
    private const SDK_URL_SECONDARY = 'https://example.2.com/sdk.js?ext=1.0.0';
    private const SDK_PATH = '/sdk.js';
    private const SDK_QUERY = 'ext=1.0.0';
    private const SDK_CONTENT = 'console.log("SDK loaded");';
    private const CACHE_KEY_PATTERN = 'payment_services_js_sdk_%s';

    /**
     * @var Provider
     */
    private Provider $provider;

    /**
     * @var Base64Json|MockObject
     */
    private Base64Json|MockObject $base64Json;

    /**
     * @var CacheEntryFactory|MockObject
     */
    private CacheEntryFactory|MockObject $cacheEntryFactory;

    /**
     * @var CacheInterface|MockObject
     */
    private CacheInterface|MockObject $cache;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * @var HttpClientFactory|MockObject
     */
    private HttpClientFactory|MockObject $clientFactory;

    /**
     * @var HttpUserAgent|MockObject
     */
    private HttpUserAgent|MockObject $userAgent;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    /**
     * @var HttpClient|MockObject
     */
    private HttpClient|MockObject $httpClient;

    /**
     * @var CacheEntry|MockObject
     */
    private CacheEntry|MockObject $cacheEntry;

    /**
     * @var CacheEntry|MockObject
     */
    private Uri|MockObject $uri;

    /**
     * Setup the test
     */
    protected function setUp(): void
    {
        $this->base64Json = $this->createMock(Base64Json::class);
        $this->cacheEntryFactory = $this->createMock(CacheEntryFactory::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->clientFactory = $this->createMock(HttpClientFactory::class);
        $this->userAgent = $this->createMock(HttpUserAgent::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpClient = $this->createMock(HttpClient::class);
        $this->cacheEntry = $this->createMock(CacheEntry::class);
        $this->uri = $this->createMock(Uri::class);

        $this->provider = new Provider(
            $this->base64Json,
            $this->cacheEntryFactory,
            $this->cache,
            $this->config,
            $this->clientFactory,
            $this->userAgent,
            $this->logger,
            $this->uri
        );
    }

    /**
     * Test hard cache miss fetches from HTTP, caches it, and returns content
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testHardCacheMissFetchesFromHttpCachesAndReturnsContent(): void
    {
        // Having: No cached entry exists
        $this->havingNoCachedEntry();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should fetch from HTTP, cache it, and return fresh content
        $this->thenShouldMakeHttpCall();
        $this->thenShouldCacheNewContent();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Test cache hit with fresh entry returns cached content without HTTP call
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCacheHitWithFreshEntryReturnsCachedContentWithoutHttpCall(): void
    {
        // Having: Fresh cached entry (< 12 hours old)
        $this->havingFreshCachedEntry();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should return cached content without making HTTP call
        $this->thenShouldNotMakeHttpCall();
        $this->thenShouldReturnCachedContent($result);
    }

    /**
     * Test soft cache miss with successful refresh fetches new content and updates cache
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testSoftCacheMissWithSuccessfulRefreshFetchesNewContentAndUpdatesCache(): void
    {
        // Having: Stale cached entry (> 12 hours old)
        $this->havingStaleCachedEntry();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should fetch fresh content, update cache, and return new content
        $this->thenShouldMakeHttpCall();
        $this->thenShouldUpdateCache();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Test soft cache miss with failed refresh returns stale content
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testSoftCacheMissWithFailedRefreshReturnsStaleContent(): void
    {
        // Having: Stale cached entry and HTTP request fails
        $this->havingStaleCachedEntryWithFailedHttpRequest();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should return stale content as fallback
        $this->thenShouldNotUpdateCache();
        $this->thenShouldReturnStaleContent($result);
    }

    /**
     * Test serialization failure still returns fetched content
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testSerializationFailureStillReturnsFetchedContent(): void
    {
        // Having: No cached entry and serialization fails
        $this->havingNoCachedEntryWithFailedSerialization();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should return fetched content despite serialization failure
        $this->thenShouldMakeHttpCall();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Test cache save failure still returns fetched content
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCacheSaveFailureStillReturnsFetchedContent(): void
    {
        // Having: No cached entry and cache save fails
        $this->havingNoCachedEntryWithFailedCacheSave();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should return fetched content despite cache save failure
        $this->thenShouldMakeHttpCall();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Test primary URL fails but fallback URL succeeds with no cache
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testPrimaryUrlFailsFallbackUrlSucceedsWithNoCache(): void
    {
        // Having: No cache, primary URL fails, secondary URL succeeds
        $this->havingNoCacheAndPrimaryUrlFailsButSecondarySucceeds();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should fetch from fallback URL, cache it, and return content
        $this->thenShouldMakeTwoHttpCalls();
        $this->thenShouldCacheNewContentForSecondaryUrl();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Test cache load throws exception and behaves as cache miss
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCacheLoadThrowsExceptionBehavesAsCacheMiss(): void
    {
        // Having: Cache load throws an exception
        $this->havingCacheLoadThrowsException();

        // When: Getting SDK content
        $result = $this->whenGettingSdkContent();

        // Then: Should fetch from HTTP, cache it, and return content (as if cache didn't exist)
        $this->thenShouldMakeHttpCall();
        $this->thenShouldCacheNewContent();
        $this->thenShouldReturnFetchedContent($result);
    }

    /**
     * Setup: Fresh cached entry exists (< 12 hours old)
     *
     * @return void
     */
    private function havingFreshCachedEntry(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache contains a fresh entry
        $cacheKey = $this->havingCacheKey();
        $unserializedCacheData = [
            'content' => self::SDK_CONTENT,
            'timestamp' => time() - 3600 // 1 hour ago (fresh)
        ];

        $this->havingCacheLoadedAndDeserialized($cacheKey, $unserializedCacheData);

        $this->havingCacheEntryLoadedFromData($unserializedCacheData, $this->cacheEntry);
    }

    /**
     * Setup: No cached entry exists
     *
     * @return void
     */
    private function havingNoCachedEntry(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache is empty (no entry exists)
        $cacheKey = $this->havingCacheKey();
        $this->havingEmptyCacheLoad($cacheKey);

        $this->havingHttpResponseReturning(self::SDK_CONTENT);

        $newCacheEntry = $this->havingNewCacheEntryCreated(self::SDK_CONTENT);
        $this->havingCacheSaved($cacheKey, $newCacheEntry);
    }

    /**
     * Setup: Stale cached entry exists (> 12 hours old)
     *
     * @return void
     */
    private function havingStaleCachedEntry(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache contains a stale entry (older than 12 hours)
        $cacheKey = $this->havingCacheKey();
        $staleTimestamp = time() - 50000; // ~13.9 hours ago (stale)
        $unserializedCacheData = [
            'content' => 'console.log("Old SDK");',
            'timestamp' => $staleTimestamp
        ];

        $this->havingCacheLoadedAndDeserialized($cacheKey, $unserializedCacheData);

        $staleCacheEntry = $this->createMock(CacheEntry::class);

        $this->cacheEntryFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $staleCacheEntry, // First call for loading stale entry
                $this->cacheEntry // Second call for creating new entry
            );

        // First create call - load stale entry
        $staleCacheEntry->expects($this->once())
            ->method('getTimestamp')
            ->willReturn($staleTimestamp);

        $this->havingHttpResponseReturning(self::SDK_CONTENT);

        $this->havingNewCacheEntryPopulated($this->cacheEntry, self::SDK_CONTENT);
        $this->havingCacheSaved($cacheKey, $this->cacheEntry);
    }

    /**
     * Setup: Stale cached entry exists (> 12 hours old) and HTTP request fails
     *
     * @return void
     */
    private function havingStaleCachedEntryWithFailedHttpRequest(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache contains a stale entry (older than 12 hours)
        $cacheKey = $this->havingCacheKey();
        $staleTimestamp = time() - 50000; // ~13.9 hours ago (stale)
        $staleContent = 'console.log("Old SDK");';
        $unserializedCacheData = [
            'content' => $staleContent,
            'timestamp' => $staleTimestamp
        ];

        $this->havingCacheLoadedAndDeserialized($cacheKey, $unserializedCacheData);

        $staleCacheEntry = $this->createMock(CacheEntry::class);
        $this->havingCacheEntryLoadedFromData($unserializedCacheData, $staleCacheEntry);

        // HTTP request fails
        $this->httpClient->expects($this->any())
            ->method('get')
            ->with(self::SDK_URL)
            ->willThrowException(new \GuzzleHttp\Exception\RequestException(
                'Error Communicating with Server',
                $this->createMock(\Psr\Http\Message\RequestInterface::class)
            ));

        // Cache should never be updated due to HTTP failure
        $this->cache->expects($this->never())
            ->method('save');
    }

    /**
     * Setup: No cached entry exists and cache save fails
     *
     * @return void
     */
    private function havingNoCachedEntryWithFailedSerialization(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache is empty (no entry exists)
        $cacheKey = $this->havingCacheKey();
        $this->havingEmptyCacheLoad($cacheKey);

        $this->havingHttpResponseReturning(self::SDK_CONTENT);

        // New cache entry will be created
        $newCacheEntry = $this->createMock(CacheEntry::class);

        $this->cacheEntryFactory->expects($this->once())
            ->method('create')
            ->willReturn($newCacheEntry);

        $newCacheEntry->expects($this->once())
            ->method('setContent')
            ->with(self::SDK_CONTENT)
            ->willReturnSelf();

        $newCacheEntry->expects($this->once())
            ->method('setTimestamp')
            ->with($this->callback(function ($timestamp) {
                return abs(time() - $timestamp) <= 5;
            }))
            ->willReturnSelf();

        $cacheData = [
            'content' => self::SDK_CONTENT,
            'timestamp' => time()
        ];

        $newCacheEntry->expects($this->once())
            ->method('getData')
            ->willReturn($cacheData);

        $newCacheEntry->expects($this->any())
            ->method('getContent')
            ->willReturn(self::SDK_CONTENT);

        // Simulate cache save failure via serialization exception
        $this->base64Json->expects($this->once())
            ->method('serialize')
            ->with($cacheData)
            ->willThrowException(new \InvalidArgumentException('Unable to serialize value'));

        // Cache save should never be called due to serialization failure
        $this->cache->expects($this->never())
            ->method('save');
    }

    /**
     * Setup: No cached entry exists and cache save fails
     *
     * @return void
     */
    private function havingNoCachedEntryWithFailedCacheSave(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache is empty (no entry exists)
        $cacheKey = $this->havingCacheKey();
        $this->havingEmptyCacheLoad($cacheKey);

        $this->havingHttpResponseReturning(self::SDK_CONTENT);

        // New cache entry will be created
        $newCacheEntry = $this->createMock(CacheEntry::class);

        $this->cacheEntryFactory->expects($this->once())
            ->method('create')
            ->willReturn($newCacheEntry);

        $newCacheEntry->expects($this->once())
            ->method('setContent')
            ->with(self::SDK_CONTENT)
            ->willReturnSelf();

        $newCacheEntry->expects($this->once())
            ->method('setTimestamp')
            ->with($this->callback(function ($timestamp) {
                return abs(time() - $timestamp) <= 5;
            }))
            ->willReturnSelf();

        $cacheData = [
            'content' => self::SDK_CONTENT,
            'timestamp' => time()
        ];

        $newCacheEntry->expects($this->once())
            ->method('getData')
            ->willReturn($cacheData);

        $newCacheEntry->expects($this->any())
            ->method('getContent')
            ->willReturn(self::SDK_CONTENT);

        // Simulate cache save failure
        $this->base64Json->expects($this->once())
            ->method('serialize')
            ->with($cacheData)
            ->willReturn('serialized-data');

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                'serialized-data',
                $cacheKey,
                ['payment_services_js_sdk'],
                604800 // 1 week in seconds
            )
            ->willThrowException(new \RuntimeException('Cache save failed'));
    }

    /**
     * Setup: SDK URL is configured
     *
     * @return void
     */
    private function havingSdkUrlConfigured(): void
    {
        $this->config->expects($this->once())
            ->method('getPaymentSDKUrl')
            ->willReturn(self::SDK_URL);

        $this->config->expects($this->once())
            ->method('getPaymentSDKFallbackUrl')
            ->willReturn(self::SDK_URL_SECONDARY);

        $this->uri->expects($this->once())
            ->method('parse')
            ->willReturnSelf();

        $this->uri->expects($this->once())
            ->method('getPath')
            ->willReturn(self::SDK_PATH);

        $this->uri->expects($this->once())
            ->method('getQuery')
            ->willReturn(self::SDK_QUERY);
    }

    /**
     * Setup: User agent is configured
     *
     * @return void
     */
    private function havingUserAgentConfigured(): void
    {
        $this->userAgent->expects($this->once())
            ->method('get')
            ->willReturn('Mozilla/5.0 Test Agent');
    }

    /**
     * Setup: HTTP client is configured and ready
     *
     * @return void
     */
    private function havingHttpClientConfigured(): void
    {
        $this->clientFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->httpClient);
    }

    /**
     * Get cache key for SDK URL
     *
     * @return string
     */
    private function havingCacheKey(): string
    {
        // md5() here is not for cryptographic use
        // phpcs:ignore Magento2.Security.InsecureFunction
        return sprintf(self::CACHE_KEY_PATTERN, md5(self::SDK_PATH . '?' . self::SDK_QUERY));
    }

    /**
     * Setup: Cache is loaded and deserialized
     *
     * @param string $cacheKey
     * @param array $unserializedData
     * @return void
     */
    private function havingCacheLoadedAndDeserialized(string $cacheKey, array $unserializedData): void
    {
        // md5() here is not for cryptographic use
        // phpcs:ignore Magento2.Security.InsecureFunction
        $serializedCacheData = 'base64-encoded-cache-data-' . md5(json_encode($unserializedData));

        $this->cache->expects($this->once())
            ->method('load')
            ->with($cacheKey)
            ->willReturn($serializedCacheData);

        $this->base64Json->expects($this->once())
            ->method('unserialize')
            ->with($serializedCacheData)
            ->willReturn($unserializedData);
    }

    /**
     * Setup: Empty cache load (cache miss)
     *
     * @param string $cacheKey
     * @return void
     */
    private function havingEmptyCacheLoad(string $cacheKey): void
    {
        $this->cache->expects($this->once())
            ->method('load')
            ->with($cacheKey)
            ->willReturn(false);
    }

    /**
     * Setup: Cache entry is loaded from data with getters configured
     *
     * @param array $cacheData
     * @param CacheEntry|MockObject $cacheEntry
     * @return void
     */
    private function havingCacheEntryLoadedFromData(array $cacheData, CacheEntry|MockObject $cacheEntry): void
    {
        $this->cacheEntryFactory->expects($this->once())
            ->method('create')
            ->with(['data' => $cacheData])
            ->willReturn($cacheEntry);

        $cacheEntry->expects($this->once())
            ->method('getTimestamp')
            ->willReturn($cacheData['timestamp']);

        $cacheEntry->expects($this->once())
            ->method('getContent')
            ->willReturn($cacheData['content']);
    }

    /**
     * Setup: HTTP response returning specified content
     *
     * @param string $content
     * @return void
     */
    private function havingHttpResponseReturning(string $content): void
    {
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with(self::SDK_URL)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn($content);
    }

    /**
     * Setup: HTTP response returning specified content
     *
     * @param string $content
     * @return void
     */
    private function havingHttpResponseFailingOnPrimaryButSuccedingOnSecondary(string $content): void
    {
        // Setup HTTP client expectations for two calls
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $this->httpClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($url) use ($response) {
                if ($url === self::SDK_URL) {
                    // Primary URL fails
                    throw new \GuzzleHttp\Exception\RequestException(
                        'Error Communicating with Server',
                        $this->createMock(\Psr\Http\Message\RequestInterface::class)
                    );
                } elseif ($url === self::SDK_URL_SECONDARY) {
                    // Secondary URL succeeds
                    return $response;
                }
                throw new \Exception('Unexpected URL: ' . $url);
            });

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn(self::SDK_CONTENT);
    }

    /**
     * Setup: New cache entry is created and populated with content
     *
     * @param string $content
     * @return CacheEntry|MockObject
     */
    private function havingNewCacheEntryCreated(string $content): CacheEntry|MockObject
    {
        $newCacheEntry = $this->createMock(CacheEntry::class);

        $this->cacheEntryFactory->expects($this->once())
            ->method('create')
            ->willReturn($newCacheEntry);

        $this->havingNewCacheEntryPopulated($newCacheEntry, $content);

        return $newCacheEntry;
    }

    /**
     * Setup: Cache entry is populated with content and timestamp
     *
     * @param CacheEntry|MockObject $cacheEntry
     * @param string $content
     * @return void
     */
    private function havingNewCacheEntryPopulated(CacheEntry|MockObject $cacheEntry, string $content): void
    {
        $cacheEntry->expects($this->once())
            ->method('setContent')
            ->with($content)
            ->willReturnSelf();

        $cacheEntry->expects($this->once())
            ->method('setTimestamp')
            ->with($this->callback(function ($timestamp) {
                // Verify timestamp is recent (within last 5 seconds)
                return abs(time() - $timestamp) <= 5;
            }))
            ->willReturnSelf();

        $cacheData = [
            'content' => $content,
            'timestamp' => time()
        ];

        $cacheEntry->expects($this->once())
            ->method('getData')
            ->willReturn($cacheData);

        $cacheEntry->expects($this->any())
            ->method('getContent')
            ->willReturn($content);
    }

    /**
     * Setup: Cache is saved with serialized entry data
     *
     * @param string $cacheKey
     * @param CacheEntry|MockObject $cacheEntry
     * @return void
     */
    private function havingCacheSaved(string $cacheKey, CacheEntry|MockObject $cacheEntry): void
    {
        $cacheData = [
            'content' => $cacheEntry->getContent(),
            'timestamp' => time()
        ];

        // md5() here is not for cryptographic use
        // phpcs:ignore Magento2.Security.InsecureFunction
        $serializedData = 'base64-encoded-cache-data-' . md5($cacheEntry->getContent());

        $this->base64Json->expects($this->once())
            ->method('serialize')
            ->with($cacheData)
            ->willReturn($serializedData);

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                $serializedData,
                $cacheKey,
                ['payment_services_js_sdk'],
                604800 // 1 week in seconds
            );
    }

    /**
     * Setup: No cache, primary URL fails, secondary URL succeeds
     *
     * @return void
     */
    private function havingNoCacheAndPrimaryUrlFailsButSecondarySucceeds(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Cache is empty for primary URL
        $primaryCacheKey = $this->havingCacheKey();
        $this->cache->expects($this->any())
            ->method('load')
            ->willReturnCallback(function ($key) use ($primaryCacheKey) {
                // Not cached
                return false;
            });

        // Setup HTTP client expectations for two calls
        $this->havingHttpResponseFailingOnPrimaryButSuccedingOnSecondary(self::SDK_CONTENT);

        // New cache entry will be created for secondary URL
        // md5() here is not for cryptographic use
        // phpcs:ignore Magento2.Security.InsecureFunction
        $secondaryCacheKey = sprintf(self::CACHE_KEY_PATTERN, md5(self::SDK_PATH . '?' . self::SDK_QUERY));
        $newCacheEntry = $this->createMock(CacheEntry::class);

        $this->cacheEntryFactory->expects($this->once())
            ->method('create')
            ->willReturn($newCacheEntry);

        $this->havingNewCacheEntryPopulated($newCacheEntry, self::SDK_CONTENT);

        // Cache should be saved with secondary URL's cache key
        $this->havingCacheSaved($secondaryCacheKey, $newCacheEntry);
    }

    /**
     * Setup: Cache load throws an exception
     *
     * @return void
     */
    private function havingCacheLoadThrowsException(): void
    {
        $this->havingSdkUrlConfigured();
        $this->havingUserAgentConfigured();
        $this->havingHttpClientConfigured();

        // Simulate exception when loading from cache
        $cacheKey = $this->havingCacheKey();
        $this->cache->expects($this->once())
            ->method('load')
            ->with($cacheKey)
            ->willThrowException(new \RuntimeException('Cache backend error'));

        $this->havingHttpResponseReturning(self::SDK_CONTENT);

        $newCacheEntry = $this->havingNewCacheEntryCreated(self::SDK_CONTENT);
        $this->havingCacheSaved($cacheKey, $newCacheEntry);
    }

    /**
     * Action: Get SDK content
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function whenGettingSdkContent(): string
    {
        return $this->provider->get();
    }

    /**
     * Assert: Should return cached content
     *
     * @param string $result
     * @return void
     */
    private function thenShouldReturnCachedContent(string $result): void
    {
        $this->assertEquals(self::SDK_CONTENT, $result);
    }

    /**
     * Assert: Should return fetched content
     *
     * @param string $result
     * @return void
     */
    private function thenShouldReturnFetchedContent(string $result): void
    {
        $this->assertEquals(self::SDK_CONTENT, $result);
    }

    /**
     * Assert: Should not make HTTP call
     *
     * @return void
     */
    private function thenShouldNotMakeHttpCall(): void
    {
        // HTTP client should not be used to make any requests
        $this->httpClient->expects($this->never())
            ->method('get');
    }

    /**
     * Assert: Should make HTTP call
     *
     * @return void
     */
    private function thenShouldMakeHttpCall(): void
    {
        // HTTP client get method is already verified in havingNoCachedEntry setup
        // This method serves as explicit documentation of the expectation
        $this->addToAssertionCount(1);
    }

    /**
     * Assert: Should cache new content
     *
     * @return void
     */
    private function thenShouldCacheNewContent(): void
    {
        // Cache save is already verified in havingNoCachedEntry setup
        // This method serves as explicit documentation of the expectation
        $this->addToAssertionCount(1);
    }

    /**
     * Assert: Should update cache with fresh content
     *
     * @return void
     */
    private function thenShouldUpdateCache(): void
    {
        // Cache save is already verified in havingStaleCachedEntry setup
        // This method serves as explicit documentation of the expectation
        $this->addToAssertionCount(1);
    }

    /**
     * Assert: Should not update cache
     *
     * @return void
     */
    private function thenShouldNotUpdateCache(): void
    {
        // Cache save should not be called
        $this->cache->expects($this->never())
            ->method('save');
    }

    /**
     * Assert: Should return stale content
     *
     * @param string $result
     * @return void
     */
    private function thenShouldReturnStaleContent(string $result): void
    {
        $this->assertEquals('console.log("Old SDK");', $result);
    }

    /**
     * Assert: Should make two HTTP calls (primary and fallback)
     *
     * @return void
     */
    private function thenShouldMakeTwoHttpCalls(): void
    {
        // HTTP client get method is already verified in havingNoCacheAndPrimaryUrlFailsButSecondarySucceeds setup
        // This method serves as explicit documentation of the expectation
        $this->addToAssertionCount(1);
    }

    /**
     * Assert: Should cache new content for secondary URL
     *
     * @return void
     */
    private function thenShouldCacheNewContentForSecondaryUrl(): void
    {
        // Cache save is already verified in havingNoCacheAndPrimaryUrlFailsButSecondarySucceeds setup
        // This method serves as explicit documentation of the expectation
        $this->addToAssertionCount(1);
    }
}
