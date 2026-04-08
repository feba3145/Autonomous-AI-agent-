<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Request;

use Braintree\TransactionLineItem;
use Magento\Directory\Model\Country;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Config\PayPal\Config as PayPalConfig;
use PayPal\Braintree\Gateway\Data\Order\OrderAdapter;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

class Level23ProcessingDataBuilder implements BuilderInterface
{
    private const KEY_PURCHASE_ORDER_NUMBER = 'purchaseOrderNumber';
    private const KEY_TAX_AMT = 'taxAmount';
    private const KEY_SHIPPING_AMT = 'shippingAmount';
    private const KEY_DISCOUNT_AMT = 'discountAmount';
    private const KEY_SHIPS_FROM_POSTAL_CODE = 'shipsFromPostalCode';
    private const KEY_SHIPPING = 'shipping';
    private const KEY_COUNTRY_CODE_ALPHA_3 = 'countryCodeAlpha3';
    public const KEY_LINE_ITEMS = 'lineItems';
    private const LINE_ITEMS_ARRAY = [
        'name',
        'kind',
        'quantity',
        'unitAmount',
        'unitOfMeasure',
        'totalAmount',
        'taxAmount',
        'discountAmount',
        'productCode',
        'commodityCode'
    ];

    /**
     * Level23ProcessingDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     * @param ScopeConfigInterface $scopeConfig
     * @param Country $country
     * @param Config $braintreeConfig
     * @param PayPalConfig $payPalConfig
     */
    public function __construct(
        protected readonly SubjectReader $subjectReader,
        protected readonly ScopeConfigInterface $scopeConfig,
        protected readonly Country $country,
        protected readonly Config $braintreeConfig,
        protected readonly PayPalConfig $payPalConfig
    ) {
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function build(array $buildSubject): array
    {
        $lineItems = [];
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /**
         * Override in di.xml, so we can add extra public methods.
         * In this instance, so we can eventually get the discount amount.
         * @var OrderAdapter $order
         */
        $order = $paymentDO->getOrder();

        $btSendLineItems = $this->braintreeConfig->canSendLineItems();
        if ($btSendLineItems) {
            foreach ($order->getItems() as $item) {
                // Skip configurable parent items and items with a base price of 0.
                if ($item->getParentItem() || 0.0 === $item->getBasePrice()) {
                    continue;
                }

                // Regex to replace all unsupported characters.
                $filteredFields = preg_replace(
                    '/[^a-zA-Z0-9\s\-.\']/',
                    '',
                    [
                        'name' => substr($item->getName(), 0, 35),
                        'unit_of_measure' => substr($item->getProductType(), 0, 12),
                        'sku' => substr($item->getSku(), 0, 12),
                        'commodity_code' => substr($item->getSku(), 0, 12)
                    ]
                );

                $lineItems[] = array_combine(
                    self::LINE_ITEMS_ARRAY,
                    [
                        $filteredFields['name'],
                        TransactionLineItem::DEBIT,
                        $this->numberToString((float)$item->getQtyOrdered(), 2),
                        $this->numberToString((float)$item->getBasePrice(), 2),
                        $filteredFields['unit_of_measure'],
                        $this->numberToString((float)$item->getBaseRowTotal(), 2),
                        $item->getBaseTaxAmount() === null ? '0.00' : $this->numberToString(
                            $item->getBaseTaxAmount(),
                            2
                        ),
                        $item->getBaseDiscountAmount() === null ? '0.00' : $this->numberToString(
                            $item->getBaseDiscountAmount(),
                            2
                        ),
                        $filteredFields['sku'],
                        $filteredFields['commodity_code']
                    ]
                );
            }
        }

        $processingData = [
            self::KEY_PURCHASE_ORDER_NUMBER => substr(
                $order->getOrderIncrementId(),
                -12,
                12
            ), // Level 2.
            self::KEY_TAX_AMT => $this->numberToString(
                $order->getBaseTaxAmount(),
                2
            ), // Level 2.
            self::KEY_DISCOUNT_AMT => $this->numberToString(
                abs($order->getBaseDiscountAmount()),
                2
            ), // Level 3.
        ];

        // Can send line items to braintree if enabled and line items are less than 250.
        if ($btSendLineItems && count($lineItems) < 250) {
            $processingData[self::KEY_LINE_ITEMS] = $lineItems; // Level 3.
        }

        // Only add these shipping related details if a shipping address is present.
        if ($order->getShippingAddress()) {
            $storePostalCode = $this->scopeConfig->getValue(
                'general/store_information/postcode',
                ScopeInterface::SCOPE_STORE
            );

            $address = $order->getShippingAddress();
            // use Magento's Alpha2 code to get the Alpha3 code.
            $country  = $this->country->loadByCode($address->getCountryId());

            // Level 3.
            $processingData[self::KEY_SHIPPING_AMT] = $this->numberToString(
                $order->getBaseShippingAmount(),
                2
            );
            $processingData[self::KEY_SHIPS_FROM_POSTAL_CODE] = $storePostalCode;
            $processingData[self::KEY_SHIPPING] = [
                self::KEY_COUNTRY_CODE_ALPHA_3 => $country['iso3_code'] ?? $address->getCountryId()
            ];
        }

        return $processingData;
    }

    /**
     * Number to string conversion
     *
     * @param float|string $num
     * @param int $precision
     * @return string
     */
    public function numberToString(float|string $num, int $precision): string
    {
        // To counter the fact that Magento often wrongly returns a sting for price values, we can cast it to a float.
        if (is_string($num)) {
            $num = (float) $num;
        }

        return (string) round($num, $precision);
    }
}
