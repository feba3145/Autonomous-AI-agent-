/**
 * Braintree Google Pay payment method integration.
 **/
define([
    'underscore',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Vault/js/view/payment/vault-enabler',
    'PayPal_Braintree/js/googlepay/button',
    'PayPal_Braintree/js/googlepay/model/parsed-response',
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/helper/get-google-pay-line-items',
    'PayPal_Braintree/js/view/payment/adapter',
    'PayPal_Braintree/js/view/payment/validator-handler'
], function (
    _,
    $t,
    Component,
    quote,
    VaultEnabler,
    GooglePayButton,
    parsedResponseModel,
    paymentDataModel,
    getGooglePayLineItems,
    braintreeMainAdapter,
    validatorManager
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPal_Braintree/googlepay/core-checkout',
            validatorManager: validatorManager,
            paymentMethodNonce: null,
            creditCardBin: null,
            deviceData: null,
            grandTotalAmount: 0,
            vaultEnabler: null,
            additionalData: {}
        },

        /**
         * @returns {exports.initialize}
         */
        initialize: function () {
            this._super();
            this.vaultEnabler = new VaultEnabler();
            this.vaultEnabler.setPaymentCode(this.getVaultCode());

            return this;
        },

        /**
         * Inject the Google Pay button into the target element
         */
        getGooglePayBtn: function (id) {
            GooglePayButton.init(
                document.getElementById(id),
                this
            );
        },

        /**
         * Subscribe to grand totals
         */
        initObservable: function () {
            this._super();
            this.vaultEnabler = new VaultEnabler();
            this.vaultEnabler.setPaymentCode(this.getVaultCode());

            this.validatorManager.initialize();

            this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);
            this.currencyCode = quote.totals()['base_currency_code'];

            quote.totals.subscribe(function () {
                if (this.grandTotalAmount !== quote.totals()['base_grand_total']) {
                    this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);
                }
            }.bind(this));

            return this;
        },

        /**
         * Google Pay place order method
         */
        startPlaceOrder: function (paymentData) {
            return new Promise((resolve) => {
                paymentDataModel.setPaymentMethodData(_.get(
                    paymentData,
                    'paymentMethodData',
                    null
                ));
                paymentDataModel.setEmail(_.get(paymentData, 'email', ''));
                paymentDataModel.setShippingAddress(_.get(
                    paymentData,
                    'shippingAddress',
                    null
                ));

                const googlePaymentInstance = braintreeMainAdapter.getGooglePayInstance();
                googlePaymentInstance.parseResponse(paymentData).then(function (result) {
                    parsedResponseModel.setNonce(result.nonce);
                    parsedResponseModel.setIsNetworkTokenized(_.get(
                        result,
                        ['details', 'isNetworkTokenized'],
                        false
                    ));
                    parsedResponseModel.setBin(_.get(
                        result,
                        ['details', 'bin'],
                        null
                    ));

                    this.email = paymentDataModel.getEmail();
                    this.paymentMethodNonce = parsedResponseModel.getNonce();
                    this.creditCardBin = parsedResponseModel.getBin();

                    if (parsedResponseModel.getIsNetworkTokenized() === false) {
                        // place order on success validation
                        this.validatorManager.validate(this, function () {
                            this.setDeviceData(braintreeMainAdapter.deviceData);
                            return this.placeOrder('parent');
                        }.bind(this), function () {
                            this.paymentMethodNonce = null;
                            this.creditCardBin = null;
                        }.bind(this));
                    } else {
                        this.setDeviceData(braintreeMainAdapter.deviceData);
                        this.placeOrder();
                    }

                    resolve({
                        transactionState: 'SUCCESS',
                    });
                }.bind(this));
            });

        },

        /**
         * Save device_data
         */
        setDeviceData: function (device_data) {
            this.deviceData = device_data;
        },

        /**
         * Retrieve the client token
         * @returns null|string
         */
        getClientToken: function () {
            return window.checkoutConfig.payment[this.getCode()].clientToken;
        },

        /**
         * Get price includes tax configuration.
         * @returns bool
         */
        getPriceIncludesTax: function () {
            return window.checkoutConfig.payment[this.getCode()].priceIncludesTax;
        },

        /**
         * Payment request info
         */
        getPaymentRequest: function () {
            let result = {
                transactionInfo: {
                    currencyCode: this.currencyCode,
                    displayItems: getGooglePayLineItems(quote.totals(), this.getPriceIncludesTax()),
                    totalPrice: this.grandTotalAmount,
                    totalPriceLabel: $t('Total'),
                    totalPriceStatus: 'FINAL'
                },
                allowedPaymentMethods: [
                    {
                        'type': 'CARD',
                        'parameters': {
                            'allowedCardNetworks': this.getCardTypes(),
                            'billingAddressRequired': true,
                            'billingAddressParameters': {
                                format: 'FULL',
                                phoneNumberRequired: true
                            }
                        }

                    }
                ],
                shippingAddressRequired: false,
                emailRequired: false,
                callbackIntents: ['PAYMENT_AUTHORIZATION']
            };

            if (this.getEnvironment() !== 'TEST') {
                result.merchantInfo = { merchantId: this.getMerchantId() };
            }

            return result;
        },

        /**
         * Merchant display name
         */
        getMerchantId: function () {
            return window.checkoutConfig.payment[this.getCode()].merchantId;
        },

        /**
         * Environment
         */
        getEnvironment: function () {
            return window.checkoutConfig.payment[this.getCode()].environment;
        },

        /**
         * Card Types
         */
        getCardTypes: function () {
            return window.checkoutConfig.payment[this.getCode()].cardTypes;
        },

        /**
         * BTN Color
         */
        getBtnColor: function () {
            return window.checkoutConfig.payment[this.getCode()].btnColor;
        },

        /**
         * Return the skip review state for the Google Pay at end of checkout.
         * @returns bool
         */
        getSkipReview: function () {
            return false;
        },

        /**
         * Get data
         * @returns {Object}
         */
        getData: function () {
            let data = {
                'method': this.getCode(),
                'additional_data': {
                    'payment_method_nonce': this.paymentMethodNonce,
                    'device_data': this.deviceData,
                    'is_network_tokenized': parsedResponseModel.getIsNetworkTokenized()
                }
            };

            if (parsedResponseModel.getIsNetworkTokenized() === false) {
                data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
                this.vaultEnabler.visitAdditionalData(data);
            }

            return data;
        },

        /**
         * Return image url for the Google Pay mark
         */
        getPaymentMarkSrc: function () {
            return window.checkoutConfig.payment[this.getCode()].paymentMarkSrc;
        },

        /**
         * @returns {Boolean}
         */
        isVaultEnabled: function () {
            return this.vaultEnabler.isVaultEnabled();
        },

        /**
         * @returns {String}
         */
        getVaultCode: function () {
            return window.checkoutConfig.payment[this.getCode()].vaultCode;
        }
    });
});
