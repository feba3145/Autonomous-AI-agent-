define([
    'jquery',
    'underscore',
    'mage/storage',
    'mage/translate',
    'PayPal_Braintree/js/helper/get-api-url'
], function (
    $,
    _,
    storage,
    $t,
    getApiUrl
) {
    'use strict';

    return function (payload, storeCode, quoteId) {
        $('body').trigger('processStart');

        if (window.checkout || window.checkoutConfig) {
            let agreementIds = [];
            if (window?.checkout?.agreementIds) {
                agreementIds = window.checkout.agreementIds;
            }
            if (window?.checkoutConfig?.checkoutAgreements
                && window?.checkoutConfig?.checkoutAgreements?.isEnabled) {
                let agreements = window.checkoutConfig.checkoutAgreements.agreements;
                _.each(agreements, function (item) {
                    agreementIds.push(item.agreementId);
                });
            }
            if (agreementIds.length) {
                payload.paymentMethod.extension_attributes = {
                    'agreement_ids': agreementIds
                };
            }
        }
        return storage.post(
            getApiUrl('payment-information', storeCode, quoteId),
            JSON.stringify(payload)
        ).catch(function (r) {
            console.error('Braintree PayPal Unable to take payment', r);
            throw new Error($t('We\'re unable to take payment through PayPal. Please try with different payment method.'));
        }).always(function () {
            $('body').trigger('processStop');
        });
    }
});
