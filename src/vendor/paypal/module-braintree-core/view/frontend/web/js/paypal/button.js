/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'uiComponent',
        'underscore',
        'jquery',
        'Magento_Customer/js/customer-data',
        'mage/translate',
        'braintree',
        'braintreeCheckoutPayPalAdapter',
        'braintreeDataCollector',
        'braintreePayPalCheckout',
        'PayPal_Braintree/js/actions/create-payment',
        'PayPal_Braintree/js/actions/get-shipping-methods',
        'PayPal_Braintree/js/actions/set-shipping-information',
        'PayPal_Braintree/js/actions/update-totals',
        'PayPal_Braintree/js/helper/check-guest-checkout',
        'PayPal_Braintree/js/helper/is-cart-virtual',
        'PayPal_Braintree/js/helper/addresses/map-paypal-payment-information',
        'PayPal_Braintree/js/helper/addresses/map-paypal-shipping-information',
        'PayPal_Braintree/js/helper/submit-review-page',
        'PayPal_Braintree/js/helper/remove-non-digit-characters',
        'PayPal_Braintree/js/helper/replace-single-quote-character',
        'PayPal_Braintree/js/model/region-data',
        'domReady!'
    ],
    function (
        Component,
        _,
        $,
        customerData,
        $t,
        braintree,
        Braintree,
        dataCollector,
        paypalCheckout,
        createPayment,
        getShippingMethods,
        setShippingInformation,
        updateTotals,
        checkGuestCheckout,
        isCartVirtual,
        mapPayPalPaymentInformation,
        mapPayPalShippingInformation,
        submitReviewPage,
        removeNonDigitCharacters,
        replaceSingleQuoteCharacter,
        regionDataModel
    ) {
        'use strict';

        return Component.extend({
            events: {
                onClick: null,
                onCancel: null,
                onError: null
            },
            currencyCode: null,
            amount: 0,
            quoteId: 0,
            storeCode: 'default',
            shippingAddress: {},
            shippingMethods: {},
            shippingMethodCode: null,
            buttonIds: [],
            skipReview: null,
            buttonConfig: {},
            pageType: null,

            /**
             * Initialize button
             *
             * @param config
             * @param element
             */
            initialize: function (config, element) {
                this._super(config);

                $(document).on('priceUpdated', (event, displayPrices) => {
                    $('.action-braintree-paypal-message[data-pp-type="product"]')
                        .attr('data-pp-amount', displayPrices.finalPrice.amount);
                });

                this.buttonConfig = config.buttonConfig;
                this.buttonIds = config.buttonIds;
                this.loadSDK(this.buttonConfig);

                window.addEventListener('hashchange', function () {
                    const step = window.location.hash.replace('#', '');

                    if (step === 'shipping') {
                        Braintree.getPayPalInstance()?.teardown(function () {
                            this.loadSDK(this.buttonConfig);
                        }.bind(this));
                    }

                }.bind(this));

                window.addEventListener('paypal:reinit-express', function () {
                    this.loadSDK(this.buttonConfig);
                }.bind(this));

                const cart = customerData.get('cart');

                cart.subscribe(({ braintree_masked_id }) => {
                    this.setQuoteId(braintree_masked_id);
                });

                if (cart()?.braintree_masked_id) {
                    this.setQuoteId(cart().braintree_masked_id);
                }
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
            setSkipReview: function (value) {
                this.skipReview = value;
            },
            getSkipReview: function () {
                return this.skipReview;
            },

            /**
             * Set and get amount
             */
            setAmount: function (value) {
                this.amount = parseFloat(value).toFixed(2);
            },
            getAmount: function () {
                return parseFloat(this.amount).toFixed(2);
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
             * Set and get store code
             */
            setCurrencyCode: function (value) {
                this.currencyCode = value;
            },
            getCurrencyCode: function () {
                return this.currencyCode;
            },

            /**
             * Load Braintree PayPal SDK
             *
             * @param buttonConfig
             */
            loadSDK: function (buttonConfig) {
                // Load SDK
                braintree.create({
                    authorization: buttonConfig.clientToken
                }, function (clientErr, clientInstance) {
                    if (clientErr) {
                        console.error('paypalCheckout error', clientErr);
                        let error = 'PayPal Checkout could not be initialized. Please contact the store owner.';

                        return Braintree.showError(error);
                    }
                    dataCollector.create({
                        client: clientInstance,
                        paypal: true
                    }, function (err) {
                        if (err) {
                            return console.log(err);
                        }
                    });
                    paypalCheckout.create({
                        client: clientInstance
                    }, function (err, paypalCheckoutInstance) {
                        if (typeof paypal !== 'undefined' ) {
                            this.renderPayPalButtons(paypalCheckoutInstance);
                        } else {
                            let configSDK = {
                                    components: 'buttons,funding-eligibility',
                                    'enable-funding': this.isCreditActive(buttonConfig) ? 'credit' : 'paylater',
                                    currency: buttonConfig.currency,
                                    commit: buttonConfig.skipOrderReviewStep && !isCartVirtual(),
                                },
                                buyerCountry = this.getMerchantCountry(buttonConfig);

                            if (buttonConfig.environment === 'sandbox'
                                && (buyerCountry !== '' || buyerCountry !== 'undefined')) {
                                configSDK['buyer-country'] = buyerCountry;
                            }

                            if (buttonConfig.pageType) {
                                configSDK.dataAttributes = {
                                    'page-type': buttonConfig.pageType
                                }
                            }

                            paypalCheckoutInstance.loadPayPalSDK(configSDK, function () {
                                this.renderPayPalButtons(paypalCheckoutInstance);
                            }.bind(this));
                        }
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
             *
             * @param paypalCheckoutInstance
             */
            renderPayPalButtons: function (paypalCheckoutInstance) {
                this.payPalButton(paypalCheckoutInstance);
            },

            /**
             * @param paypalCheckoutInstance
             */
            payPalButton: function (paypalCheckoutInstance) {
                let self = this;
                $(this.buttonIds.join(',')).each(function (index, element) {
                    $(element).html('');

                    let currentElement = $(element),
                        style = {
                            label: currentElement.data('label'),
                            color: currentElement.data('color'),
                            shape: currentElement.data('shape')
                        };

                    if (currentElement.data('fundingicons')) {
                        style.fundingicons = currentElement.data('fundingicons');
                    }

                    // set values
                    self.setCurrencyCode(currentElement.data('currency'));
                    self.setAmount(currentElement.data('amount'));
                    self.setStoreCode(currentElement.data('storecode'));
                    self.setActionSuccess(currentElement.data('actionsuccess'));

                    self.setSkipReview(currentElement.data('skiporderreviewstep'));

                    // Render
                    const fundingSource = currentElement.data('funding'),
                        config = {
                            fundingSource,
                            style: style,
                            message: Braintree.getMessage(
                                fundingSource,
                                self.getAmount(),
                                self.buttonConfig.pageType
                            ),

                            createOrder: () => self.createOrder(paypalCheckoutInstance, currentElement),

                            validate: function (actions) {
                                let cart = customerData.get('cart'),
                                    customer = customerData.get('customer'),
                                    declinePayment = false,
                                    isGuestCheckoutAllowed;

                                isGuestCheckoutAllowed = cart().isGuestCheckoutAllowed;
                                declinePayment = !customer().firstname && !isGuestCheckoutAllowed
                                    && typeof isGuestCheckoutAllowed !== 'undefined';

                                if (declinePayment) {
                                    actions.disable();
                                }
                            },

                            onCancel: function () {
                                $('#maincontent').trigger('processStop');
                            },

                            onError: function (errorData) {
                                console.error('paypalCheckout button render error', errorData);
                                $('#maincontent').trigger('processStop');
                            },

                            onClick: self.onClick.bind(self),

                            onApprove: function (approveData) {
                                return paypalCheckoutInstance.tokenizePayment(approveData, function (err, payload) {
                                    if (!self.getSkipReview() || isCartVirtual()) {
                                        payload.details.shippingAddress = self.getShippingAddressData(payload);
                                        payload.details.billingAddress = self.getBillingAddressData(payload, currentElement);

                                        return submitReviewPage(payload, currentElement, 'paypal');
                                    }

                                    const shippingMethod = self.shippingMethods[self.shippingMethodCode];
                                    const shippingInformation = mapPayPalShippingInformation(payload, shippingMethod);
                                    const paymentInformation = mapPayPalPaymentInformation(payload, currentElement.data('requiredbillingaddress'));

                                    return setShippingInformation(shippingInformation, self.getStoreCode(), self.getQuoteId())
                                        .then(() => createPayment(paymentInformation, self.getStoreCode(), self.getQuoteId()))
                                        .then(() => document.location = self.getActionSuccess())
                                        .catch(function (error) {
                                            alert(error);
                                        });
                                });
                            }
                        };

                        if (self.getSkipReview()) {
                            config.onShippingChange = async function (data) {
                                // Create a payload to get estimated shipping methods
                                let payload = {
                                    address: {
                                        city: data.shipping_address.city,
                                        region: data.shipping_address.state,
                                        country_id: data.shipping_address.country_code,
                                        postcode: data.shipping_address.postal_code,
                                        save_in_address_book: 0
                                    }
                                };

                                this.shippingAddress = payload.address;

                                // POST to endpoint for shipping methods.
                                const result = await getShippingMethods(
                                    payload,
                                    self.getStoreCode(),
                                    self.getQuoteId()
                                );

                                // Stop if no shipping methods.
                                let virtualFlag = false;
                                if (result.length === 0) {
                                    let productItems = customerData.get('cart')().items;
                                    _.each(productItems,
                                        function (item) {
                                            if (item.is_virtual || item.product_type === 'bundle') {
                                                virtualFlag = true;
                                            } else {
                                                virtualFlag = false;
                                            }
                                        }
                                    );
                                    if (!virtualFlag) {
                                        alert($t("There are no shipping methods available for you right now. Please try again or use an alternative payment method."));
                                        return false;
                                    }
                                }

                                let shippingMethods = [];
                                // Format shipping methods array.
                                for (let i = 0; i < result.length; i++) {
                                    if (typeof result[i].method_code !== 'string') {
                                        continue;
                                    }

                                    let selected = false;
                                    if (!data.selected_shipping_option) {
                                        if (i === 0) {
                                            selected = true;
                                            self.shippingMethodCode = result[i].method_code;
                                        }
                                    } else {
                                        if (data.selected_shipping_option.id === result[i].method_code) {
                                            selected = true;
                                            self.shippingMethodCode = result[i].method_code;
                                        }
                                    }

                                    // get shipping type
                                    let shippingType = 'SHIPPING';
                                    if (result[i].method_code === 'pickup') {
                                        shippingType = 'PICKUP';
                                    }

                                    let method = {
                                        id: result[i].method_code,
                                        type: shippingType,
                                        label: result[i].method_title,
                                        selected: selected,
                                        amount: {
                                            value: parseFloat(result[i].price_excl_tax).toFixed(2),
                                            currency: self.getCurrencyCode()
                                        },
                                    };

                                    // Add method object to array.
                                    shippingMethods.push(method);

                                    self.shippingMethods[result[i].method_code] = result[i];
                                }

                                // Create payload to get totals
                                let shippingInformationPayload = {
                                    "addressInformation": {
                                        "shipping_address": {
                                            "countryId": this.shippingAddress.country_id,
                                            "region": this.shippingAddress.region,
                                            "regionId": regionDataModel.getRegionIdByCode(this.shippingAddress.country_id, this.shippingAddress.region),
                                            "postcode": this.shippingAddress.postcode
                                        },
                                        "shipping_method_code": virtualFlag ? null : self.shippingMethods[self.shippingMethodCode].method_code,
                                        "shipping_carrier_code": virtualFlag ? null : self.shippingMethods[self.shippingMethodCode].carrier_code
                                    }
                                };

                                // Set shipping information to the quote
                                await setShippingInformation(shippingInformationPayload, self.getStoreCode(), self.getQuoteId());

                                const totalsPayload = {
                                    addressInformation: {
                                        address: shippingInformationPayload.addressInformation.shipping_address,
                                        "shipping_method_code": shippingInformationPayload.addressInformation.shipping_method_code,
                                        "shipping_carrier_code": shippingInformationPayload.addressInformation.shipping_carrier_code
                                    }
                                }

                                // POST to endpoint to get totals, using 1st shipping method
                                const totals = await updateTotals(totalsPayload, self.getStoreCode(), self.getQuoteId());

                                // In rare cases the estimated shipping value doesn't match the true value when added
                                // to the quote due to rounding within Magento. Update the shipping price of the
                                // selected method to be that as provided by the totals information as this is correct.
                                const shippingIndex = shippingMethods.findIndex((shippingMethod) => {
                                    return shippingMethod.id === shippingInformationPayload.addressInformation.shipping_method_code;
                                });
                                shippingMethods[shippingIndex].amount.value = parseFloat(totals.shipping_amount).toFixed(2);

                                // Set total
                                self.setAmount(totals.base_grand_total);

                                // update payments to PayPal
                                return paypalCheckoutInstance.updatePayment({
                                    paymentId: data.paymentId,
                                    amount: self.getAmount(),
                                    currency: self.getCurrencyCode(),
                                    shippingOptions: shippingMethods
                                });
                            };
                        }

                    const button = window.paypal.Buttons(config);

                    if (!button.isEligible()) {
                        console.log('PayPal button is not elligible');
                        currentElement.parent().remove();
                        return;
                    }
                    if (button.isEligible() && $('#' + currentElement.attr('id')).length) {
                        button.render('#' + currentElement.attr('id'));
                    }
                });
            },

            createOrder: function (paypalCheckoutInstance, currentElement) {
                return paypalCheckoutInstance.createPayment({
                    amount: currentElement.data('amount'),
                    locale: currentElement.data('locale'),
                    currency: currentElement.data('currency'),
                    flow: 'checkout',
                    enableShippingAddress: true,
                    displayName: currentElement.data('displayname'),
                    shippingOptions: []
                });
            },

            onClick: function (data, actions) {
                if (!checkGuestCheckout()) {
                    return false;
                }

                return true;
            },

            /**
             * Get the shipping address from the payment data model which should already be set by the calling script.
             *
             * @return {?Object}
             */
            getShippingAddressData: function (payload) {
                let accountFirstName = replaceSingleQuoteCharacter(payload.details.firstName),
                    accountLastName = replaceSingleQuoteCharacter(payload.details.lastName),
                    accountEmail = replaceSingleQuoteCharacter(payload.details.email),
                    recipientFirstName = accountFirstName,
                    recipientLastName = accountLastName,
                    recipientName = null,
                    address = payload.details.shippingAddress,
                    phone = _.get(payload, ['details', 'phone'], '');

                    // Map the shipping address correctly
                    if (!_.isUndefined(address.recipientName) && _.isString(address.recipientName)) {
                        /*
                            * Trim leading/ending spaces before splitting,
                            * filter to remove array keys with empty values
                            * & set to variable.
                            */
                        recipientName = address.recipientName.trim().split(' ').filter(n => n);
                    }

                    /*
                        * If the recipientName is not null, and it is an array with
                        * first/last name, use it. Otherwise, keep the default billing first/last name.
                        * This is to avoid cases of old accounts where spaces were allowed to first or
                        * last name in PayPal and the result was an array with empty fields
                        * resulting in empty names in the system.
                        */
                    if (!_.isNull(recipientName) && !_.isUndefined(recipientName[1])) {
                        recipientFirstName = replaceSingleQuoteCharacter(recipientName[0]);
                        recipientLastName = replaceSingleQuoteCharacter(recipientName[1]);
                    }

                return {
                    streetAddress: typeof address.line2 !== 'undefined' && _.isString(address.line2)
                        ? replaceSingleQuoteCharacter(address.line1)
                                + ' ' + replaceSingleQuoteCharacter(address.line2)
                        : replaceSingleQuoteCharacter(address.line1),
                    locality: replaceSingleQuoteCharacter(address.city),
                    postalCode: address.postalCode,
                    countryCodeAlpha2: address.countryCode,
                    email: accountEmail,
                    recipientFirstName: recipientFirstName,
                    recipientLastName: recipientLastName,
                    telephone: removeNonDigitCharacters(phone),
                    region: typeof address.state !== 'undefined'
                        ? replaceSingleQuoteCharacter(address.state)
                        : ''
                };
            },

            /**
             * Get the billing address from the payment data model which should already be set by the calling script.
             *
             * @return {?Object}
             */
            getBillingAddressData: function (payload, currentElement) {
                // Map the billing address correctly
                const isRequiredBillingAddress = currentElement.data('requiredbillingaddress');

                if (isRequiredBillingAddress
                            && typeof payload.details.billingAddress !== 'undefined') {

                    if (!payload.details?.billingAddress?.streetAddress) {
                        return payload.details.shippingAddress;
                    }
                    const billingAddress = payload.details.billingAddress,
                        phone = _.get(payload, ['details', 'phone'], '');

                    return {
                        streetAddress: typeof billingAddress.line2 !== 'undefined'
                                && _.isString(billingAddress.line2)
                            ? replaceSingleQuoteCharacter(billingAddress.line1)
                                    + ' ' + replaceSingleQuoteCharacter(billingAddress.line2)
                            : replaceSingleQuoteCharacter(billingAddress.line1),
                        locality: replaceSingleQuoteCharacter(billingAddress.city),
                        postalCode: billingAddress.postalCode,
                        countryCodeAlpha2: billingAddress.countryCode,
                        telephone: removeNonDigitCharacters(phone),
                        region: typeof billingAddress.state !== 'undefined'
                            ? replaceSingleQuoteCharacter(billingAddress.state)
                            : ''
                    };
                }

            },
        });
    }
);
