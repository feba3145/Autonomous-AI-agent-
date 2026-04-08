<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace Magento\SaaSCommon\Test\Unit\Model\Http;

use Magento\DataExporter\Status\ExportStatusCodeProvider;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\SaaSCommon\Model\Http\ResponseParser;
use Magento\SaaSCommon\Model\Logging\SaaSExportLoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Unit test for ResponseParser
 *
 * Tests that items_limit_exceeded errors are marked as RETRYABLE instead of FAILED_ITEM_ERROR
 */
class ResponseParserTest extends TestCase
{
    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

    /**
     * @var SaaSExportLoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ResponseParser
     */
    private $responseParser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(SaaSExportLoggerInterface::class);

        $this->responseParser = new ResponseParser(
            $this->serializer,
            $this->logger,
            'invalidFeedItems'
        );
    }

    /**
     * Test that items_limit_exceeded error is marked as RETRYABLE
     */
    public function testItemsLimitExceededIsRetryable(): void
    {
        $responseData = [
            'invalidFeedItems' => [
                [
                    'itemIndex' => 0,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'itemIndex' => 1,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ]
            ]
        ];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertCount(2, $result);
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[0]['status']);
        $this->assertEquals('items_limit_exceeded', $result[0]['field']);
        $this->assertEquals('Item quantity exceeds the maximum allowed limit', $result[0]['message']);
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[1]['status']);
    }

    /**
     * Test that regular validation errors are marked as FAILED_ITEM_ERROR
     */
    public function testValidationErrorIsFailedItemError(): void
    {
        $responseData = [
            'invalidFeedItems' => [
                [
                    'itemIndex' => 0,
                    'code' => 'invalid_sku',
                    'message' => 'SKU is invalid'
                ],
                [
                    'itemIndex' => 1,
                    'code' => 'missing_required_field',
                    'message' => 'Name field is required'
                ]
            ]
        ];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertCount(2, $result);
        $this->assertEquals(ExportStatusCodeProvider::FAILED_ITEM_ERROR, $result[0]['status']);
        $this->assertEquals('invalid_sku', $result[0]['field']);
        $this->assertEquals('SKU is invalid', $result[0]['message']);
        $this->assertEquals(ExportStatusCodeProvider::FAILED_ITEM_ERROR, $result[1]['status']);
    }

    /**
     * Test mixed errors - items_limit_exceeded and validation errors
     */
    public function testMixedErrorTypes(): void
    {
        $responseData = [
            'invalidFeedItems' => [
                [
                    'itemIndex' => 0,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'itemIndex' => 1,
                    'code' => 'invalid_sku',
                    'message' => 'SKU is invalid'
                ]
            ]
        ];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertCount(2, $result);
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[0]['status']);
        $this->assertEquals('items_limit_exceeded', $result[0]['field']);
        $this->assertEquals(ExportStatusCodeProvider::FAILED_ITEM_ERROR, $result[1]['status']);
        $this->assertEquals('invalid_sku', $result[1]['field']);
    }

    /**
     * Test items_limit_exceeded with field format "/index/field"
     */
    public function testItemsLimitExceededWithFieldFormat(): void
    {
        $responseData = [
            'invalidFeedItems' => [
                [
                    'field' => '/0/items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'field' => '/1/items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ]
            ]
        ];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertCount(2, $result);
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[0]['status']);
        $this->assertEquals('items_limit_exceeded', $result[0]['field']);
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[1]['status']);
        $this->assertEquals('items_limit_exceeded', $result[1]['field']);
    }

    /**
     * Test empty response
     */
    public function testEmptyResponse(): void
    {
        $responseData = [];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertEmpty($result);
    }

    /**
     * Test response with no invalidFeedItems
     */
    public function testResponseWithoutInvalidFeedItems(): void
    {
        $responseData = [
            'success' => true,
            'message' => 'All items processed successfully'
        ];

        $response = $this->createResponseMock($responseData);

        $result = $this->responseParser->parse($response);

        $this->assertEmpty($result);
    }

    /**
     * Create a mock response object
     *
     * @param array $data
     * @return ResponseInterface|MockObject
     */
    private function createResponseMock(array $data)
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')
            ->willReturn(json_encode($data));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')
            ->willReturn($body);

        $this->serializer->method('unserialize')
            ->willReturn($data);

        return $response;
    }
}
