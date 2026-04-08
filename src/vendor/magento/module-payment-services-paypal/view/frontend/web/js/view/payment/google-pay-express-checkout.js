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
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/view/payment/google-pay-cart',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay',
    'Magento_Ui/js/model/messageList'
], function (_, $t, ResponseError, Component, GooglePayButton, globalMessageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_google_pay, 'express', {});

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalGooglePay',
            sdkParamsKey: 'googlepay',
            buttonContainerId: 'paypal-express-container-google-pay',
            isErrorDisplayed: false,
            template: 'Magento_PaymentServicesPaypal/payment/google-pay-express-checkout',
            styles: window.checkoutConfig.payment.payment_services_paypal_google_pay.styles,
            createOrderUrl: config.createOrderUrl,
            authorizeOrderUrl: config.authorizeOrderUrl,
            getOrderDetailsUrl: config.getOrderDetailsUrl,
            isVirtual: !!window.checkoutConfig.quoteData.is_virtual,
            sort: config.sort,
            pageType: config.location
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            _.bindAll(this, 'initGooglePayButton', 'onClick',
                'afterOnAuthorize', 'onCancel');
            this._super();

            return this;
        },

        isMethodActive: function () {
            return config.isVisible;
        },

        renderAfter: function () {
            this.getSdkParams()
                .then(this.initGooglePayButton)
                .catch(console.log);
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
        },

        /**
         * onCancel callback.
         */
        onCancel: function () {
            this.googlePayButton.showLoader(false);
        }
    });
});
