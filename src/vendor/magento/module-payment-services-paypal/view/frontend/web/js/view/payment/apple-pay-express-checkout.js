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
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/view/payment/apple-pay-cart',
    'Magento_Ui/js/model/messageList'
], function (_, $t, Component, globalMessageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_apple_pay, 'express', {});

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalApplePay',
            sdkParamsKey: 'applepay',
            buttonContainerId: 'applepay-button-container',
            paymentActionError: $t('Something went wrong with your request. Please try again later.'),
            isErrorDisplayed: false,
            template: 'Magento_PaymentServicesPaypal/payment/apple-pay-express-checkout',
            authorizeOrderUrl: config.authorizeOrderUrl,
            placeOrderUrl: config.placeOrderUrl,
            isVirtual: !!window.checkoutConfig.quoteData.is_virtual,
            styles: window.checkoutConfig.payment.payment_services_paypal_apple_pay.buttonStyles,
            sort: config.sort,
            pageType: config.location,
            createOrderUrl: config.createOrderUrl,
            estimateShippingMethodsWhenLoggedInUrl: config.estimateShippingMethodsWhenLoggedInUrl,
            estimateShippingMethodsWhenGuestUrl: config.estimateShippingMethodsWhenGuestUrl,
            shippingInformationWhenLoggedInUrl: config.shippingInformationWhenLoggedInUrl,
            shippingInformationWhenGuestUrl: config.shippingInformationWhenGuestUrl,
            updatePayPalOrderUrl: config.updatePayPalOrderUrl,
            countriesUrl: config.countriesUrl,
            showPopup: config.showPopup
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            _.bindAll(this, 'initApplePayButton', 'cancelApplePay');

            this._super();

            return this;
        },

        isMethodActive: function () {
            return config.isVisible;
        },

        renderAfter: function () {
            this.getSdkParams()
                .then(this.initApplePayButton)
                .catch(console.log);

        },

        initApplePayButton: function () {
            if (document.getElementById(this.buttonContainerId)) {
                this._super();
            }
        },

        cancelApplePay: function () {
            this.applePayButton.showLoaderAsync(false);
        },

        /**
         * Catch errors.
         *
         * @param {*} error
         */
        catchError: function (error) {
            var message = error instanceof ResponseError ? error.message : this.requestProcessingError;

            this.showLoader(false);

            if (this.isErrorDisplayed) {
                return;
            }

            if (error.hidden === undefined || !error.hidden) {
                this.addMessage(message, 'error');
            }

            this.isErrorDisplayed = true;
        },

        /**
         * Render messages.
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
        }
    });
});
