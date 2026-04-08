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
define([
    'knockout',
    'mageUtils',
    'uiLayout',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'scriptLoader'
], function (ko, utils, layout, Component, quote, loadSdkScript) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Magento_PaymentServicesPaypal/payment/apm',
            code: 'payment_services_paypal_apm',
            orderId: null,
            isEligible: ko.observable(false),
            allowedMethods: ko.observableArray([]),
            paymentsSdkInitPromise: null,
            selectedApm: ko.observable(null),
            sdkNamespace: 'paypalApm'
        },

        initialize: function () {
            this._super();

            const allowedMethods = this.getAllowedMethods();

            this.allowedMethods(allowedMethods);

            if (allowedMethods.length) {
                this.loadPayPalSDK()
                    .then(() => {
                        this.allowedMethods().forEach((method) => {
                            layout([this.createComponent(method)]);
                        });
                    });
            }
        },

        loadPayPalSDK: function () {
            return loadSdkScript(window.checkoutConfig.payment[this.getCode()].sdkParams, this.sdkNamespace)
                .catch(function (e) {
                    this.onError(e);
                });
        },

        getAllowedMethods: function () {
            return window.checkoutConfig?.payment?.[this.code]?.allowedMethods?.split(',') || [];
        },

        createComponent: function (method) {
            const templateData = {
                    parentName: this.name,
                    name: method
                },
                rendererTemplate = {
                    parent: '${ $.$data.parentName }',
                    name: '${ $.$data.name }',
                    displayArea: 'payment_services_paypal_apm_methods',
                    component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/apm-method'
                },
                rendererComponent = utils.template(rendererTemplate, templateData);

            utils.extend(rendererComponent, {
                config: {
                    item: this.item,
                    method
                }
            });

            return rendererComponent;
        }
    });
});
