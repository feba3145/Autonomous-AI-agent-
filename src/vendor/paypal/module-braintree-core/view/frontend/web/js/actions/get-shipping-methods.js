define([
    'mage/storage',
    'PayPal_Braintree/js/helper/get-api-url'
], function (storage, getApiUrl) {
    'use strict';

    // TODO: Remove need for storeCode to be passed in.
    return function (payload, storeCode, quoteId) {
        return storage.post(
            getApiUrl("estimate-shipping-methods", storeCode, quoteId),
            JSON.stringify(payload)
        );
    };
});
