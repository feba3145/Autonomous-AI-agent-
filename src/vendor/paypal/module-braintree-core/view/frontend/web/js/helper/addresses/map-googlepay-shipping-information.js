define([
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/model/region-data',
], function (paymentDataModel, regionDataModel) {
    'use strict';

    return function (payload, shippingMethod) {
        let address = payload.details.shippingAddress;

        const [firstname, ...lastname] = address.name.split(' ');

        return {
            addressInformation: {
                'shipping_method_code': shippingMethod.method_code,
                'shipping_carrier_code': shippingMethod.carrier_code,
                'shipping_address': {
                    'email': paymentDataModel.getEmail(),
                    'telephone': typeof address.telephone !== 'undefined' ? address.telephone : '00000000000',
                    'firstname': firstname,
                    'lastname': lastname.join(' '),
                    'street': address.streetAddress.split('\n'),
                    'city': address.locality,
                    'region': address.region,
                    'region_id': regionDataModel.getRegionIdByCode(address.countryCodeAlpha2, address.region),
                    'region_code': null,
                    'country_id': address.countryCodeAlpha2,
                    'postcode': address.postalCode,
                    'same_as_billing': 0,
                    'customer_address_id': 0,
                    'save_in_address_book': 0
                }
            }
        };
    };
});
