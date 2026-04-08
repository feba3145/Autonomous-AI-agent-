define([
    'PayPal_Braintree/js/messages/paylater',
    'Magento_Customer/js/customer-data'
], function (Component, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            messageElement: '#paypal-braintree-mini-cart-paylater-container-message',
            template: 'PayPal_Braintree/messages/mini-cart'
        },

        /**
         * Initialize pay later messaging
         *
         * @param config
         */
        initialize: function (config) {
            config.currency = window.checkout?.paypalBraintreeCurrencyCode;
            config.messageStyles = window.checkout?.payPalBraintreePaylaterMessageConfig;

            this._super(config);

            this.attachCartUpdateSubscription(config);
        },

        /**
         * Get amount
         *
         * @returns {*}
         */
        getAmount: function () {
            const cart = customerData.get('cart')();

            return cart.subtotalAmount;
        },

        /**
         * Cart update event (subscription)
         *
         * @param config
         */
        attachCartUpdateSubscription: function (config) {
            const cart = customerData.get('cart');

            cart.subscribe(({subtotalAmount}) => {
                if (subtotalAmount) {
                    this.renderMessage(config, subtotalAmount);
                }
            });
        },

        /**
         * Render pay later message
         */
        renderMessage: function () {
            const element = document.querySelector(this.messageElement),
                amount = this.getAmount();

            if (!this.hasCartContent() || !element) {
                return;
            }

            // Check that the messages component is available before calling it.
            if (window[`paypal_${this.code}`]?.Messages) {
                const messageConfig = this.getMessageConfig(this, amount);
                let message;

                if (!messageConfig) {
                    return;
                }

                message = window[`paypal_${this.code}`].Messages(messageConfig);

                message.render(this.messageElement);
            } else {
                // Otherwise attach a wait for the component to load.
                this.attachPayLaterMessageSubscription(this, amount);
            }
        }
    });
});
