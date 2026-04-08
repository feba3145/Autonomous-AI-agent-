define([
    'jquery',
    'underscore',
    'mage/url',
    'PayPal_Braintree/js/form-builder',
    'PayPal_Braintree/js/helper/remove-non-digit-characters',
    'PayPal_Braintree/js/helper/replace-single-quote-character'
], function (
    $,
    _,
    url,
    formBuilder,
    removeNonDigitCharacters,
    replaceSingleQuoteCharacter
) {
    'use strict';

    return function (payload, currentElement, type) {
        $('#maincontent').trigger('processStart');

        /* Set variables & default values for shipping/recipient name to billing */
        let accountFirstName = replaceSingleQuoteCharacter(payload.details.firstName),
            accountLastName = replaceSingleQuoteCharacter(payload.details.lastName),
            accountEmail = replaceSingleQuoteCharacter(payload.details.email),
            actionSuccess = url.build(`braintree/${type}/review`);

        payload.details.email = accountEmail;
        payload.details.firstName = accountFirstName;
        payload.details.lastName = accountLastName;
        if (typeof payload.details.businessName !== 'undefined'
                && _.isString(payload.details.businessName)) {
            payload.details.businessName
                    = replaceSingleQuoteCharacter(payload.details.businessName);
        }

        if (currentElement.data('location') === 'productpage') {
            let form = $('#product_addtocart_form');

            payload.additionalData = form.serialize();
        }

        formBuilder.build(
            {
                action: actionSuccess,
                fields: {
                    result: JSON.stringify(payload)
                }
            }
        ).submit();
    }
});
