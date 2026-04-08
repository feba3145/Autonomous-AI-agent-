define([
        'PayPal_Braintree/js/helper/is-logged-in'
], function (isLoggedIn) {
    'use strict';

    return function (uri, storeCode, quoteId) {
        if (isLoggedIn()) {
        return "rest/" + storeCode + "/V1/carts/mine/" + uri;
        } else {
            return "rest/" + storeCode + "/V1/guest-carts/" + quoteId + "/" + uri;
        }
    };
});
