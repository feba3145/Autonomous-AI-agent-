<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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

namespace Magento\PaymentServicesBase\Model;

use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesPaypal\Model\SdkService;
use Magento\ServicesConnector\Api\ClientResolverInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ServicesConnector\Exception\PrivateKeySignException;
use Magento\Framework\App\CacheInterface;

/**
 * Generic SaaS Service Client
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ServiceClient implements ServiceClientInterface
{
    /**
     * Extension name for Services Connector
     */
    private const EXTENSION_NAME = 'Magento_PaymentServicesBase';

    /**
     * @var ClientResolverInterface
     */
    private $clientResolver;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeHeadersBuilder
     */
    private $scopeHeaderBuilder;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var State
     */
    private $appState;

    private const AUTH_REQUEST_EXCEPTION = 'error during authorize request';

    private const NO_ACTIVE_ACCOUNT_EXCEPTION = 'mage id does not have any active PayPal accounts registered';

    /**
     * @var int[]
     */
    private $successfulResponseCodes = [200, 201, 202, 204];

    /**
     * @var ?ServiceRouteResolverInterface $serviceRouteResolver
     */
    private $serviceRouteResolver;

    /**
     * @param ClientResolverInterface $clientResolver
     * @param Config $config
     * @param Json $serializer
     * @param LoggerInterface $logger
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param CacheInterface $cache
     * @param State $appState
     * @param ?ServiceRouteResolverInterface $serviceRouteResolver
     */
    public function __construct(
        ClientResolverInterface $clientResolver,
        Config $config,
        Json $serializer,
        LoggerInterface $logger,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        CacheInterface $cache,
        State $appState,
        ?ServiceRouteResolverInterface $serviceRouteResolver = null
    ) {
        $this->clientResolver = $clientResolver;
        $this->config = $config;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->cache = $cache;
        $this->appState = $appState;
        $this->serviceRouteResolver = $serviceRouteResolver ??
            ObjectManager::getInstance()->get(ServiceRouteResolverInterface::class);
    }

    /**
     * Make request to service.
     *
     * @param array $headers
     * @param string $path
     * @param string $httpMethod
     * @param string $data
     * @param string $requestContentType
     * @param string $environment
     * @return array
     * @throws NoSuchEntityException
     * @throws PrivateKeySignException
     */
    public function request(
        array $headers,
        string $path,
        string $httpMethod = Http::METHOD_POST,
        string $data = '',
        string $requestContentType = 'json',
        string $environment = ''
    ): array {
        try {
            $environment = $environment ?: $this->config->getEnvironmentType();
            $client = $this->clientResolver->createHttpClient(
                self::EXTENSION_NAME,
                $environment
            );
            $options = [
                'headers' => array_merge(
                    $headers,
                    $this->prepareHeaders($headers, $environment)
                ),
                'body' => $data
            ];
            $path = $this->serviceRouteResolver->resolve($path);

            $response = $client->request($httpMethod, $path, $options);
            $responseBody = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            $isSuccessful = in_array($statusCode, $this->successfulResponseCodes);

            $result = $isSuccessful
                ? $this->handleSuccessfulResponse($response, $responseBody, $requestContentType)
                : $this->handleUnsuccessfulResponse($responseBody, $statusCode);

            if (!$result['is_successful']) {
                $uri = (string) $client->getConfig('base_uri');
                $this->logRequestError($uri, $path, $options, $httpMethod, $data, $responseBody, $statusCode);
            }

            return $result;
        } catch (GuzzleException | InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 500,
                'is_successful' => false,
                'statusText' => 'INTERNAL_SERVER_ERROR',
                'message' => 'An error occurred'
            ];
        }
    }

    /**
     * Handle successful response processing.
     *
     * @param mixed $response
     * @param string $responseBody
     * @param string $requestContentType
     * @return array
     */
    private function handleSuccessfulResponse(
        ResponseInterface $response,
        string $responseBody,
        string $requestContentType)
    : array {
        $result = [
            'is_successful' => true,
            'status' => $response->getStatusCode()
        ];

        if ($requestContentType === 'json') {
            try {
                $result = array_merge($result, $this->serializer->unserialize($responseBody));
            } catch (\InvalidArgumentException $e) {
                return [
                    'is_successful' => false,
                    'status' => 500,
                    'message' => $e->getMessage()
                ];
            }
        } else {
            $result = array_merge($result, [
                'content_body' => $responseBody,
                'content_disposition' => $response->getHeaderLine('Content-Disposition'),
                'content_length' => $response->getHeaderLine('Content-Length'),
                'content_type' => $response->getHeaderLine('Content-Type')
            ]);
        }

        return $result;
    }

    /**
     * Handle unsuccessful response processing.
     *
     * @param string $exceptionMessage
     * @param int $statusCode
     * @return array
     */
    private function handleUnsuccessfulResponse(string $exceptionMessage, int $statusCode): array
    {
        if ($exceptionMessage === self::AUTH_REQUEST_EXCEPTION
            || $exceptionMessage === self::NO_ACTIVE_ACCOUNT_EXCEPTION) {
            $this->cache->clean([SdkService::CACHE_TYPE_TAG]);
        }

        return [
            'is_successful' => false,
            'status' => $statusCode,
            'message' => $exceptionMessage
        ];
    }

    /**
     * Log request error with full context.
     *
     * @param string $uri
     * @param string $path
     * @param array $options
     * @param string $httpMethod
     * @param string $data
     * @param string $responseBody
     * @param int $statusCode
     * @return void
     */
    private function logRequestError(
        string $uri,
        string $path,
        array $options,
        string $httpMethod,
        string $data,
        string $responseBody,
        int $statusCode
    ): void {
        $this->logger->error('An error occurred.', [
            'request' => [
                'host' => $uri,
                'path' => $path,
                'headers' => $options['headers'],
                'method' => $httpMethod,
                'body' => $data,
            ],
            'response' => [
                'body' => $responseBody,
                'statusCode' => $statusCode,
            ],
        ]);
    }

    /**
     * Prepare request headers.
     *
     * @param array $headers
     * @param string $environment
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    private function prepareHeaders(array $headers, string $environment): array
    {
        $preparedHeaders = [
            'x-mp-merchant-id' => $this->config->getMerchantId($environment),
            'x-saas-id' => $this->config->getServicesEnvironmentId(),
            'x-request-user-agent' => $headers['x-request-user-agent'] ??
                sprintf('PaymentServices/%s/%s', $this->appState->getAreaCode(), $this->config->getVersion())
        ];

        if (isset($headers[ScopeHeadersBuilder::SCOPE_TYPE]) && isset($headers[ScopeHeadersBuilder::SCOPE_ID])) {
            // If the provided headers already include scope headers, take those
            $preparedHeaders[ScopeHeadersBuilder::SCOPE_TYPE] = $headers[ScopeHeadersBuilder::SCOPE_TYPE];
            $preparedHeaders[ScopeHeadersBuilder::SCOPE_ID] = $headers[ScopeHeadersBuilder::SCOPE_ID];
        } elseif (isset($headers[ScopeHeadersBuilder::SCOPE_ID])) {
            // If the provided headers contain a scope id but no scope type, assume website scope type
            $preparedHeaders[ScopeHeadersBuilder::SCOPE_TYPE] = ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE;
            $preparedHeaders[ScopeHeadersBuilder::SCOPE_ID] = $headers[ScopeHeadersBuilder::SCOPE_ID];
        } else {
            // If no scope id header was provided, fall back to current store scope
            $scopeHeaders = $this->scopeHeaderBuilder->buildScopeHeadersForCurrentStore();
            $preparedHeaders = array_merge($preparedHeaders, $scopeHeaders);
        }

        return $preparedHeaders;
    }
}
