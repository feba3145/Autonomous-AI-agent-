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

namespace Magento\SaaSCommon\Test\Integration\Model\Http;

use Magento\DataExporter\Status\ExportStatusCodeProvider;
use Magento\SaaSCommon\Model\Http\ResponseParser;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Integration test for ResponseParser
 *
 * Tests the real behavior of ResponseParser when parsing HTTP responses with items_limit_exceeded errors
 */
class ResponseParserTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ResponseParser
     */
    private $responseParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        
        // Create ResponseParser with real dependencies from object manager
        $this->responseParser = $this->objectManager->create(
            ResponseParser::class,
            ['errorItemsField' => 'invalidFeedItems']
        );
    }

    /**
     * Test that items_limit_exceeded error is correctly parsed and marked as RETRYABLE
     *
     * This tests the real integration flow where ResponseParser receives an HTTP response
     * and must parse it to extract error information and determine the correct status.
     */
    public function testItemsLimitExceededIsMarkedAsRetryable(): void
    {
        // Simulate real CCDM API response with items_limit_exceeded error
        $responseBody = json_encode([
            'invalidFeedItems' => [
                [
                    'itemIndex' => 100,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'itemIndex' => 101,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'itemIndex' => 102,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
            ]
        ]);

        $response = $this->createHttpResponse(400, 'Bad Request', $responseBody);

        // Parse the response - this is where the fix is tested
        $result = $this->responseParser->parse($response);

        // Verify all items have RETRYABLE status
        $this->assertCount(3, $result, 'Should parse 3 failed items');
        
        foreach ($result as $index => $item) {
            $this->assertEquals(
                ExportStatusCodeProvider::RETRYABLE,
                $item['status'],
                "Item $index with items_limit_exceeded should have RETRYABLE status"
            );
            $this->assertEquals('items_limit_exceeded', $item['field']);
            $this->assertEquals('Item quantity exceeds the maximum allowed limit', $item['message']);
        }
    }

    /**
     * Test that regular validation errors are marked as FAILED_ITEM_ERROR
     */
    public function testValidationErrorsAreMarkedAsFailedItemError(): void
    {
        // Simulate CCDM API response with validation errors
        $responseBody = json_encode([
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
                ],
            ]
        ]);

        $response = $this->createHttpResponse(400, 'Bad Request', $responseBody);

        $result = $this->responseParser->parse($response);

        $this->assertCount(2, $result);
        
        // Validation errors should NOT be retryable
        $this->assertEquals(
            ExportStatusCodeProvider::FAILED_ITEM_ERROR,
            $result[0]['status'],
            'Validation errors should have FAILED_ITEM_ERROR status'
        );
        $this->assertEquals('invalid_sku', $result[0]['field']);
        
        $this->assertEquals(
            ExportStatusCodeProvider::FAILED_ITEM_ERROR,
            $result[1]['status'],
            'Validation errors should have FAILED_ITEM_ERROR status'
        );
        $this->assertEquals('missing_required_field', $result[1]['field']);
    }

    /**
     * Test mixed errors - some items_limit_exceeded, some validation errors
     *
     * This simulates a real scenario where a batch has both types of errors
     */
    public function testMixedErrorTypesAreHandledCorrectly(): void
    {
        $responseBody = json_encode([
            'invalidFeedItems' => [
                [
                    'itemIndex' => 100,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
                [
                    'itemIndex' => 50,
                    'code' => 'invalid_sku',
                    'message' => 'SKU is invalid'
                ],
                [
                    'itemIndex' => 101,
                    'code' => 'items_limit_exceeded',
                    'message' => 'Item quantity exceeds the maximum allowed limit'
                ],
            ]
        ]);

        $response = $this->createHttpResponse(400, 'Bad Request', $responseBody);

        $result = $this->responseParser->parse($response);

        $this->assertCount(3, $result);
        
        // First item: items_limit_exceeded -> RETRYABLE
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[100]['status']);
        $this->assertEquals('items_limit_exceeded', $result[100]['field']);
        
        // Second item: validation error -> FAILED_ITEM_ERROR
        $this->assertEquals(ExportStatusCodeProvider::FAILED_ITEM_ERROR, $result[50]['status']);
        $this->assertEquals('invalid_sku', $result[50]['field']);
        
        // Third item: items_limit_exceeded -> RETRYABLE
        $this->assertEquals(ExportStatusCodeProvider::RETRYABLE, $result[101]['status']);
        $this->assertEquals('items_limit_exceeded', $result[101]['field']);
    }

    /**
     * Test the actual production scenario from MDEE-1264
     *
     * Simulates the exact error pattern seen in customer logs where batch size exceeded 100 items
     */
    public function testProductionScenarioWithLargeBatch(): void
    {
        // Simulate batch of items 100-199 all failing with items_limit_exceeded
        $invalidItems = [];
        for ($i = 100; $i <= 199; $i++) {
            $invalidItems[] = [
                'itemIndex' => $i,
                'code' => 'items_limit_exceeded',
                'message' => 'Item quantity exceeds the maximum allowed limit'
            ];
        }

        $responseBody = json_encode(['invalidFeedItems' => $invalidItems]);
        $response = $this->createHttpResponse(400, 'Bad Request', $responseBody);

        $result = $this->responseParser->parse($response);

        // All 100 items should be marked as RETRYABLE
        $this->assertCount(100, $result, 'Should parse all 100 failed items');
        
        foreach ($result as $index => $item) {
            $this->assertGreaterThanOrEqual(100, $index, 'Item indices should start at 100');
            $this->assertLessThanOrEqual(199, $index, 'Item indices should end at 199');
            $this->assertEquals(
                ExportStatusCodeProvider::RETRYABLE,
                $item['status'],
                "Item $index should be marked as RETRYABLE for automatic retry"
            );
        }
    }

    /**
     * Create a mock HTTP response
     *
     * @param int $statusCode
     * @param string $reasonPhrase
     * @param string $body
     * @return ResponseInterface
     */
    private function createHttpResponse(int $statusCode, string $reasonPhrase, string $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
