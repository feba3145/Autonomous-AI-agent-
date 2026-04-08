define(['PayPal_Braintree/js/model/region-data'], function (regionDataModel) {
    'use strict';

    return function (payload, isRequiredBillingAddress) {
        const billingAddress = isRequiredBillingAddress && payload.details?.billingAddress?.line1
                ? payload.details.billingAddress
                : payload.details.shippingAddress;

        let recipientFirstName,
            recipientLastName;

        // get recipient first and last name
        if (typeof billingAddress.recipientName !== 'undefined') {
            let recipientName = billingAddress.recipientName.split(' ');
            recipientFirstName = recipientName[0].replace(/'/g, '&apos;');
            recipientLastName = recipientName[1].replace(/'/g, '&apos;');
        } else {
            recipientFirstName = payload.details.firstName.replace(/'/g, '&apos;');
            recipientLastName = payload.details.lastName.replace(/'/g, '&apos;');
        }

        return {
            'email': payload.details.email.replace(/'/g, '&apos;'),
            'paymentMethod': {
                'method': 'braintree_paypal',
                'additional_data': {
                    'payment_method_nonce': payload.nonce
                }
            },
            'billing_address': {
                'email': payload.details.email.replace(/'/g, '&apos;'),
                'telephone': typeof payload.details.phone !== 'undefined' ? payload.details.phone : '00000000000',
                'firstname': recipientFirstName,
                'lastname': recipientLastName,
                'street': typeof billingAddress.line2 !== 'undefined' ? [billingAddress.line1.replace(/'/g, '&apos;'), billingAddress.line2.replace(/'/g, '&apos;')] : [billingAddress.line1.replace(/'/g, '&apos;')],
                'city': billingAddress.city.replace(/'/g, '&apos;'),
                'region': billingAddress?.state?.replace(/'/g, '&apos;') || '',
                'region_id': regionDataModel.getRegionIdByCode(billingAddress.countryCode, billingAddress?.state?.replace(/'/g, '&apos;') || ''),
                'region_code': null,
                'country_id': billingAddress.countryCode,
                'postcode': billingAddress.postalCode,
                'same_as_billing': 0,
                'customer_address_id': 0,
                'save_in_address_book': 0
            }
        };
    };
});
