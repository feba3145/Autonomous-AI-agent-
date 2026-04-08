<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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

namespace Magento\PaymentServicesPaypal\Test\Unit\Helper;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Helper\L2DataProvider;
use Magento\PaymentServicesPaypal\Helper\L3DataProvider;
use Magento\PaymentServicesPaypal\Helper\LineItemsProvider;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderHelperTest extends TestCase
{
    private const ORDER_INCREMENT_ID = '100000001';
    private const STORE_ID = 1;

    /**
     * @var MockObject|L2DataProvider
     */
    private MockObject|L2DataProvider $l2DataProvider;

    /**
     * @var MockObject|L3DataProvider
     */
    private MockObject|L3DataProvider $l3DataProvider;

    /**
     * @var MockObject|LineItemsProvider
     */
    private MockObject|LineItemsProvider $lineItemsProvider;

    /**
     * @var MockObject|Config
     */
    private MockObject|Config $config;

    /**
     * @var MockObject|LoggerInterface
     */
    private MockObject|LoggerInterface $logger;

    /**
     * @var MockObject|QuoteIdMaskFactory
     */
    private MockObject|QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @var MockObject|QuoteIdMask
     */
    private MockObject|QuoteIdMask $quoteIdMaskResource;

    /**
     * @var MockObject|UrlInterface
     */
    private MockObject|UrlInterface $urlBuilder;

    /**
     * @var MockObject|RedirectInterface
     */
    private MockObject|RedirectInterface $redirect;

    /**
     * @var OrderHelper
     */
    private OrderHelper $orderHelper;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->l2DataProvider = $this->createMock(L2DataProvider::class);
        $this->l3DataProvider = $this->createMock(L3DataProvider::class);
        $this->lineItemsProvider = $this->createMock(LineItemsProvider::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->quoteIdMaskFactory = $this->createMock(QuoteIdMaskFactory::class);
        $this->quoteIdMaskResource = $this->createMock(QuoteIdMask::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->redirect = $this->createMock(RedirectInterface::class);

        $this->orderHelper = new OrderHelper(
            $this->l2DataProvider,
            $this->l3DataProvider,
            $this->lineItemsProvider,
            $this->config,
            $this->logger,
            $this->quoteIdMaskFactory,
            $this->quoteIdMaskResource,
            $this->urlBuilder,
            $this->redirect
        );

        $this->lineItemsProvider->expects($this->any())
            ->method('toCents')
            ->willReturnCallback(
                function (float $amount):int {
                    return (int)($amount * 100);
                }
            );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetLineItemsWithNoAmountMisMatch(): void
    {
        // baseSubtotal should be the sum of the lineItems unit_amount * quantity
        // baseTaxAmount should be the sum of the lineItems tax * quantity
        $quote = $this->createQuote(80.28, 70.00, 10.28);

        $lineItems = [
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '15.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '2.00',
                    'currency_code' => 'USD'
                ],
            ],
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '20.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '3.14',
                    'currency_code' => 'USD'
                ],
            ]
        ];

        $this->givenSendingLineItemsIs(true);

        $this->lineItemsProvider->expects($this->once())
            ->method('getLineItems')
            ->with($quote)
            ->willReturn($lineItems);

        $this->lineItemsProvider->expects($this->any())
            ->method('toCents')
            ->willReturnCallback(
                function (float $amount):int {
                    return intval($amount * 100);
                }
            );

        $expectedLineItems = $this->orderHelper->getLineItems($quote, self::ORDER_INCREMENT_ID);

        $this->logger->expects($this->never())
            ->method('info');

        $this->assertEquals($expectedLineItems, $lineItems);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetLineItemsWithTaxAmountMisMatch(): void
    {
        // baseSubtotal should be the sum of the lineItems unit_amount * quantity
        // baseTaxAmount should be the sum of the lineItems tax * quantity
        $quote = $this->createQuote(80.00, 70.00, 10.00);

        $lineItems = [
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '15.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '2.00',
                    'currency_code' => 'USD'
                ],
            ],
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '20.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '3.14',
                    'currency_code' => 'USD'
                ],
            ]
        ];

        $this->givenSendingLineItemsIs(true);

        $this->lineItemsProvider->expects($this->once())
            ->method('getLineItems')
            ->with($quote)
            ->willReturn($lineItems);

        $expectedLineItems = $this->orderHelper->getLineItems($quote, self::ORDER_INCREMENT_ID);

        $this->assertEmpty($expectedLineItems);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetLineItemsWithAmountMisMatch(): void
    {
        // baseSubtotal should be the sum of the lineItems unit_amount * quantity
        // baseTaxAmount should be the sum of the lineItems tax * quantity
        $quote = $this->createQuote(84.28, 74.00, 10.28);

        $lineItems = [
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '15.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '2.00',
                    'currency_code' => 'USD'
                ],
            ],
            [
                'quantity' => '2',
                'unit_amount' => [
                    'value' => '20.00',
                    'currency_code' => 'USD'
                ],
                'tax' => [
                    'value' => '3.14',
                    'currency_code' => 'USD'
                ],
            ]
        ];

        $this->givenSendingLineItemsIs(true);

        $this->lineItemsProvider->expects($this->once())
            ->method('getLineItems')
            ->with($quote)
            ->willReturn($lineItems);

        $expectedLineItems = $this->orderHelper->getLineItems($quote, self::ORDER_INCREMENT_ID);

        $this->assertEmpty($expectedLineItems);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetAmountBreakdownWithoutMisMatch(): void
    {
        $quote = $this->createQuote(96.00, 74.00, 10.00, 10.00, 2.00);

        $expectedAmountBreakdown = [
            'item_total' => [
                'value' => '74.00',
                'currency_code' => 'USD'
            ],
            'shipping' => [
                'value' => '10.00',
                'currency_code' => 'USD'
            ],
            'tax_total' => [
                'value' => '10.00',
                'currency_code' => 'USD'
            ],
            'discount' => [
                'value' => '2.00',
                'currency_code' => 'USD'
            ],
        ];

        $this->givenSendingLineItemsIs(true);

        $amountBreakdown = $this->orderHelper->getAmountBreakdown($quote, self::ORDER_INCREMENT_ID);

        $this->assertEquals($expectedAmountBreakdown, $amountBreakdown);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetLineItemsWhenSendingLineItemsIsDisabled(): void
    {
        $quote = $this->createQuote(84.28, 74.00, 10.28);

        $this->givenSendingLineItemsIs(false);

        $this->lineItemsProvider->expects($this->never())
            ->method('getLineItems')
            ->with($quote);

        $expectedLineItems = $this->orderHelper->getLineItems($quote, self::ORDER_INCREMENT_ID);

        $this->assertEmpty($expectedLineItems);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetAmountBreakdownWithMisMatch(): void
    {
        $quote = $this->createQuote(98.00, 74.00, 10.00, 12.00, 3.00);

        $this->givenSendingLineItemsIs(true);

        $amountBreakdown = $this->orderHelper->getAmountBreakdown($quote, self::ORDER_INCREMENT_ID);

        $this->assertEmpty($amountBreakdown);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetAmountBreakdownWhenSendingLineItemsIsDisabled(): void
    {
        $quote = $this->createQuote(98.00, 74.00, 10.00, 12.00, 3.00);

        $this->givenSendingLineItemsIs(false);

        $amountBreakdown = $this->orderHelper->getAmountBreakdown($quote, self::ORDER_INCREMENT_ID);

        $this->assertEmpty($amountBreakdown);
    }

    /**
     * Test formatAmount method
     *
     * @return void
     */
    public function testFormatAmount(): void
    {
        $this->assertEquals('10.00', $this->orderHelper->formatAmount(10.0));
        $this->assertEquals('10.50', $this->orderHelper->formatAmount(10.5));
        $this->assertEquals('10.99', $this->orderHelper->formatAmount(10.99));
        $this->assertEquals('0.00', $this->orderHelper->formatAmount(0.0));
        $this->assertEquals('100.10', $this->orderHelper->formatAmount(100.1));
    }

    /**
     * Test getL2Data returns data when payment source is supported and L2/L3 enabled
     *
     * @return void
     * @throws Exception
     */
    public function testGetL2DataWithSupportedPaymentSource(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);
        $expectedL2Data = ['invoice_id' => '12345'];

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(true);

        $this->l2DataProvider->expects($this->once())
            ->method('getL2Data')
            ->with($quote)
            ->willReturn($expectedL2Data);

        $result = $this->orderHelper->getL2Data($quote, 'cc');

        $this->assertEquals($expectedL2Data, $result);
    }

    /**
     * Test getL2Data returns empty array when payment source is not supported
     *
     * @return void
     * @throws Exception
     */
    public function testGetL2DataWithUnsupportedPaymentSource(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(true);

        $this->l2DataProvider->expects($this->never())
            ->method('getL2Data');

        $result = $this->orderHelper->getL2Data($quote, 'paypal');

        $this->assertEmpty($result);
    }

    /**
     * Test getL2Data returns empty array when L2/L3 is disabled
     *
     * @return void
     * @throws Exception
     */
    public function testGetL2DataWhenDisabled(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(false);

        $this->l2DataProvider->expects($this->never())
            ->method('getL2Data');

        $result = $this->orderHelper->getL2Data($quote, 'cc');

        $this->assertEmpty($result);
    }

    /**
     * Test getL3Data returns data when payment source is supported and L2/L3 enabled
     *
     * @return void
     * @throws Exception
     */
    public function testGetL3DataWithSupportedPaymentSource(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);
        $expectedL3Data = ['line_items' => []];

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(true);

        $this->l3DataProvider->expects($this->once())
            ->method('getL3Data')
            ->with($quote)
            ->willReturn($expectedL3Data);

        $result = $this->orderHelper->getL3Data($quote, 'cc');

        $this->assertEquals($expectedL3Data, $result);
    }

    /**
     * Test getL3Data returns empty array when payment source is not supported
     *
     * @return void
     * @throws Exception
     */
    public function testGetL3DataWithUnsupportedPaymentSource(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(true);

        $this->l3DataProvider->expects($this->never())
            ->method('getL3Data');

        $result = $this->orderHelper->getL3Data($quote, 'paypal');

        $this->assertEmpty($result);
    }

    /**
     * Test getL3Data returns empty array when L2/L3 is disabled
     *
     * @return void
     * @throws Exception
     */
    public function testGetL3DataWhenDisabled(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);

        $this->config->expects($this->once())
            ->method('isL2L3SendDataEnabled')
            ->willReturn(false);

        $this->l3DataProvider->expects($this->never())
            ->method('getL3Data');

        $result = $this->orderHelper->getL3Data($quote, 'cc');

        $this->assertEmpty($result);
    }

    /**
     * Test reserveAndGetOrderIncrementId
     *
     * @return void
     * @throws Exception
     */
    public function testReserveAndGetOrderIncrementId(): void
    {
        $quote = $this->createQuote(100.00, 80.00, 10.00);
        $expectedOrderId = '000000123';

        $quote->expects($this->once())
            ->method('reserveOrderId');

        $quote->expects($this->once())
            ->method('getReservedOrderId')
            ->willReturn($expectedOrderId);

        $result = $this->orderHelper->reserveAndGetOrderIncrementId($quote);

        $this->assertEquals($expectedOrderId, $result);
    }

    /**
     * Test validateCheckoutLocation with valid locations
     *
     * @dataProvider validCheckoutLocationsDataProvider
     * @param string $input
     * @param string $expected
     * @return void
     */
    public function testValidateCheckoutLocationWithValidLocations(string $input, string $expected): void
    {
        $result = $this->orderHelper->validateCheckoutLocation($input);

        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for valid checkout locations
     *
     * @return array
     */
    public static function validCheckoutLocationsDataProvider(): array
    {
        return [
            'product maps to product_detail' => ['product', 'PRODUCT_DETAIL'],
            'product uppercase maps to product_detail' => ['PRODUCT', 'PRODUCT_DETAIL'],
            'product_detail stays same' => ['PRODUCT_DETAIL', 'PRODUCT_DETAIL'],
            'product_detail lowercase' => ['product_detail', 'PRODUCT_DETAIL'],
            'checkout stays same' => ['CHECKOUT', 'CHECKOUT'],
            'minicart stays same' => ['MINICART', 'MINICART'],
            'cart stays same' => ['CART', 'CART'],
            'admin stays same' => ['ADMIN', 'ADMIN'],
        ];
    }

    /**
     * Test validateCheckoutLocation returns null for invalid locations
     *
     * @return void
     */
    public function testValidateCheckoutLocationWithInvalidLocation(): void
    {
        $result = $this->orderHelper->validateCheckoutLocation('INVALID_LOCATION');

        $this->assertNull($result);
    }

    /**
     * Test validateCheckoutLocation returns null for null input
     *
     * @return void
     */
    public function testValidateCheckoutLocationWithNullInput(): void
    {
        $result = $this->orderHelper->validateCheckoutLocation(null);

        $this->assertNull($result);
    }

    /**
     * Test getUserAction returns PAY_NOW
     *
     * @return void
     */
    public function testGetUserAction(): void
    {
        $result = $this->orderHelper->getUserAction();

        $this->assertEquals('PAY_NOW', $result);
    }

    /**
     * Test getShippingPreference returns GET_FROM_FILE for physical products
     *
     * @return void
     * @throws Exception
     */
    public function testGetShippingPreferenceWithPhysicalProducts(): void
    {
        $quote = $this->createQuoteWithItems(false);

        $result = $this->orderHelper->getShippingPreference($quote);

        $this->assertEquals('GET_FROM_FILE', $result);
    }

    /**
     * Test getShippingPreference returns NO_SHIPPING for virtual products
     *
     * @return void
     * @throws Exception
     */
    public function testGetShippingPreferenceWithVirtualProducts(): void
    {
        $quote = $this->createQuoteWithItems(true);

        $result = $this->orderHelper->getShippingPreference($quote);

        $this->assertEquals('NO_SHIPPING', $result);
    }

    /**
     * Test isAppSwitchEnabled
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function testIsAppSwitchEnabled(): void
    {
        $this->config->expects($this->once())
            ->method('getAppSwitch')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $result = $this->orderHelper->isAppSwitchEnabled(self::STORE_ID);

        $this->assertTrue($result);
    }

    /**
     * Test isAppSwitchEnabled when disabled
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function testIsAppSwitchEnabledWhenDisabled(): void
    {
        $this->config->expects($this->once())
            ->method('getAppSwitch')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $result = $this->orderHelper->isAppSwitchEnabled(self::STORE_ID);

        $this->assertFalse($result);
    }

    /**
     * Test isContactPreferenceEnabled
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function testIsContactPreferenceEnabled(): void
    {
        $this->config->expects($this->once())
            ->method('getContactPreference')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $result = $this->orderHelper->isContactPreferenceEnabled(self::STORE_ID);

        $this->assertTrue($result);
    }

    /**
     * Test isContactPreferenceEnabled when disabled
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function testIsContactPreferenceEnabledWhenDisabled(): void
    {
        $this->config->expects($this->once())
            ->method('getContactPreference')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $result = $this->orderHelper->isContactPreferenceEnabled(self::STORE_ID);

        $this->assertFalse($result);
    }

    /**
     * Test getCurrentPageUrl
     *
     * @return void
     */
    public function testGetCurrentPageUrl(): void
    {
        $expectedUrl = 'https://example.com/current-page';

        $this->redirect->expects($this->once())
            ->method('getRefererUrl')
            ->willReturn($expectedUrl);

        $result = $this->orderHelper->getCurrentPageUrl();

        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test getCheckoutPaymentSectionUrl
     *
     * @return void
     */
    public function testGetCheckoutPaymentSectionUrl(): void
    {
        $expectedUrl = 'https://example.com/checkout#payment';

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('checkout', ['_secure' => true, '_fragment' => 'payment'])
            ->willReturn($expectedUrl);

        $result = $this->orderHelper->getCheckoutPaymentSectionUrl();

        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test getReturnUrl
     *
     * @return void
     */
    public function testGetReturnUrl(): void
    {
        $expectedUrl = 'https://example.com/checkout/onepage/success';

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('checkout/onepage/success', ['_secure' => true])
            ->willReturn($expectedUrl);

        $result = $this->orderHelper->getReturnUrl();

        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test getAmountBreakdown with virtual quote
     *
     * @return void
     * @throws Exception
     */
    public function testGetAmountBreakdownWithVirtualQuote(): void
    {
        $quote = $this->createVirtualQuote(84.00, 74.00, 10.00, 0.00, 0.00);

        $expectedAmountBreakdown = [
            'item_total' => [
                'value' => '74.00',
                'currency_code' => 'USD'
            ],
            'shipping' => [
                'value' => '0.00',
                'currency_code' => 'USD'
            ],
            'tax_total' => [
                'value' => '10.00',
                'currency_code' => 'USD'
            ],
            'discount' => [
                'value' => '0.00',
                'currency_code' => 'USD'
            ],
        ];

        $this->givenSendingLineItemsIs(true);

        $amountBreakdown = $this->orderHelper->getAmountBreakdown($quote, self::ORDER_INCREMENT_ID);

        $this->assertEquals($expectedAmountBreakdown, $amountBreakdown);
    }

    /**
     * Create a quote with items
     *
     * @param bool $allVirtual
     * @return Quote|MockObject
     * @throws Exception
     */
    private function createQuoteWithItems(bool $allVirtual): Quote|MockObject
    {
        $item = $this->getMockBuilder(Quote\Item::class)
            ->addMethods(['getIsVirtual'])
            ->disableOriginalConstructor()
            ->getMock();

        $item->expects($this->any())
            ->method('getIsVirtual')
            ->willReturn($allVirtual);

        $quote = $this->getMockBuilder(Quote::class)
            ->onlyMethods(['getAllVisibleItems'])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->expects($this->once())
            ->method('getAllVisibleItems')
            ->willReturn([$item]);

        return $quote;
    }

    /**
     * Create a virtual quote
     *
     * @param float $quoteGrandTotal
     * @param float $quoteSubtotal
     * @param float $addressTaxAmount
     * @param float $addressShippingAmount
     * @param float $addressDiscountAmount
     * @return Quote|MockObject
     * @throws Exception
     */
    private function createVirtualQuote(
        float $quoteGrandTotal,
        float $quoteSubtotal,
        float $addressTaxAmount,
        float $addressShippingAmount = 0.00,
        float $addressDiscountAmount = 0.00,
    ): Quote|MockObject {
        $currency = $this->createCurrency();

        $address = $this->getMockBuilder(Quote\Address::class)
            ->addMethods([
                'getBaseTaxAmount',
                'getBaseShippingAmount',
                'getBaseDiscountAmount',
                'getBaseShippingTaxAmount',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $address->expects($this->any())
            ->method('getBaseTaxAmount')
            ->willReturn($addressTaxAmount);

        $address->expects($this->any())
            ->method('getBaseShippingAmount')
            ->willReturn($addressShippingAmount);

        $address->expects($this->any())
            ->method('getBaseDiscountAmount')
            ->willReturn($addressDiscountAmount);

        $address->expects($this->any())
            ->method('getBaseShippingTaxAmount')
            ->willReturn(0.00);

        $quote = $this->getMockBuilder(Quote::class)
            ->addMethods([
                'getBaseSubtotal',
                'getBaseTaxAmount',
                'getBaseGrandTotal',
            ])
            ->onlyMethods([
                'getShippingAddress',
                'getBillingAddress',
                'isVirtual',
                'getCurrency',
                'getId',
                'getPayment',
                'getAllVisibleItems',
                'reserveOrderId',
                'getReservedOrderId',
                'getStoreId',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->expects($this->any())
            ->method('getCurrency')
            ->willReturn($currency);

        $quote->expects($this->any())
            ->method('getBaseSubtotal')
            ->willReturn($quoteSubtotal);

        $quote->expects($this->any())
            ->method('getBaseGrandTotal')
            ->willReturn($quoteGrandTotal);

        $quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($address);

        $quote->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($address);

        $quote->expects($this->any())
            ->method('isVirtual')
            ->willReturn(true);

        $quote->expects($this->any())
            ->method('getStoreId')
            ->willReturn(self::STORE_ID);

        return $quote;
    }

    /**
     * Create a quote
     *
     * @param float $quoteGrandTotal
     * @param float $quoteSubtotal
     * @param float $addressTaxAmount
     * @param float $addressShippingAmount
     * @param float $addressDiscountAmount
     * @return Quote
     * @throws Exception
     */
    private function createQuote(
        float $quoteGrandTotal,
        float $quoteSubtotal,
        float $addressTaxAmount,
        float $addressShippingAmount = 10.00,
        float $addressDiscountAmount = 2.00,
    ): Quote {
        $currency = $this->createCurrency();

        $address = $this->getMockBuilder(Quote\Address::class)
            ->addMethods([
                'getBaseTaxAmount',
                'getBaseShippingAmount',
                'getBaseDiscountAmount',
                'getBaseShippingTaxAmount',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $address->expects($this->any())
            ->method('getBaseTaxAmount')
            ->willReturn($addressTaxAmount);

        $address->expects($this->any())
            ->method('getBaseShippingAmount')
            ->willReturn($addressShippingAmount);

        $address->expects($this->any())
            ->method('getBaseDiscountAmount')
            ->willReturn($addressDiscountAmount);

        $address->expects($this->any())
            ->method('getBaseShippingTaxAmount')
            ->willReturn(0.00);

        $quote = $this->getMockBuilder(Quote::class)
            ->addMethods([
                'getBaseSubtotal',
                'getBaseTaxAmount',
                'getBaseGrandTotal',
            ])
            ->onlyMethods([
                'getShippingAddress',
                'getBillingAddress',
                'isVirtual',
                'getCurrency',
                'getId',
                'getPayment',
                'getAllVisibleItems',
                'reserveOrderId',
                'getReservedOrderId',
                'getStoreId',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->expects($this->any())
            ->method('getCurrency')
            ->willReturn($currency);

        $quote->expects($this->any())
            ->method('getBaseSubtotal')
            ->willReturn($quoteSubtotal);

        $quote->expects($this->any())
            ->method('getBaseGrandTotal')
            ->willReturn($quoteGrandTotal);

        $quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($address);

        $quote->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($address);

        $quote->expects($this->any())
            ->method('isVirtual')
            ->willReturn(false);

        $quote->expects($this->any())
            ->method('getStoreId')
            ->willReturn(self::STORE_ID);

        return $quote;
    }

    /**
     * Create a currency
     *
     * @return CurrencyInterface
     * @throws Exception
     */
    private function createCurrency(): CurrencyInterface
    {
        $currency = $this->createMock(CurrencyInterface::class);
        $currency->expects($this->any())
            ->method('getBaseCurrencyCode')
            ->willReturn('USD');

        return $currency;
    }

    /**
     * Mock the configuration for sending line items
     *
     * @param bool $enabled
     * @return void
     */
    private function givenSendingLineItemsIs(bool $enabled): void
    {
        $this->config->expects($this->once())
            ->method('isSendLineItemsEnabled')
            ->with(self::STORE_ID)
            ->willReturn($enabled);
    }
}
