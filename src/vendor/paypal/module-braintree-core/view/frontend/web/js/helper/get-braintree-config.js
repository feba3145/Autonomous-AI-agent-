define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (storeCode) {
        return $.ajax({
            method: 'POST',
            url: urlBuilder.build('graphql'),
            headers: {
                'Store': storeCode
            },
            contentType: 'application/json',
            data: JSON.stringify({
                query: `{
                    storeConfig {
                        braintree_merchant_account_id,
                        braintree_3dsecure_verify_3dsecure,
                        braintree_3dsecure_always_request_3ds,
                        braintree_3dsecure_threshold_amount,
                        braintree_3dsecure_allowspecific,
                        braintree_3dsecure_specificcountry
                    }
                  }`
            })
        });
    };
});
