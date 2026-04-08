/**
 * Braintree Google Pay helper to get a formatted list of line items.
 **/
define(['PayPal_Braintree/js/helper/get-cart-line-items-helper'], function (getCartLineItems) {
    'use strict';

    return function (totals, priceIncludesTax) {
        /**
         * @returns array
         */
        return getCartLineItems(totals, true, priceIncludesTax).map((lineItem) => {
            const price = ((parseFloat(lineItem.unitAmount) * 100) * parseFloat(lineItem.quantity)) / 100;
            return {
                type: 'LINE_ITEM',
                label: lineItem.name,
                price: ((lineItem.kind === 'debit' ? 1 : -1) * price).toString()
            }
        });
    }
});
