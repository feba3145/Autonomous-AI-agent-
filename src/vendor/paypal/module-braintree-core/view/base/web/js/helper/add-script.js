define([
    'jquery',
    'braintree',
    'braintreePayPalCheckout'
], function ($, braintree, paypalCheckout) {
    'use strict';

    // Create a cache of all the loaded scripts so that we don't load them multiple times.
    const cache = {};

    return function (clientToken, currency, namespace = 'paypal', pageType = 'checkout') {
        // If the key has already been used return the existing promise that will already be resolved.
        if (cache[namespace]) {
            return cache[namespace];
        }

        // New keys we will add to the cache and then return the pending promise.
        cache[namespace] = new Promise((resolve, reject) => {
            // Load SDK
            braintree.create({
                authorization: clientToken
            }, function (clientErr, clientInstance) {
                if (clientErr) {
                    console.error('paypalCheckout error', clientErr);
                    let error = 'PayPal Checkout could not be initialized. Please contact the store owner.';

                    reject(error);
                    return;
                }

                paypalCheckout.create({
                    client: clientInstance
                }, function (err, paypalCheckoutInstance) {
                    if (err) {
                        console.error('paypalCheckout error', clientErr);
                        let error = 'PayPal Checkout could not be initialized. Please contact the store owner.';

                        reject(error);
                        return;
                    }

                    let configSDK = {
                        components: 'messages',
                        currency: currency,
                        dataAttributes: {
                            namespace: `paypal_${namespace}`,
                            'page-type': pageType
                        }
                    };

                    // eslint-disable-next-line max-nested-callbacks
                    paypalCheckoutInstance.loadPayPalSDK(configSDK, function () {
                        $(document).trigger('paypalBraintreeScriptLoaded', namespace);
                        resolve();
                    });
                });
            });
        });

        return cache[namespace];
    };
});
