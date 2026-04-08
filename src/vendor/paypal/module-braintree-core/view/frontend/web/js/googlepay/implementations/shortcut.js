/**
 * Braintree Google Pay mini cart payment method integration.
 **/
define(
    [
        'uiComponent',
        'jquery',
        'PayPal_Braintree/js/googlepay/button',
        'PayPal_Braintree/js/googlepay/api',
        'domReady!'
    ],
    function (
        Component,
        $,
        button,
        buttonApi
    ) {
        'use strict';

        return Component.extend({

            defaults: {
                id: null,
                clientToken: null,
                merchantId: null,
                currencyCode: null,
                actionSuccess: null,
                amount: null,
                environment: 'TEST',
                cardType: [],
                btnColor: 0,
                threeDSecure: null,
                quoteId: 0,
                storeCode: 'default',
                skipOrderReviewStep: false,
                priceFormat: [],
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();

                /* Add client token & environment to 3DS Config */
                this.threeDSecure.clientToken = this.clientToken;
                this.threeDSecure.environment = this.environment;

                const element = $(`#${this.id}`);
                let api = new buttonApi();

                api.setEnvironment(this.environment);
                api.setCurrencyCode(this.currencyCode);
                api.setClientToken(this.clientToken);
                api.setMerchantId(this.merchantId);
                api.setActionSuccess(this.actionSuccess);
                api.setAmount(this.amount);
                api.setCardTypes(this.cardTypes);
                api.setBtnColor(this.btnColor);
                api.setThreeDSecureValidatorConfig(this.threeDSecure);
                api.setStoreCode(this.storeCode);
                api.setQuoteId(this.quoteId);
                api.setSkipReview(this.skipOrderReviewStep);
                api.setPriceIncludesTax(this.priceIncludesTax);
                api.setElement(element);
                api.setPriceFormat(this.priceFormat);

                // Attach the button
                button.init(
                    element,
                    api
                );

                return this;
            }
        });
    }
);
