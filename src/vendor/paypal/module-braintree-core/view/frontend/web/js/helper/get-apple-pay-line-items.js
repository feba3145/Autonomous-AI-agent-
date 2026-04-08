/**
 * Braintree Apple Pay helper to get a formatted list of line items.
 **/
define(['PayPal_Braintree/js/helper/get-cart-line-items-helper'], function (getCartLineItems) {
    'use strict';

    return function (totals, priceIncludesTax) {
        /**
         * @returns array
         */
        return getCartLineItems(totals, true, priceIncludesTax).map((lineItem) => {
            return {
                type: 'final',
                label: lineItem.name,
                amount: (lineItem.kind === 'debit' ? 1 : -1) * lineItem.unitAmount * lineItem.quantity
            }
        });
    }
});
