define([
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/model/authentication-popup'
], function (customerData, authenticationPopup) {
    'use strict';

    return function () {
        const cart = customerData.get('cart'),
            customer = customerData.get('customer');

        // Check if the User is able to checkout as a guest.
        if (!customer().firstname && cart().isGuestCheckoutAllowed === false) {
            authenticationPopup.showModal();
            return false;
        }

        return true;
    }
});
