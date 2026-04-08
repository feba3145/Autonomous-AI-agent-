define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    // Magento's Magento_Customer/js/model/customer doesn't always give the correct information about logged in
    // if you're not in the checkout so look at the localStorage instead using the same logic Magento does.
    return function () {
        const customer = customerData.get('customer');

        return customer().firstname;
    };
});
