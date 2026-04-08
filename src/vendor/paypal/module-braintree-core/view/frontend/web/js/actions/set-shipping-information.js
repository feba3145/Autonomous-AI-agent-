define([
    'jquery',
    'mage/storage',
    'mage/translate',
    'PayPal_Braintree/js/helper/get-api-url',
    'PayPal_Braintree/js/helper/is-cart-virtual'
], function ($, storage, $t, getApiUrl, isCartVirtual) {
    'use strict';

    // TODO: Remove need for storeCode to be passed in.
    return function (payload, storeCode, quoteId) {
        if (!isCartVirtual()) {
            $('body').trigger('processStart');

            return storage.post(
                getApiUrl('shipping-information', storeCode, quoteId),
                JSON.stringify(payload)
            ).catch(function (r) {
                console.error('Braintree PayPal unable to set shipping information', r);
                throw new Error($t('Braintree PayPal unable to set shipping information.'));
            }).always(function () {
                $('body').trigger('processStop');
            });
        }

        return Promise.resolve();
    };
});
