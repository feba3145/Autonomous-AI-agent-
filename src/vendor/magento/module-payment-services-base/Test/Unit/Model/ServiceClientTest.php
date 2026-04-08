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

namespace Magento\PaymentServicesBase\Test\Unit\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Laminas\Http\Request;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesBase\Model\ServiceClient;
use Magento\PaymentServicesBase\Model\ServiceRouteResolverInterface;
use Magento\PaymentServicesPaypal\Model\SdkService;
use Magento\ServicesConnector\Api\ClientResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Test for ServiceClient
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ServiceClientTest extends TestCase
{
    private const CONTENT_TYPE_JSON = 'json';
    private const CONTENT_TYPE_FILE = 'file';

    /**
     * @var ServiceClient
     */
    private $serviceClient;

    /**
     * @var ClientResolverInterface|MockObject
     */
    private $clientResolver;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var Json|MockObject
     */
    private $serializer;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ScopeHeadersBuilder|MockObject
     */
    private $scopeHeaderBuilder;

    /**
     * @var CacheInterface|MockObject
     */
    private $cache;

    /**
     * @var State|MockObject
     */
    private $appState;

    /**
     * @var ServiceRouteResolverInterface|MockObject
     */
    private $serviceRouteResolver;

    /**
     * @var Client|MockObject
     */
    private $httpClient;

    /**
     * @var ResponseInterface|MockObject
     */
    private $response;

    /**
     * @var StreamInterface|MockObject
     */
    private $stream;

    /**
     * Setup the test
     */
    protected function setUp(): void
    {
        $this->clientResolver = $this->createMock(ClientResolverInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->serializer = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->scopeHeaderBuilder = $this->createMock(ScopeHeadersBuilder::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->appState = $this->createMock(State::class);
        $this->serviceRouteResolver = $this->createMock(ServiceRouteResolverInterface::class);
        $this->httpClient = $this->createMock(Client::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->serviceClient = new ServiceClient(
            $this->clientResolver,
            $this->config,
            $this->serializer,
            $this->logger,
            $this->scopeHeaderBuilder,
            $this->cache,
            $this->appState,
            $this->serviceRouteResolver
        );
    }

    /**
     * Test successful JSON request
     *
     * @return void
     */
    public function testRequestSuccessfulJsonResponse(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = ['x-custom-header' => 'value'];
        $requestBody = '{"test": "data"}';
        $responseBody = '{"result": "success", "data": "test"}';
        $statusCode = 200;

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->with($responseBody)
            ->willReturn(['result' => 'success', 'data' => 'test']);

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->serviceClient->request(
            $headers,
            $path,
            Request::METHOD_POST,
            $requestBody,
            self::CONTENT_TYPE_JSON,
            $environment
        );

        $this->assertTrue($result['is_successful']);
        $this->assertEquals($statusCode, $result['status']);
        $this->assertEquals('success', $result['result']);
        $this->assertEquals('test', $result['data']);
    }

    /**
     * Test successful non-JSON request
     *
     * @return void
     */
    public function testRequestSuccessfulNonJsonResponse(): void
    {
        $path = '/api/download';
        $resolvedPath = '/resolved/api/download';
        $environment = 'production';
        $headers = [];
        $requestBody = '';
        $responseBody = 'file content';
        $statusCode = 200;

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->response->expects($this->exactly(3))
            ->method('getHeaderLine')
            ->willReturnMap([
                ['Content-Disposition', 'attachment; filename="test.pdf"'],
                ['Content-Length', '1024'],
                ['Content-Type', 'application/pdf']
            ]);

        $this->serializer->expects($this->never())
            ->method('unserialize');

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_GET, $requestBody, self::CONTENT_TYPE_FILE, $environment);

        $this->assertTrue($result['is_successful']);
        $this->assertEquals($statusCode, $result['status']);
        $this->assertEquals($responseBody, $result['content_body']);
        $this->assertEquals('attachment; filename="test.pdf"', $result['content_disposition']);
        $this->assertEquals('1024', $result['content_length']);
        $this->assertEquals('application/pdf', $result['content_type']);
    }

    /**
     * Test unsuccessful request with auth error (should clean cache)
     *
     * @return void
     */
    public function testRequestUnsuccessfulWithAuthError(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $responseBody = 'error during authorize request';
        $statusCode = 401;

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->cache->expects($this->once())
            ->method('clean')
            ->with([SdkService::CACHE_TYPE_TAG]);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'An error occurred.',
                $this->callback(function ($context) use ($responseBody, $statusCode) {
                    return isset($context['request']) &&
                        isset($context['response']) &&
                        $context['response']['body'] === $responseBody &&
                        $context['response']['statusCode'] === $statusCode;
                })
            );

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody, self::CONTENT_TYPE_JSON, $environment);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals($statusCode, $result['status']);
        $this->assertEquals($responseBody, $result['message']);
    }

    /**
     * Test unsuccessful request with no active account error (should clean cache)
     *
     * @return void
     */
    public function testRequestUnsuccessfulWithNoActiveAccountError(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $responseBody = 'mage id does not have any active PayPal accounts registered';
        $statusCode = 404;

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->cache->expects($this->once())
            ->method('clean')
            ->with([SdkService::CACHE_TYPE_TAG]);

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody, self::CONTENT_TYPE_JSON, $environment);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals($statusCode, $result['status']);
        $this->assertEquals($responseBody, $result['message']);
    }

    /**
     * Test unsuccessful request with regular error (should not clean cache)
     *
     * @return void
     */
    public function testRequestUnsuccessfulWithRegularError(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $responseBody = 'Some other error message';
        $statusCode = 500;

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->cache->expects($this->never())
            ->method('clean');

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody, self::CONTENT_TYPE_JSON, $environment);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals($statusCode, $result['status']);
        $this->assertEquals($responseBody, $result['message']);
    }

    /**
     * Test request with GuzzleException
     *
     * @return void
     */
    public function testRequestWithGuzzleException(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $exceptionMessage = 'Connection timeout';

        $this->config->expects($this->once())
            ->method('getEnvironmentType')
            ->willReturn($environment);

        $this->setupHeaderPreparation($headers, $environment);

        $this->serviceRouteResolver->expects($this->once())
            ->method('resolve')
            ->with($path)
            ->willReturn($resolvedPath);

        $this->clientResolver->expects($this->once())
            ->method('createHttpClient')
            ->willReturn($this->httpClient);

        $mockRequest = $this->createMock(RequestInterface::class);
        $exception = new RequestException($exceptionMessage, $mockRequest);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($exceptionMessage);

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals(500, $result['status']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $result['statusText']);
        $this->assertEquals('An error occurred', $result['message']);
    }

    /**
     * Test request with InvalidArgumentException
     *
     * @return void
     */
    public function testRequestWithInvalidArgumentException(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $exceptionMessage = 'Invalid argument';

        $this->setupHeaderPreparation($headers, $environment);

        $this->serviceRouteResolver->expects($this->once())
            ->method('resolve')
            ->with($path)
            ->willReturn($resolvedPath);

        $this->clientResolver->expects($this->once())
            ->method('createHttpClient')
            ->willReturn($this->httpClient);

        $exception = new InvalidArgumentException($exceptionMessage);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($exceptionMessage);

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody, self::CONTENT_TYPE_JSON, $environment);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals(500, $result['status']);
    }

    /**
     * Test successful request with JSON parsing error
     *
     * @return void
     */
    public function testRequestWithJsonParsingError(): void
    {
        $path = '/api/test';
        $resolvedPath = '/resolved/api/test';
        $environment = 'sandbox';
        $headers = [];
        $requestBody = '';
        $responseBody = 'invalid json';
        $statusCode = 200;
        $parsingErrorMessage = 'Unable to unserialize value';

        $this->setupMocksForRequest(
            $environment,
            $path,
            $resolvedPath,
            $headers,
            $requestBody,
            $responseBody,
            $statusCode
        );

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->with($responseBody)
            ->willThrowException(new \InvalidArgumentException($parsingErrorMessage));

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->serviceClient->request($headers, $path, Request::METHOD_POST, $requestBody, self::CONTENT_TYPE_JSON, $environment);

        $this->assertFalse($result['is_successful']);
        $this->assertEquals(500, $result['status']);
        $this->assertEquals($parsingErrorMessage, $result['message']);
    }

    /**
     * Setup mocks for a complete request flow
     *
     * @param string $environment
     * @param string $path
     * @param string $resolvedPath
     * @param array $headers
     * @param string $requestBody
     * @param string $responseBody
     * @param int $statusCode
     * @return void
     */
    private function setupMocksForRequest(
        string $environment,
        string $path,
        string $resolvedPath,
        array $headers,
        string $requestBody,
        string $responseBody,
        int $statusCode
    ): void {
        $this->setupHeaderPreparation($headers, $environment);
        $this->setupRequestExecution($resolvedPath, $responseBody, $statusCode);
    }

    /**
     * Setup header preparation mocks
     *
     * @param array $headers
     * @param string $environment
     * @return void
     */
    private function setupHeaderPreparation(array $headers, string $environment): void
    {
        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with($environment)
            ->willReturn('merchant-123');

        $this->config->expects($this->once())
            ->method('getServicesEnvironmentId')
            ->willReturn('env-456');

        if (!isset($headers['x-request-user-agent'])) {
            $this->appState->expects($this->once())
                ->method('getAreaCode')
                ->willReturn('frontend');

            $this->config->expects($this->once())
                ->method('getVersion')
                ->willReturn('1.0.0');
        }

        if (!isset($headers[ScopeHeadersBuilder::SCOPE_TYPE]) && !isset($headers[ScopeHeadersBuilder::SCOPE_ID])) {
            $this->scopeHeaderBuilder->expects($this->once())
                ->method('buildScopeHeadersForCurrentStore')
                ->willReturn([
                    ScopeHeadersBuilder::SCOPE_TYPE => 'storeview',
                    ScopeHeadersBuilder::SCOPE_ID => '1'
                ]);
        }

        $this->clientResolver->expects($this->once())
            ->method('createHttpClient')
            ->with('Magento_PaymentServicesBase', $environment)
            ->willReturn($this->httpClient);
    }

    /**
     * Setup request execution mocks
     *
     * @param string $resolvedPath
     * @param string $responseBody
     * @param int $statusCode
     * @return void
     */
    private function setupRequestExecution(
        string $resolvedPath,
        string $responseBody,
        int $statusCode
    ): void {
        $this->serviceRouteResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($resolvedPath);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->response);

        $this->httpClient->expects($this->any())
            ->method('getConfig')
            ->with('base_uri')
            ->willReturn('https://api.example.com');

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->expects($this->once())
            ->method('getContents')
            ->willReturn($responseBody);

        $this->response->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn($statusCode);
    }
}

