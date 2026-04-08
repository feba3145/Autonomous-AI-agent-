/**
 * Express Paypal button component
 */

define([
    'underscore',
    'uiComponent',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'PayPal_Braintree/js/paypal/button',
    'domReady!'
], function (_, Component, url, quote, payPalButton) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment, 'braintree_paypal', {});

    return Component.extend({
        defaults: {
            template: 'PayPal_Braintree/express/express-paypal',
            isActive: _.get(config, 'isActive', false),
            clientToken: _.get(config, 'clientToken', null),
            checkoutCurrency: window.checkoutConfig.quoteData.base_currency_code,
            checkoutAmount: window.checkoutConfig.quoteData.base_grand_total,
            checkoutLocale: _.get(config, 'locale', null),
            buttonLabel: _.get(config, ['style', 'label'], null),
            buttonColor: _.get(config, ['style', 'color'], null),
            buttonShape: _.get(config, ['style', 'shape'], null),
            skipOrderReviewStep: _.get(config, 'skipOrderReviewStep', true),
            actionSuccess: _.get(config, 'skipOrderReviewStep', true)
                ? url.build('checkout/onepage/success')
                : url.build('braintree/paypal/review'),
            storeCode: window.checkoutConfig.storeCode,
            quoteId: window.checkoutConfig.quoteData.entity_id
        },

        /**
         * Initializes regular properties of instance.
         *
         * @returns {Object} Chainable.
         */
        initConfig: function () {
            this._super();

            return this;
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
         * Is Billing address required.
         *
         * @return {string}
         */
        getIsRequiredBillingAddress: function () {
            return _.get(config, 'isRequiredBillingAddress', '0') === '0' ? '' : 'true';
        },

        /**
         * Is Customer LoggedIn.
         *
         * @return {string}
         */
        getIsCustomerLoggedIn: function () {
            return _.get(window.checkoutConfig, 'isCustomerLoggedIn', false) === false ? '' : true;
        },

        /**
         * Get the merchant's name config.
         *
         * @return {string}
         */
        getMerchantName: function () {
            return _.get(config, 'merchantName', '');
        },

        /**
         * Initialize Braintree PayPal buttons.
         *
         * PayPal Credit & PayPal Pay Later & PayPal Pay Later Messaging rely on PayPal to be enabled.
         */
        initPayPalButtons: function () {
            if (!this.isMethodActive() || !this.clientToken) {
                return;
            }

            let buttonConfig = {
                    'buttonConfig': {
                        'clientToken': this.clientToken,
                        'currency': this.checkoutCurrency,
                        'environment': config.environment,
                        'merchantCountry': config.merchantCountry,
                        'isCreditActive': _.get(
                            window.checkoutConfig.payment,
                            ['braintree_paypal_credit', 'isActive'],
                            false
                        ),
                        'skipOrderReviewStep': this.skipOrderReviewStep,
                        'pageType': 'checkout'
                    },
                    'buttonIds': [
                        '#paypal-braintree-express-payment',
                        '#paypal-braintree-express-credit-payment',
                        '#paypal-braintree-express-paylater'
                    ]
                };

            payPalButton(buttonConfig);
        }
    });
});
