define([
    'jquery',
    'underscore',
    'mage/translate',
    'PayPal_Braintree/js/helper/format-amount',
    'PayPal_Braintree/js/helper/replace-unsupported-characters'
], function (
    $,
    _,
    $t,
    formatAmount,
    replaceUnsupportedCharacters
) {
    'use strict';

    /**
     * Get line items
     *
     * @returns {Array}
     */
    return function (quote, includeShipping = true, priceIncludesTax = true) {
        let lineItems = [];

        /**
         * Line items array
         *
         * {Array}
         */
        const lineItemsArray = [
            'name',
            'kind',
            'quantity',
            'unitAmount',
            'productCode',
            'description'
        ];

        let giftWrappingItems = 0,
            giftWrappingOrder = 0,
            storeCredit = 0,
            giftCardAccount = 0,
            giftWrappingPrintedCard = 0,
            baseDiscountAmount = formatAmount(Math.abs(quote['base_discount_amount']).toString()),
            baseTaxAmount = formatAmount(quote['base_tax_amount']);

        $.each(quote['total_segments'], function (segmentsKey, segmentsItem) {
            if (segmentsItem['code'] === 'customerbalance') {
                storeCredit = formatAmount(Math.abs(segmentsItem['value']).toString());
            }
            if (segmentsItem['code'] === 'giftcardaccount') {
                giftCardAccount = formatAmount(Math.abs(segmentsItem['value']).toString());
            }
            if (segmentsItem['code'] === 'giftwrapping') {
                let extensionAttributes = segmentsItem['extension_attributes'];

                giftWrappingOrder = extensionAttributes['gw_base_price'];
                giftWrappingItems = extensionAttributes['gw_items_base_price'];
                giftWrappingPrintedCard = extensionAttributes['gw_card_base_price'];
            }
        });

        $.each(quote.items, function (quoteItemKey, quoteItem) {
            if (quoteItem.parent_item_id || quoteItem.base_price === 0.0) {
                return true;
            }

            const unitPrice = (priceIncludesTax
                ? parseFloat(quoteItem.base_price_incl_tax)
                : parseFloat(quoteItem.base_price)) || parseFloat(quoteItem.product_price_value);

            let floatQty = parseFloat(quoteItem.qty),
                itemName = replaceUnsupportedCharacters(quoteItem.name || quoteItem.product_name),
                itemSku = replaceUnsupportedCharacters(quoteItem.sku || quoteItem.product_sku || ''),
                hasQty = floatQty > Math.floor(floatQty) && floatQty < Math.ceil(floatQty),
                description = hasQty
                    ? 'Item quantity is ' + floatQty.toFixed(2) + ' and per unit amount is '
                        + unitPrice.toFixed(2)
                    : '',
                itemQty = hasQty ? parseFloat('1') : floatQty;

            let lineItemValues = [
                    itemName,
                    'debit',
                    itemQty.toFixed(2),
                    unitPrice.toFixed(2),
                    itemSku,
                    description
                ],

                mappedLineItems = $.map(lineItemsArray, function (itemElement, itemIndex) {
                    return [[
                        lineItemsArray[itemIndex],
                        lineItemValues[itemIndex]
                    ]];
                });

            lineItems[quoteItemKey] = Object.fromEntries(mappedLineItems);
        });

        /**
         * Adds credit (refund or discount) kind as LineItems for the
         * PayPal transaction if discount amount is greater than 0(Zero)
         * as discountAmount lineItem field is not being used by PayPal.
         *
         * developer.paypal.com/braintree/docs/reference/response/transaction-line-item/php#discount_amount
         */
        if (baseDiscountAmount > 0) {
            let discountLineItem = {
                'name': $t('Discount'),
                'kind': 'credit',
                'quantity': 1.00,
                'unitAmount': baseDiscountAmount
            };

            lineItems = $.merge(lineItems, [discountLineItem]);
        }

        /**
         * Adds Gift Cards as credit LineItems for the PayPal
         * transaction if it is greater than 0(Zero) to manage
         * the totals with client-side implementation
         */
        if (giftCardAccount > 0) {
            let giftCardItem = {
                'name': $t('Gift Cards'),
                'kind': 'credit',
                'quantity': 1.00,
                'unitAmount': giftCardAccount
            };

            lineItems = $.merge(lineItems, [giftCardItem]);
        }

        /**
         * Adds credit (Store Credit) kind as LineItems for the
         * PayPal transaction if store credit is greater than 0(Zero)
         * to manage the totals with client-side implementation
         */
        if (storeCredit > 0) {
            let storeCreditItem = {
                'name': $t('Store Credit'),
                'kind': 'credit',
                'quantity': 1.00,
                'unitAmount': storeCredit
            };

            lineItems = $.merge(lineItems, [storeCreditItem]);
        }

        /**
         * Adds shipping as LineItems for the PayPal transaction
         * if shipping amount is greater than 0(Zero) to manage
         * the totals with client-side implementation as there is
         * no any field exist in the client-side implementation
         * to send the shipping amount to the Braintree.
         */
        if (includeShipping && quote['base_shipping_amount'] > 0) {
            let shippingLineItem = {
                'name': $t('Shipping'),
                'kind': 'debit',
                'quantity': 1.00,
                'unitAmount': priceIncludesTax ?
                    quote['base_shipping_incl_tax'] :
                    quote['base_shipping_amount']
            };

            lineItems = $.merge(lineItems, [shippingLineItem]);
        }

        /**
         * Adds Gift Wrapping for items as LineItems for the PayPal
         * transaction if it is greater than 0(Zero) to manage
         * the totals with client-side implementation
         */
        if (giftWrappingItems > 0) {
            let gwItems = {
                'name': $t('Gift Wrapping for Items'),
                'kind': 'debit',
                'quantity': 1.00,
                'unitAmount': giftWrappingItems
            };

            lineItems = $.merge(lineItems, [gwItems]);
        }

        /**
         * Adds Gift Wrapping for order as LineItems for the PayPal
         * transaction if it is greater than 0(Zero) to manage
         * the totals with client-side implementation
         */
        if (giftWrappingOrder > 0) {
            let gwOrderItem = {
                'name': $t('Gift Wrapping for Order'),
                'kind': 'debit',
                'quantity': 1.00,
                'unitAmount': giftWrappingOrder
            };

            lineItems = $.merge(lineItems, [gwOrderItem]);
        }

        /**
         * Adds Gift Wrapping Printed Card as LineItems for the PayPal
         * transaction if it is greater than 0(Zero) to manage
         * the totals with client-side implementation
         */
        if (giftWrappingPrintedCard > 0) {
            let gwPrintedCard = {
                'name': $t('Printed Card'),
                'kind': 'debit',
                'quantity': 1.00,
                'unitAmount': giftWrappingPrintedCard
            };

            lineItems = $.merge(lineItems, [gwPrintedCard]);
        }

        if (!priceIncludesTax && baseTaxAmount > 0) {
            let taxLineItem = {
                'name': $t('Tax'),
                'kind': 'debit',
                'quantity': 1.00,
                'unitAmount': baseTaxAmount
            };

            lineItems = $.merge(lineItems, [taxLineItem]);
        }

        if (lineItems.length >= 250) {
            lineItems = [];
        }

        return lineItems;
    };
});
