define([
    'jquery',
    'uiComponent',
    'PayPal_Braintree/js/helper/add-script'
], function ($, Component, addScript) {
    'use strict';

    return Component.extend({
        defaults: {
            code: 'paypal_braintree_messages'
        },

        initialize: function (config) {
            this._super(config);

            addScript(config.clientToken, config.currency, this.code)
                .then(() => this.renderMessage(config))
                .then(() => this.attachListeners(config));

            this.updateMessageRender(config);
        },

        /**
         * Get message config
         *
         * @returns {{amount: string, currency: string, style: *, placement: string}} | Boolean.
         */
        getMessageConfig: function (config) {
            const style = this.getMessageStyles(config);

            return {
                amount: config.amount,
                currency: config.currency,
                style
            };
        },

        /**
         * Render the message using the SDK provided message.
         */
        renderMessage: function (config) {
            // Clear the existing message if it exists.
            const container = $(`#${config.messageId}-container`).empty();

            // Check that the messages component is available before calling it.
            if (window[`paypal_${this.code}`]?.Messages && this.isMessageEnabled(config)) {
                const messageConfig = this.getMessageConfig(config),
                    message = window[`paypal_${this.code}`].Messages(messageConfig);

                container.append(`<div id="${config.messageId}-message"></div>`);

                message.render(`#${config.messageId}-message`);
            }
        },

        isMessageEnabled: function (config) {
            return $(`#${config.messageId}-container`)
                .closest('.form-list').find('select[id$="messaging_show"]').val() === '1';
        },

        /**
         * Get the styles from the selected options.
         *
         * @param {Object} config
         */
        getMessageStyles: function (config) {
            const parentElement = $(`#${config.messageId}-container`).closest('.form-list');

            if (!parentElement.length) {
                return null;
            }

            return {
                layout: parentElement.find('select[id$="messaging_layout"]').val(),
                logo: {
                    type: parentElement.find('select[id$="messaging_logo"]').val(),
                    position: parentElement.find('select[id$="messaging_logo_position"]').val()
                },
                text: {
                    color: parentElement.find('select[id$="messaging_text_color"]').val()
                }
            };
        },

        attachListeners: function (config) {
            const parentElement = $(`#${config.messageId}-container`).closest('.form-list');

            parentElement.find('select').on('change', () => this.renderMessage(config));
        },

        updateMessageRender: function (config) {
            const adminField = $(`#${config.messageId}-container`).closest('tr');

            if (adminField.length) {
                adminField.find('.label').addClass('hidden');

                adminField.find('.value').attr('colspan', 2);
            }
        }
    });
});
