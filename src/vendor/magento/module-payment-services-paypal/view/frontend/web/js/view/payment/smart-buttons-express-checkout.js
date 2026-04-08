/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

/* eslint-disable no-undef */
define([
    'underscore',
    'jquery',
    'scriptLoader',
    'Magento_PaymentServicesPaypal/js/helpers/remove-paypal-url-token',
    'Magento_PaymentServicesPaypal/js/view/payment/smart-buttons-cart',
    'Magento_PaymentServicesPaypal/js/view/payment/message',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/model/messageList'
], function (_, $, loadSdkScript, removePayPalUrlToken, Component, Message,
    $t, customerData, globalMessageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_smart_buttons, 'express', {});

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalCart',
            buttonsContainerId: 'paypal-express-container-paypal',
            element: null,
            paymentActionError: $t('Something went wrong with your request. Please try again later.'),
            isErrorDisplayed: false,
            template: 'Magento_PaymentServicesPaypal/payment/smart-buttons-express-checkout',
            createOrderUrl: config.createOrderUrl,
            authorizeOrderUrl: config.authorizeOrderUrl,
            getOrderDetailsUrl: config.getOrderDetailsUrl,
            completeOrderUrl: config.completeOrderUrl,
            isVirtual: !!window.checkoutConfig.quoteData.is_virtual,
            sort: config.sort,
            styles: window.checkoutConfig.payment.payment_services_paypal_smart_buttons.buttonStyles || {},
            appSwitchWhenAvailable: window.checkoutConfig.payment.payment_services_paypal_smart_buttons.appSwitchWhenAvailable,
            pageType: config.location,
            sdkParams: config.sdkParams || {}
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            _.bindAll(this, 'renderButtons', 'onError', 'beforeCreateOrder', 'afterCreateOrder',
                'beforeOnAuthorize', 'onCancel');
            this._super();

            return this;
        },

        /**
         * Is method active
         *
         * @returns {*}
         */
        isMethodActive: function () {
            return config.isVisible;
        },

        /**
         * Render after
         */
        renderAfter: function () {
            this.sdkLoaded = loadSdkScript(this.sdkParams, this.sdkNamespace)
                .then((sdkScript) => {
                    this.paypal = sdkScript;
                    this.renderButtons();
                    this.initMessage();
                });
        },

        /**
         * Initialize message component
         */
        initMessage: function () {
            if (!this.sdkLoaded) {
                return;
            }

            this.sdkLoaded.then(function () {
                var cartData = customerData.get('cart');

                this.message = new Message({
                    scriptParams: this.sdkParams,
                    element: this.element,
                    renderContainer: '#paypal-express-paypal-message',
                    styles: window.checkoutConfig.payment.payment_services_paypal_smart_buttons.messageStyles,
                    placement: 'payment',
                    amount: cartData().subtotalAmount
                });

                this.message.render();

                cartData.subscribe(function (updatedCart) {
                    this.message.updateAmount(updatedCart.subtotalAmount);
                }, this);
            }.bind(this)).catch(function () {
                console.log('Error: Failed to load PayPal SDK script!');
            });
        },

        /**
         * Catch payment authorization errors.
         */
        catchOnAuthorize: function (error) {
            this.onError(error);
        },

        /**
         * Add message to customer data.
         *
         * @param {String} message
         * @param {String} [type]
         */
        addMessage: function (message, type) {
            type = type || 'error';
            const messageFunction = type === 'error' ? 'addErrorMessage' : 'addSuccessMessage';

            globalMessageList[messageFunction]({
                message
            });
        },

        /**
         * onCancel callback.
         */
        onCancel: function () {
            removePayPalUrlToken();

            this.showLoader(false);
        }
    });
});
