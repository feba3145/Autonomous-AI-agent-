/**
 * Braintree Apple Pay express payment method integration.
 **/
define([
    'underscore',
    'uiComponent',
    'PayPal_Braintree/js/applepay/button',
    'PayPal_Braintree/js/applepay/api',
    'PayPal_Braintree/js/helper/format-amount',
    'mage/translate',
    'mage/url',
    'domReady!'
], function (
    _,
    Component,
    button,
    buttonApi,
    formatAmount,
    $t,
    url
) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment, 'braintree_applepay', {});

    return Component.extend({

        defaults: {
            template: 'PayPal_Braintree/express/express-applepay',
            id: 'braintree-applepay-express-payment',
            isActive: !_.isEmpty(config),
            clientToken: _.get(config, 'clientToken', null),
            quoteId: window.checkoutConfig.quoteData.entity_id,
            displayName: _.get(config, 'merchantName', null),
            actionSuccess: url.build('checkout/onepage/success'),
            grandTotalAmount: window.checkoutConfig.quoteData.base_grand_total,
            storeCode: window.checkoutConfig.storeCode,
            priceIncludesTax: _.get(config, 'priceIncludesTax', true)
        },

        /**
         * Is the payment method active.
         *
         * @return {boolean}
         */
        isMethodActive: function () {
            return this.isActive;
        },

        /**
         * Initialize Apple Pay express.
         */
        initApplePayExpress: function () {
            if (!this.isMethodActive() || !this.clientToken) {
                return;
            }

            if (!this.displayName) {
                this.displayName = $t('Store');
            }

            let api = new buttonApi();

            api.setGrandTotalAmount(formatAmount(this.grandTotalAmount));
            api.setClientToken(this.clientToken);
            api.setDisplayName(this.displayName);
            api.setQuoteId(this.quoteId);
            api.setActionSuccess(this.actionSuccess);
            api.setStoreCode(this.storeCode);
            api.setPriceIncludesTax(this.priceIncludesTax);

            // Attach the button
            button.init(
                document.getElementById(this.id),
                api
            );
        },

        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();

            return this;
        }
    });
});
