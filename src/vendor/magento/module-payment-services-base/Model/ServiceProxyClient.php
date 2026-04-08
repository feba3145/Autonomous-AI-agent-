<?php
/************************************************************************
 *
 * Copyright 2021 Adobe
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
 * ************************************************************************
 */
declare(strict_types=1);

namespace Magento\PaymentServicesBase\Model;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface as HttpResponseInterface;
use InvalidArgumentException;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\ServicesConnector\Api\ClientResolverInterface;
use Magento\Framework\App\ResponseInterface;
use Psr\Log\LoggerInterface;
use Magento\ServiceProxy\Model\ServiceProxyClientInterface;
use Magento\Framework\App\ObjectManager;

/**
 * Client to work with generic service proxy controller.
 */
class ServiceProxyClient implements ServiceProxyClientInterface
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int[]
     */
    private $successfulResponseCodes = [200, 201, 202, 204];

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var ?ServiceRouteResolverInterface $serviceRouteResolver
     */
    private $serviceRouteResolver;

    /**
     * @param ClientResolverInterface $clientResolver
     * @param Config $config
     * @param LoggerInterface $logger
     * @param HttpResponse $response
     * @param ?ServiceRouteResolverInterface $serviceRouteResolver
     */
    public function __construct(
        ClientResolverInterface $clientResolver,
        Config $config,
        LoggerInterface $logger,
        HttpResponse $response,
        ?ServiceRouteResolverInterface $serviceRouteResolver = null
    ) {
        $this->clientResolver = $clientResolver;
        $this->config = $config;
        $this->logger = $logger;
        $this->response = $response;
        $this->serviceRouteResolver = $serviceRouteResolver ??
            ObjectManager::getInstance()->get(ServiceRouteResolverInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function request(
        string $path,
        string $httpMethod,
        array $headers,
        string $body = ''
    ): ResponseInterface {
        $this->response->clearHeaders();
        try {
            $environment = $this->getEnvironment($headers);
            $client = $this->clientResolver->createHttpClient(self::EXTENSION_NAME, $environment);
            $options = $this->getOptions($headers, $body, $environment);
            $path = $this->serviceRouteResolver->resolve($path);
            $response = $client->request($httpMethod, $path, $options);
            $this->buildResponse($response);
            $isSuccessful = in_array($response->getStatusCode(), $this->successfulResponseCodes);

            if (!$isSuccessful) {
                $this->logger->error(
                    'An error occurred.',
                    [
                        'request' => [
                            'host' => (string)$client->getConfig('base_uri'),
                            'path' => $path,
                            'headers' => $options['headers'],
                            'method' => $httpMethod,
                            'body' => $body,
                        ],
                        'response' => [
                            'body' => $response->getBody()->getContents(),
                            'statusCode' => $response->getStatusCode(),
                        ],
                    ]
                );
            }
        } catch (GuzzleException | InvalidArgumentException $e) {
            $this->response->setHttpResponseCode(500);
            $this->response->setBody('Internal Server error.');
            $this->logger->error($e->getMessage());
        }

        return $this->response;
    }

    /**
     * Get request options.
     *
     * @param array $headers
     * @param string $body
     * @param string $environment
     * @return array
     */
    private function getOptions(array $headers, string $body, string $environment): array
    {
        $options['headers'] = array_merge(
            $headers,
            [
                'x-mp-merchant-id' => $this->config->getMerchantId($environment),
                'x-saas-id' => $this->config->getServicesEnvironmentId(),
                'x-request-user-agent' => $headers['x-request-user-agent'] ??
                        sprintf('PaymentServices/%s', $this->config->getVersion())
            ]
        );
        $options['body'] = $body;
        return $options;
    }

    /**
     * Get environment from headers or from the config.
     *
     * @param array $headers
     * @return string
     */
    private function getEnvironment(array $headers): string
    {
        return $headers['X-Payment-Services-Environment'] ?? $this->config->getEnvironmentType();
    }

    /**
     * Build response to client.
     *
     * @param HttpResponseInterface $response
     */
    private function buildResponse(HttpResponseInterface $response)
    {
        // Content already loaded at this point and no need to send Transfer-Encoding header to client.
        $response = $response->withoutHeader('Transfer-Encoding');
        $this->response->setBody($response->getBody()->getContents());
        $this->response->setHttpResponseCode($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            $this->response->setHeader($name, $value[0], true);
        }
    }
}
