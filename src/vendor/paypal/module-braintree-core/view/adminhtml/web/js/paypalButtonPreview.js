/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'underscore',
    'jquery',
    'braintree',
    'braintreePayPalCheckout',
    'domReady!'
], function (_, $, braintree, paypalCheckout) {
    'use strict';

    return {
        events: {
            onClick: null
        },

        /**
         * Initialize button
         *
         * @param buttonConfig
         */
        init: function (buttonConfig) {
            const buttonIds = [];

            buttonConfig.buttonIds.forEach(function (buttonId) {
                if (!$(`#${buttonId}`).hasClass('button-loaded')) {
                    $(`#${buttonId}`).addClass('button-loaded');
                    buttonIds.push(buttonId);
                }
            });

            if (buttonIds.length > 0) {
                this.loadSDK(buttonConfig, buttonIds);
            }

            if (!buttonConfig.showPayLaterMessaging) {
                $('.payment-location').on('change', () => {
                    window.hidePaypalSections();
                });
            }
        },

        /**
         * Load Braintree PayPal SDK
         * @param buttonConfig
         * @param buttonIds
         */
        loadSDK: function (buttonConfig, buttonIds) {
            braintree.create({
                authorization: buttonConfig.clientToken
            }, function (clientErr, clientInstance) {
                if (clientErr) {
                    console.error('paypalCheckout error', clientErr);
                    return this.showError('PayPal Checkout could not be initialized. Please contact the store owner.');
                }
                paypalCheckout.create({
                    client: clientInstance
                }, function (err, paypalCheckoutInstance) {
                    if (typeof paypal !== 'undefined') {
                        this.renderPayPalButtons(buttonIds, buttonConfig);
                    } else {
                        let configSDK = {
                                components: 'buttons,messages,funding-eligibility',
                                'enable-funding': this.isCreditActive(buttonConfig) ? 'credit' : 'paylater',
                                currency: buttonConfig.currency,
                                dataAttributes: {
                                    'page-type': 'checkout'
                                }
                            },

                            buyerCountry = this.getMerchantCountry(buttonConfig);

                        if (buttonConfig.environment === 'sandbox'
                            && (buyerCountry !== '' || buyerCountry !== 'undefined'))
                        {
                            configSDK['buyer-country'] = buyerCountry;
                        }
                        paypalCheckoutInstance.loadPayPalSDK(configSDK, function () {
                            this.renderPayPalButtons(buttonIds, buttonConfig);
                        }.bind(this));
                    }

                    this.attachListeners(buttonIds, buttonConfig);
                }.bind(this));
            }.bind(this));
        },

        /**
         * Is Credit enabled
         *
         * @param buttonConfig
         * @returns {boolean}
         */
        isCreditActive: function (buttonConfig) {
            return buttonConfig.isCreditActive;
        },

        /**
         * Get merchant country
         *
         * @param buttonConfig
         * @returns {string}
         */
        getMerchantCountry: function (buttonConfig) {
            return buttonConfig.merchantCountry;
        },

        /**
         * Render PayPal buttons
         * @param ids
         * @param buttonConfig
         */
        renderPayPalButtons: function (ids, buttonConfig) {
            _.each(ids, function (id) {
                this.payPalButton(id, buttonConfig);
            }.bind(this));
        },

        /**
         * Render PayPal button
         *
         * @param id
         * @param buttonConfig
         */
        payPalButton: function (id, buttonConfig) {
            let buttonElement = $('#' + id),
                funding = buttonElement.data('funding'),
                paymentLocation = buttonConfig.showPayLaterMessaging ? 'checkout' : $('.payment-location').val(),
                style = this.getStyles(paymentLocation, funding, buttonElement.data('fundingicons')),
                button,
                properties = {
                    fundingSource: funding,
                    style: style,

                    onInit: function (data, actions) {
                        actions.disable();
                    }
                };

            const activeSelected = funding === 'paypal'
                ? 'select[id$="braintree_braintree_paypal_active_braintree_paypal"]'
                : `select[id$="braintree_braintree_paypal_braintree_paypal_${funding}_active"]`;

            // Remove if config is set to not display.
            if ($(activeSelected).val() !== '1'
                || $(`tr[id$="button_location_${paymentLocation}_type_${funding}_show"] select`).val() !== '1') {
                buttonElement.empty();
                return;
            }

            if (funding === 'paylater' && buttonConfig.showPayLaterMessaging) {
                properties.message = this.getMessageStyles();
            }

            button = window.paypal.Buttons(properties);

            if (!button.isEligible()) {
                console.log('PayPal button is not eligible');
                buttonElement.empty();
                return;
            }
            if ($('#' + buttonElement.attr('id')).length) {
                buttonElement.empty();
                button.render('#' + buttonElement.attr('id'));
            }
        },

        /**
         * Get styles
         *
         * @param location
         * @param funding
         * @param fundingIcons
         * @returns {{color: (*|jQuery), shape: (*|jQuery), fundingicons: string, label: (*|jQuery)}}
         */
        getStyles: function (location, funding, fundingIcons) {
            return {
                label: $('.' + location + '-' + funding + '-label').val(),
                color: $('.' + location + '-' + funding + '-color').val(),
                shape: $('.' + location + '-' + funding + '-shape').val(),
                fundingicons: fundingIcons || ''
            };
        },

        /**
         * Get message styles
         *
         * @returns {{amount: number, color: (string|*|jQuery), align: (*|jQuery)}}
         */
        getMessageStyles: function () {
            /* eslint-disable max-len */
            const align = $('select[id$="section_braintree_braintree_paylater_messaging_button_location_checkout_type_messaging_button_location_checkout_type_messaging_text_align"]').val(),
                amount = 200,
                color = $('select[id$="section_braintree_braintree_paylater_messaging_button_location_checkout_type_messaging_button_location_checkout_type_messaging_text_color"]').val();

            return {
                align,
                amount,
                // Button doesn't support monochrome or greyscale so in either of these cases return black.
                color: color !== 'black' || color !== 'white' ? 'black' : color
            };
        },

        /**
         * Attache listeners
         *
         * @param buttonsIds
         * @param buttonConfig
         */
        attachListeners: function (buttonsIds, buttonConfig) {
            $(`
                select[id$="braintree_braintree_paypal_active_braintree_paypal"],
                select[id$="braintree_braintree_paypal_braintree_paypal_credit_active"],
                select[id$="braintree_braintree_paypal_braintree_paypal_paylater_active"],
                [id$="braintree_section_braintree_braintree_paypal_styling"] select,
                [id$="braintree_section_braintree_braintree_paylater_messaging_button_location_checkout_type_messaging"] select
            `).on('change', () => {
                this.renderPayPalButtons(buttonsIds, buttonConfig);
            });
        }
    };
});
