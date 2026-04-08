define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    return function () {
        const cart = customerData.get('cart')();

        if (cart?.items) {
            return !cart.items.some((cartItem) => !cartItem.is_virtual);
        }

        return false;
    }
});
