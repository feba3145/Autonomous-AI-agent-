/**
 * Braintree Google Pay button api
 **/
define([
    'uiComponent',
    'underscore',
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils',
    'PayPal_Braintree/js/actions/create-payment',
    'PayPal_Braintree/js/actions/get-shipping-methods',
    'PayPal_Braintree/js/actions/set-shipping-information',
    'PayPal_Braintree/js/actions/update-totals',
    'PayPal_Braintree/js/form-builder',
    'PayPal_Braintree/js/googlepay/implementations/shortcut/3d-secure',
    'PayPal_Braintree/js/googlepay/model/parsed-response',
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/helper/addresses/map-googlepay-payment-information',
    'PayPal_Braintree/js/helper/addresses/map-googlepay-shipping-information',
    'PayPal_Braintree/js/helper/get-google-pay-line-items',
    'PayPal_Braintree/js/helper/is-cart-virtual',
    'PayPal_Braintree/js/helper/remove-non-digit-characters',
    'PayPal_Braintree/js/helper/submit-review-page',
    'PayPal_Braintree/js/model/region-data',
    'PayPal_Braintree/js/view/payment/adapter',
    'PayPal_Braintree/js/view/payment/validator-manager'
], function (
    Component,
    _,
    $,
    $t,
    customerData,
    priceUtils,
    createPayment,
    getShippingMethods,
    setShippingInformation,
    updateTotals,
    formBuilder,
    threeDSecureValidator,
    parsedResponseModel,
    paymentDataModel,
    mapGooglePayPaymentInformation,
    mapGooglePayShippingInformation,
    getGooglePayLineItems,
    isCartVirtual,
    removeNonDigitCharacters,
    submitReviewPage,
    regionDataModel,
    braintreeMainAdapter,
    validatorManager
) {
    'use strict';

    return Component.extend({
        defaults: {
            validatorManager: validatorManager,
            threeDSecureValidator: threeDSecureValidator,
            clientToken: null,
            merchantId: null,
            currencyCode: null,
            actionSuccess: null,
            amount: null,
            cardTypes: [],
            shippingMethods: {},
            shippingMethodCode: null,
            btnColor: 0,
            email: null,
            paymentMethodNonce: null,
            creditCardBin: null,
            element: null,
            priceFormat: [],
        },

        /**
         * Set & get environment
         * "PRODUCTION" or "TEST"
         */
        setEnvironment: function (value) {
            this.environment = value;
        },
        getEnvironment: function () {
            return this.environment;
        },

        /**
         * Set & get api token
         */
        setClientToken: function (value) {
            this.clientToken = value;
        },
        getClientToken: function () {
            return this.clientToken;
        },

        /**
         * Set and get display name
         */
        setMerchantId: function (value) {
            this.merchantId = value;
        },
        getMerchantId: function () {
            return this.merchantId;
        },

        /**
         * Set and get currency code
         */
        setAmount: function (value) {
            this.amount = parseFloat(value).toFixed(2);
        },
        getAmount: function () {
            return this.amount;
        },

        /**
         * Set and get currency code
         */
        setCurrencyCode: function (value) {
            this.currencyCode = value;
        },
        getCurrencyCode: function () {
            return this.currencyCode;
        },

        /**
         * Set and get success redirection url
         */
        setActionSuccess: function (value) {
            this.actionSuccess = value;
        },
        getActionSuccess: function () {
            return this.actionSuccess;
        },

        /**
         * Set and get success redirection url
         */
        setCardTypes: function (value) {
            this.cardTypes = value;
        },
        getCardTypes: function () {
            return this.cardTypes;
        },

        /**
         * BTN Color
         */
        setBtnColor: function (value) {
            this.btnColor = value;
        },
        getBtnColor: function () {
            return this.btnColor;
        },

        /**
         * Set and get quote id
         */
        setQuoteId: function (value) {
            this.quoteId = value;
        },
        getQuoteId: function () {
            return this.quoteId;
        },

        /**
         * Set and get store code
         */
        setStoreCode: function (value) {
            this.storeCode = value;
        },
        getStoreCode: function () {
            return this.storeCode;
        },

        /**
         * Set and get success redirection url
         */
        setSkipReview: function (value) {
            this.skipReview = value;
        },
        getSkipReview: function () {
            return this.skipReview;
        },

        /**
         * Set and get store code
         */
        setPriceIncludesTax: function (value) {
            this.priceIncludesTax = value;
        },
        getPriceIncludesTax: function () {
            return this.priceIncludesTax;
        },

        /**
         * Set and get the current element
         */
        setElement: function (value) {
            this.element = value;
        },
        getElement: function () {
            return this.element;
        },

        /**
         * Set and get the current element
         */
        setPriceFormat: function (value) {
            this.priceFormat = value;
        },
        getPriceFormat: function () {
            return this.priceFormat;
        },

        /**
         * Add the 3D Secure validator config.
         *
         * @param {object} value
         */
        setThreeDSecureValidatorConfig: function (value) {
            this.threeDSecureValidator.setConfig(value);
        },

        /**
         * Add the 3D Secure validator to the validation manager with amount & billing address data set.
         * It will be added only if 3D Secure is active.
         */
        addThreeDSecureValidator: function () {
            this.threeDSecureValidator.setBillingAddress(this.getThreeDSecureBillingAddressData());
            this.threeDSecureValidator.setShippingAddress(this.getThreeDSecureShippingAddressData());
            this.threeDSecureValidator.setTotalAmount(this.getAmount());

            this.validatorManager.add(this.threeDSecureValidator);
        },

        /**
         * Payment request info
         */
        getPaymentRequest: function () {
            const displayShippingOptions = !isCartVirtual() && this.getSkipReview();
            const callbackIntents = ['PAYMENT_AUTHORIZATION'];

            if (!isCartVirtual()) {
                callbackIntents.push('SHIPPING_ADDRESS');
            }

            if (displayShippingOptions) {
              callbackIntents.push('SHIPPING_OPTION');
            }

            const totals = customerData.get('cart')();
            let result = {
                transactionInfo: {
                    totalPriceStatus: 'ESTIMATED',
                    totalPrice: this.getAmount(),
                    currencyCode: this.getCurrencyCode(),
                    displayItems: getGooglePayLineItems(totals, this.getPriceIncludesTax()),
                    totalPriceLabel: $t('Total'),
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
                shippingAddressRequired: !isCartVirtual(),
                shippingOptionRequired: displayShippingOptions,
                shippingAddressParameters: {
                    phoneNumberRequired: true
                },
                emailRequired: true,
                callbackIntents,
            };

            if (this.getEnvironment() !== 'TEST') {
                result.merchantInfo = { merchantId: this.getMerchantId() };
            }

            return result;
        },

        onPaymentDataChanged: function (data) {
            return new Promise((resolve) => {
                const payload = {
                    address: {
                        city: data.shippingAddress.locality,
                        region: data.shippingAddress.administrativeArea,
                        country_id: data.shippingAddress.countryCode,
                        postcode: data.shippingAddress.postalCode,
                        save_in_address_book: 0
                    }
                };

                let shippingMethods = Promise.resolve();

                if (!isCartVirtual()) {
                    shippingMethods = getShippingMethods(payload, this.getStoreCode(), this.getQuoteId()).then((response) => {
                        const methods = response.filter(({ available }) => available);

                        // Any error message means we need to exit by resolving with an error state.
                        if (!methods.length) {
                            resolve({
                                error: {
                                    reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                    message: $t('There are no shipping methods available for the selected address.'),
                                    intent: 'SHIPPING_ADDRESS',
                                },
                            });
                            return;
                        }

                        const shippingMethods = methods.map((shippingMethod) => {
                            const price = priceUtils.formatPriceLocale(shippingMethod.price_incl_tax, this.getPriceFormat());
                            const description = shippingMethod.carrier_title
                                ? `${price} ${shippingMethod.carrier_title}`
                                : price;

                            this.shippingMethods[shippingMethod.method_code] = shippingMethod;

                            return {
                                id: shippingMethod.method_code,
                                label: shippingMethod.method_title,
                                description,
                            };
                        });

                        return { shippingMethods, methods };
                    });
                }

                shippingMethods.then(({ shippingMethods, methods }) => {
                    let selectedShipping;

                    if (!isCartVirtual() && this.getSkipReview()) {
                        selectedShipping = data.shippingOptionData.id === 'shipping_option_unselected'
                            ? methods[0]
                            : methods.find(({ method_code: id }) => id === data.shippingOptionData.id) || methods[0];

                        this.shippingMethodCode = selectedShipping.method_code;
                    }

                    // Create payload to get totals
                    let totalsPayload = {
                        "addressInformation": {
                            "address": {
                                "countryId": data.shippingAddress.countryCode,
                                "region": data.shippingAddress.administrativeArea,
                                "regionId": regionDataModel.getRegionId(data.shippingAddress.countryCode, data.shippingAddress.administrativeArea),
                                "postcode": data.shippingAddress.postalCode
                            },
                            "shipping_method_code": selectedShipping?.method_code,
                            "shipping_carrier_code": selectedShipping?.method_code
                        }
                    };

                    updateTotals(totalsPayload, this.getStoreCode(), this.getQuoteId())
                        .then((totals) => {
                            const paymentDataRequestUpdate = {
                                newTransactionInfo: {
                                    currencyCode: totals.base_currency_code,
                                    displayItems: getGooglePayLineItems(totals, this.getPriceIncludesTax()),
                                    totalPrice: totals.base_grand_total.toString(),
                                    totalPriceLabel: $t('Total'),
                                    totalPriceStatus: 'FINAL'
                                },
                            };

                            if (shippingMethods && selectedShipping) {
                                paymentDataRequestUpdate.newShippingOptionParameters = {
                                    defaultSelectedOptionId: selectedShipping.method_code,
                                    shippingOptions: shippingMethods,
                                };
                            }

                            resolve(paymentDataRequestUpdate);
                        });
                });
            });
        },

        /**
         * Place the order
         */
        startPlaceOrder: function (paymentData) {
            // Persist the paymentData (shipping address etc.)
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

                    let payload = {
                        details: {
                            shippingAddress: this.getShippingAddressData(),
                            billingAddress: this.getBillingAddressData()
                        },
                        nonce: this.paymentMethodNonce,
                        isNetworkTokenized: parsedResponseModel.getIsNetworkTokenized(),
                        deviceData: braintreeMainAdapter.deviceData
                    };
                    payload.details.name = payload.details.shippingAddress?.name || payload.details.billingAddress?.name;

                    this.email = paymentDataModel.getEmail();
                    this.paymentMethodNonce = parsedResponseModel.getNonce();
                    this.creditCardBin = parsedResponseModel.getBin();

                    if (parsedResponseModel.getIsNetworkTokenized() === false) {
                        /* Add 3D Secure verification to payment & validate payment for non network tokenized cards */
                        this.addThreeDSecureValidator();

                        this.validatorManager.validate(this, function () {
                            /* Set the new nonce from the 3DS verification */
                            payload.nonce = this.paymentMethodNonce;

                            if (!this.getSkipReview() || isCartVirtual()) {
                                return submitReviewPage(payload, this.getElement(), 'googlepay');
                            }

                            const shippingMethod = this.shippingMethods[this.shippingMethodCode];
                            const shippingInformation = mapGooglePayShippingInformation(payload, shippingMethod);
                            const paymentInformation = mapGooglePayPaymentInformation(payload, shippingInformation.addressInformation.shipping_address);

                            return setShippingInformation(shippingInformation, this.getStoreCode(), this.getQuoteId())
                                .then(() => createPayment(paymentInformation, this.getStoreCode(), this.getQuoteId()))
                                .then(() => document.location = this.getActionSuccess())
                                .catch(function (error) {
                                    alert(error);
                                });
                        }.bind(this), function () {
                            this.paymentMethodNonce = null;
                            this.creditCardBin = null;
                        }.bind(this));

                        resolve({
                            transactionState: 'SUCCESS',
                        });
                    } else {
                        formBuilder.build({
                            action: this.getActionSuccess(),
                            fields: {
                                result: JSON.stringify(payload)
                            }
                        }).submit();
                    }
                }.bind(this));
            });
        },

        /**
         * Get the shipping address from the payment data model which should already be set by the calling script.
         *
         * @return {?Object}
         */
        getShippingAddressData: function () {
            const shippingAddress = paymentDataModel.getShippingAddress();

            if (shippingAddress === null) {
                return null;
            }

            return {
                streetAddress: shippingAddress.address1 + '\n' + shippingAddress.address2,
                locality: shippingAddress.locality,
                postalCode: shippingAddress.postalCode,
                countryCodeAlpha2: shippingAddress.countryCode,
                email: paymentDataModel.getEmail(),
                name: shippingAddress.name,
                telephone: removeNonDigitCharacters(_.get(shippingAddress, 'phoneNumber', '')),
                region: _.get(shippingAddress, 'administrativeArea', '')
            };
        },

        /**
         * Get the billing address from the payment data model which should already be set by the calling script.
         *
         * @return {?Object}
         */
        getBillingAddressData: function () {
            const paymentMethodData = paymentDataModel.getPaymentMethodData(),
                billingAddress = _.get(paymentMethodData, ['info', 'billingAddress'], null);

            if (paymentMethodData === null) {
                return null;
            }


            if (billingAddress === null) {
                return null;
            }

            return {
                streetAddress: billingAddress.address1 + '\n' + billingAddress.address2,
                locality: billingAddress.locality,
                postalCode: billingAddress.postalCode,
                countryCodeAlpha2: billingAddress.countryCode,
                email: paymentDataModel.getEmail(),
                name: billingAddress.name,
                telephone: removeNonDigitCharacters(_.get(billingAddress, 'phoneNumber', '')),
                region: _.get(billingAddress, 'administrativeArea', '')
            };
        },

        /**
         * Get the billing address data as required for 3D Secure verification.
         *
         * For First & last name, use a simple split by space.
         *
         * @return {?Object}
         */
        getThreeDSecureBillingAddressData: function () {
            let paymentMethodData = paymentDataModel.getPaymentMethodData(),
                billingAddress = _.get(paymentMethodData, ['info', 'billingAddress'], null);

            if (paymentMethodData === null) {
                return null;
            }

            if (billingAddress === null) {
                return null;
            }

            return {
                firstname: billingAddress.name.substring(0, billingAddress.name.indexOf(' ')),
                lastname: billingAddress.name.substring(billingAddress.name.indexOf(' ') + 1),
                telephone: removeNonDigitCharacters(_.get(billingAddress, 'phoneNumber', '')),
                street: [
                    billingAddress.address1,
                    billingAddress.address2
                ],
                city: billingAddress.locality,
                regionCode: _.get(billingAddress, 'administrativeArea', ''),
                postcode: billingAddress.postalCode,
                countryId: billingAddress.countryCode
            };
        },

        /**
         * Get the shipping address data as required for 3D Secure verification.
         *
         * For First & last name, use a simple split by space.
         *
         * @return {?Object}
         */
        getThreeDSecureShippingAddressData: function () {
            let shippingAddress = paymentDataModel.getShippingAddress();

            if (shippingAddress === null) {
                return null;
            }

            return {
                firstname: shippingAddress.name.substring(0, shippingAddress.name.indexOf(' ')),
                lastname: shippingAddress.name.substring(shippingAddress.name.indexOf(' ') + 1),
                telephone: removeNonDigitCharacters(_.get(shippingAddress, 'phoneNumber', '')),
                street: [
                    shippingAddress.address1,
                    shippingAddress.address2
                ],
                city: shippingAddress.locality,
                regionCode: _.get(shippingAddress, 'administrativeArea', ''),
                postcode: shippingAddress.postalCode,
                countryId: shippingAddress.countryCode
            };
        }
    });
});
