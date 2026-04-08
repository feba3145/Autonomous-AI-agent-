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
    'jquery',
    'knockout',
    'matchMedia',
    'uiLayout',
    'uiRegistry',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList'
], function (
    $, ko, mediaCheck, layout, registry, $t, Component, selectPaymentMethodAction, setBillingAddressAction,
    checkoutData, loader, additionalValidators, quote, globalMessageList
) {
    'use strict';

    const HTTP_STATUS_CREATED = 201;

    /**
     * Create order request.
     *
     * @param {String} url
     * @param {Object} payPalOrderData
     * @param {FormData} orderData
     * @return {Object}
     */
    var performCreateOrder = function (url, payPalOrderData, orderData) {
        orderData = orderData || new FormData();
        orderData.append('form_key', $.mage.cookies.get('form_key'));
        orderData.append('payment_source', payPalOrderData['paymentSource']);

        let xhr = new XMLHttpRequest();
        xhr.open('POST', url, false);
        xhr.send(orderData);

        if (xhr.status !== HTTP_STATUS_CREATED) {
            throw new Error('Request failed');
        } else {
            return JSON.parse(xhr.responseText);
        }
    };

    return Component.extend({
        defaults: {
            active: ko.observable(false),
            code: 'payment_services_paypal_apm',
            method: null,
            orderId: null,
            paymentsSdk: null,
            paymentSource: 'apm',
            requestProcessingError: $t('Error happened when processing the request. Please try again later.'),
            template: 'Magento_PaymentServicesPaypal/payment/apm-method',
            createOrderUrl: window.checkoutConfig.payment.payment_services_paypal_apm.createOrderUrl,
            location: window.checkoutConfig.payment.payment_services_paypal_apm.location
        },

        initialize: function (config) {
            this._super(config);

            const parentApm = registry.get(config.parentName),
                parentBilling = registry.get(parentApm.parentName);

            this.parentApm = parentApm;
            this.parentBilling = parentBilling;
            this.isEligible = ko.observable(false);

            this.selectedApmCode = ko.computed(function () {
                return quote.paymentMethod()
                    ? `${quote.paymentMethod().method}_${parentApm?.selectedApm()}`
                    : null;
            });

            additionalValidators.registerValidator({
                validate: () => {
                    return true;
                }
            });

            this.renderApm();
        },

        /**
         * Get Payment method code
         *
         * @returns {*}
         */
        getCode: function () {
            return this.code;
        },

        /**
         * Render APMs
         */
        renderApm: function () {
            this.createElements();
            this.checkEligibility();
        },

        /**
         * Create elements
         */
        createElements: function () {
            this.mark = window[this.parentApm.sdkNamespace].Marks({
                fundingSource: window[this.parentApm.sdkNamespace].FUNDING[this.method.toUpperCase()]
            });

            this.paymentFields = window[this.parentApm.sdkNamespace].PaymentFields({
                fundingSource: window[this.parentApm.sdkNamespace].FUNDING[this.method.toUpperCase()]
            });

            this.paymentButtons = window[this.parentApm.sdkNamespace].Buttons({
                fundingSource: window[this.parentApm.sdkNamespace].FUNDING[this.method.toUpperCase()],
                createOrder: this.createOrder.bind(this),
                onApprove: () => {
                    this.placeOrder();
                },
                onClick: this.onClick.bind(this),
                onError: this.catchError.bind(this),
                onCancel: this.onCancel.bind(this)
            });
        },

        /**
         * Render payment marks
         *
         * @param element
         */
        renderPaymentMark: function (element) {
            if (this.mark) {
                const id = `paypal_${this.method}_mark`;

                $(element).attr('id', id);
                this.mark.render(`#${id}`);
            }
        },

        /**
         * Check APMs eligibility
         */
        checkEligibility: function () {
            if (!this.paymentButtons || !this.paymentButtons.isEligible()) {
                return;
            }

            const grandTotal = quote.totals()['base_grand_total'];

            quote.totals.subscribe((totalsUpdate) => {
                this.isEligible(this.isGrandTotalWithinThreshold(totalsUpdate.base_grand_total));
            });

            this.isEligible(this.isGrandTotalWithinThreshold(grandTotal));
        },

        /**
         * Checks the grand total of the quote against the thresolds set for the APM.
         *
         * @param {float} grandTotal
         * @returns
         */
        isGrandTotalWithinThreshold: function (grandTotal) {
            const thresholds = window.checkoutConfig.payment.payment_services_paypal_apm.thresholds[this.method];

            if (Object.hasOwn(thresholds, 'min') && grandTotal < thresholds.min) {
                return false;
            }

            if (Object.hasOwn(thresholds, 'max') && grandTotal > thresholds.max) {
                return false;
            }

            return true;
        },

        /**
         * Run a validation of the form before opening APM modals.
         *
         * @returns bool
         */
        onClick: function () {
            return additionalValidators.validate();
        },

        /**
         * Create order.
         *
         * @return {String}
         */
        createOrder: async function () {
            let data = {'paymentSource': this.method};

            try {
                let formData = new FormData();
                formData.append('location', this.location);

                await this.beforeCreateOrder();
                let orderData = performCreateOrder(this.createOrderUrl, data, formData);
                this.paypalOrderId = this.afterCreateOrder(orderData);
                return this.paypalOrderId;
            } catch (error) {
                return this.catchCreateOrder(error);
            }
        },

        /**
         * Before order created.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            return setBillingAddressAction(globalMessageList)
                .fail(function () {
                    throw {message: $t('Failed to set billing address')};
                });
        },

        /**
         * After order created.
         *
         * @param {Object} data
         * @return {String}
         */
        afterCreateOrder: function (data) {
            if (data.response['paypal-order'] && data.response['paypal-order']['mp_order_id']) {
                this.paymentsOrderId = data.response['paypal-order']['mp_order_id'];
                this.paypalOrderId = data.response['paypal-order'].id;

                return this.paypalOrderId;
            }

            throw new Error();
        },

        /**
         * Catch error on order creation.
         */
        catchCreateOrder: function (error) {
            this.catchError(error);
        },

        /**
         * Get APM payment method titles
         *
         * @returns {*}
         */
        getTitle: function () {
            if (window.checkoutConfig?.payment?.[this.getCode()]?.titles?.[this.method]) {
                return window.checkoutConfig?.payment?.[this.getCode()]?.titles?.[this.method];
            }

            return this.method;
        },

        /**
         * Get payment method code.
         */
        getApmCode: function () {
            return `${this.item.method}_${this.method}`;
        },

        /**
         * Check if payment is active
         *
         * @returns {Boolean}
         */
        isActive: function () {
            let active = this.getApmCode() === this.selectedApmCode();

            this.active(active);
            return active;
        },

        /**
         * Select payment method
         *
         * @returns {boolean}
         */
        selectPaymentMethod: function () {
            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.item.method);

            this.parentApm.selectedApm(this.method);

            this.renderPaymentFields();
            this.renderPaymentButton();

            if (quote.isVirtual() && quote.billingAddress()) {
                setBillingAddressAction(globalMessageList);
            }

            return true;
        },

        /**
         * Render payment fields
         */
        renderPaymentFields: function () {
            if (this.paymentFields && $(`#paypal_${this.method}_fields`).is(':empty')) {
                this.paymentFields.render(`#paypal_${this.method}_fields`);
            }
        },

        /**
         * Render payment button
         */
        renderPaymentButton: function () {
            if (this.paymentButtons && $(`#paypal_${this.method}_button`).is(':empty')) {
                this.paymentButtons.render(`#paypal_${this.method}_button`);
            }
        },

        /**
         * Get data
         *
         * @returns {Object}
         */
        getData: function () {
            return {
                'method': this.getCode(),
                'additional_data': {
                    'payments_order_id': this.paymentsOrderId,
                    'paypal_order_id': this.paypalOrderId,
                    'payment_source': this.method
                }
            };
        },

        /**
         * We need to hide iDEAL from mobile devices as the webhook callbacks aren't currently available
         * so the method fails to completely successfully.
         */
        hideIdeal: function () {
            if (this.method === 'ideal') {
                mediaCheck({
                    media: '(min-width: 768px)',
                    entry: function () {
                        $('.payment-method-payment_services_paypal_apm_ideal').show();
                    },
                    exit: function () {
                        $('.payment-method-payment_services_paypal_apm_ideal').hide();
                    }
                });
            }
        },

        /**
         * Show/hide loader.
         *
         * @param {Boolean} show
         */
        showLoader: function (show) {
            var event = show ? 'processStart' : 'processStop';

            $('body').trigger(event);
        },

        onCancel: function (error) {
            this.catchError(error);
        },

        /**
         * Catch error.
         *
         * @param {Error} error
         */
        catchError: function (error) {
            if (error.hidden === undefined || !error.hidden) {
                this.messageContainer.addErrorMessage({
                    message: this.requestProcessingError
                });
            }

            this.showLoader(false);

            console.log('Error: ', error);
        },

        /**
         * Create child message renderer component
         *
         * @returns {Component} Chainable.
         */
        createMessagesComponent: function () {
            return this._super();
        },

        /**
         * Extend the core to stop loader on all outcomes of the payment request.
         */
        getPlaceOrderDeferredObject: function () {
            return this._super()
                .always(() => this.showLoader(false));
        }
    });
});
