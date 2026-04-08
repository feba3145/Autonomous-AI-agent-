define(['PayPal_Braintree/js/model/region-data'], function (regionDataModel) {
    'use strict';

    return function (payload, shippingMethod) {
        let address = payload.details.shippingAddress,
            recipientFirstName,
            recipientLastName;

        // get recipient first and last name
        if (typeof address.recipientName !== 'undefined') {
            let recipientName = address.recipientName.split(' ');
            recipientFirstName = recipientName[0].replace(/'/g, '&apos;');
            recipientLastName = recipientName[1].replace(/'/g, '&apos;');
        } else {
            recipientFirstName = payload.details.firstName.replace(/'/g, '&apos;');
            recipientLastName = payload.details.lastName.replace(/'/g, '&apos;');
        }

        return {
            addressInformation: {
                'shipping_method_code': shippingMethod.method_code,
                'shipping_carrier_code': shippingMethod.carrier_code,
                'shipping_address': {
                    'email': payload.details.email.replace(/'/g, '&apos;'),
                    'telephone': typeof payload.details.phone !== 'undefined' ? payload.details.phone : '00000000000',
                    'firstname': recipientFirstName,
                    'lastname': recipientLastName,
                    'street': typeof address.line2 !== 'undefined' ? [address.line1.replace(/'/g, '&apos;'), address.line2.replace(/'/g, '&apos;')] : [address.line1.replace(/'/g, '&apos;')],
                    'city': address.city.replace(/'/g, '&apos;'),
                    'region': address?.state?.replace(/'/g, '&apos;') || '',
                    'region_id': regionDataModel.getRegionIdByCode(address.countryCode, address?.state?.replace(/'/g, '&apos;') || ''),
                    'region_code': null,
                    'country_id': address.countryCode,
                    'postcode': address.postalCode,
                    'same_as_billing': 0,
                    'customer_address_id': 0,
                    'save_in_address_book': 0
                }
            }
        };
    };
});
