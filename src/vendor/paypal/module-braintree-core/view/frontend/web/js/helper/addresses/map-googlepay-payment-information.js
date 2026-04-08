define([
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/model/region-data'
], function (paymentDataModel, regionDataModel) {
    'use strict';

    return function (payload, shippingAddress) {
        const billingAddress = payload.details?.billingAddress?.streetAddress
                ? payload.details.billingAddress
                : shippingAddress;

        const [firstname, ...lastname] = billingAddress.name.split(' ');

        return {
            'email': paymentDataModel.getEmail(),
            'paymentMethod': {
                'method': 'braintree_googlepay',
                'additional_data': {
                    'payment_method_nonce': payload.nonce
                }
            },
            'billing_address': {
                'telephone': typeof billingAddress.telephone !== 'undefined' ? billingAddress.telephone : '00000000000',
                'firstname': firstname,
                'lastname': lastname.join(' '),
                'street': billingAddress.streetAddress?.split('\n') || billingAddress.street,
                'city': billingAddress.locality,
                'region': billingAddress.region,
                'region_id': regionDataModel.getRegionIdByCode(billingAddress.countryCodeAlpha2, billingAddress.region),
                'region_code': null,
                'country_id': billingAddress.countryCodeAlpha2,
                'postcode': billingAddress.postalCode,
                'same_as_billing': 0,
                'customer_address_id': 0,
                'save_in_address_book': 0
            }
        };
    };
});
