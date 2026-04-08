define([
    'uiComponent',
    'jquery',
    'Magento_Customer/js/customer-data',
    'PayPal_Braintree/js/helper/add-script'
], function (Component, $, customerData, addScript) {
    'use strict';

    return Component.extend({
        defaults: {
            code: 'paypal_braintree_messages',
            messageElement: ''
        },

        /**
         * Initialize message element
         *
         * @param config
         */
        initialize: function (config) {
            this._super(config);

            // Return early if we are running in the minicart.
            if (config.parentName === 'minicart_content.extra_info') {
                return;
            }

            this.messageElement = config.messageElement;

            this.renderMessage(config);

            if (config.placement === 'product') {
                // Attach a listener to the price update event so that we are always using the correct values.
                this.attachPriceUpdateSubscription(config);
            } else if (config.placement === 'cart') {
                this.attachCartUpdateSubscription(config);
            }
        },

        /**
         * Is cart active
         *
         * @returns {*|boolean}
         */
        hasCartContent: function () {
            const cart = customerData.get('cart')();

            return cart && cart.summary_count > 0;
        },

        /**
         * Because the PayPal SDK isn't loaded synchronously we may need to attach a listener to wait
         * until it's loaded.
         */
        attachPayLaterMessageSubscription: function (config, amount) {
            if (!this.getMessageConfig(config, amount)) {
                return;
            }

            const clientToken = config.clientToken || window.checkout.payPalBraintreeClientToken,
                currency = config.currency || window.checkout.paypalBraintreeCurrencyCode;

            return addScript(clientToken, currency, this.code, config.placement)
                .then(() => this.renderMessage(config, amount));
        },

        /**
         * On PDP the price can change on configurable so we need to listen for those changes
         * and re-render the button with the updated price.
         */
        attachPriceUpdateSubscription: function (config) {
            $(document).on('priceUpdated', (event, displayPrices) => {
                this.renderMessage(config, displayPrices.finalPrice.amount);
            });
        },

        /**
         * Cart update event
         *
         * @param config
         */
        attachCartUpdateSubscription: function (config) {
            const cartData = customerData.get('cart-data');

            cartData.subscribe(({totals}) => {
                if (totals) {
                    this.renderMessage(config, totals.grand_total);
                }
            });
        },

        /**
         * Get message config
         *
         * @returns {{amount: string, currency: string, style: *, placement: string}} | Boolean.
         */
        getMessageConfig: function (config, amount) {
            const configuration = config || window?.checkoutConfig?.payment?.braintree || {};

            if (!configuration.messageStyles) {
                return false;
            }

            return {
                amount: amount || configuration.amount,
                currency: configuration.currency,
                pageType: configuration.placement,
                style: configuration.messageStyles
            };
        },

        /**
         * Render the message using the SDK provided message.
         */
        renderMessage: function (config, amount) {
            // Check that the messages component is available before calling it.
            if (window[`paypal_${this.code}`]?.Messages) {
                const messageConfig = this.getMessageConfig(config, amount);
                let message;

                if (!messageConfig) {
                    return;
                }

                message = window[`paypal_${this.code}`].Messages(messageConfig);

                message.render(this.messageElement);
            } else {
                // Otherwise attach a wait for the component to load.
                this.attachPayLaterMessageSubscription(config, amount);
            }
        }
    });
});
