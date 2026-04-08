/**
 * Braintree Apple Pay button API
 *
 **/
define(
    [
        'jquery',
        'underscore',
        'uiComponent',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/actions/create-payment',
        'PayPal_Braintree/js/actions/get-shipping-methods',
        'PayPal_Braintree/js/actions/set-shipping-information',
        'PayPal_Braintree/js/actions/update-totals',
        'PayPal_Braintree/js/helper/get-apple-pay-line-items',
        'PayPal_Braintree/js/helper/remove-non-digit-characters',
        'PayPal_Braintree/js/model/region-data',
    ],
    function (
        $,
        _,
        Component,
        $t,
        customerData,
        createPayment,
        getShippingMethods,
        setShippingInformation,
        updateTotals,
        getApplePayLineItems,
        removeNonDigitCharacters,
        regionDataModel,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                clientToken: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                storeCode: 'default',
                priceIncludesTax: true,
                shippingAddress: {},
                shippingMethods: {}
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
             * Set and get quote id
             */
            setQuoteId: function (value) {
                this.quoteId = value;
            },
            getQuoteId: function () {
                return this.quoteId;
            },

            /**
             * Set and get display name
             */
            setDisplayName: function (value) {
                this.displayName = value;
            },
            getDisplayName: function () {
                return this.displayName;
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
             * Set and get grand total
             */
            setGrandTotalAmount: function (value) {
                this.grandTotalAmount = parseFloat(value).toFixed(2);
            },
            getGrandTotalAmount: function () {
                return parseFloat(this.grandTotalAmount);
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
            setPriceIncludesTax: function (value) {
                this.priceIncludesTax = value;
            },
            getPriceIncludesTax: function () {
                return this.priceIncludesTax;
            },

            /**
             * Payment request info
             */
            getPaymentRequest: function () {
                return {
                    total: {
                        label: this.getDisplayName(),
                        amount: this.getGrandTotalAmount()
                    },
                    requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone'],
                    requiredBillingContactFields: ['postalAddress', 'name']
                };
            },

            /**
             * Retrieve shipping methods based on address
             */
            onShippingContactSelect: function (event, session) {
                // Get the address.
                let address = event.shippingContact,

                    // Create a payload.
                    payload = {
                        address: {
                            city: address.locality,
                            region: address.administrativeArea,
                            country_id: address.countryCode.toUpperCase(),
                            postcode: address.postalCode,
                            save_in_address_book: 0
                        }
                    };

                this.shippingAddress = payload.address;

                getShippingMethods(payload, this.getStoreCode(), this.getQuoteId())
                .done(function (result) {
                    // Stop if no shipping methods.
                    let virtualFlag = false,
                        shippingMethods = [],
                        totalsPayload = {};

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
                            session.abort();
                            // eslint-disable-next-line
                            alert($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.'));
                            return false;
                        }
                    }

                    this.shippingMethods = {};

                    // Format shipping methods array.
                    for (let i = 0; i < result.length; i++) {
                        if (typeof result[i].method_code !== 'string') {
                            continue;
                        }

                        let method = {
                            identifier: result[i].method_code,
                            label: result[i].method_title,
                            detail: result[i].carrier_title ? result[i].carrier_title : '',
                            amount: parseFloat(result[i].amount).toFixed(2)
                        };

                        // Add method object to array.
                        shippingMethods.push(method);

                        this.shippingMethods[result[i].method_code] = result[i];

                        if (!this.shippingMethod) {
                            this.shippingMethod = result[i].method_code;
                        }
                    }

                    // Create payload to get totals
                    totalsPayload = {
                        'addressInformation': {
                            'address': {
                                'countryId': this.shippingAddress.country_id,
                                'region': this.shippingAddress.region,
                                'regionId': regionDataModel.getRegionId(
                                    this.shippingAddress.country_id, this.shippingAddress.region),
                                'postcode': this.shippingAddress.postcode
                            },
                            'shipping_method_code': virtualFlag
                                ? null : this.shippingMethods[shippingMethods[0].identifier].method_code,
                            'shipping_carrier_code': virtualFlag
                                ? null : this.shippingMethods[shippingMethods[0].identifier].carrier_code
                        }
                    };

                    // POST to endpoint to get totals, using 1st shipping method
                    updateTotals(totalsPayload, this.getStoreCode(), this.getQuoteId())
                    .done(function (totals) {
                        // Set total
                        this.setGrandTotalAmount(totals.base_grand_total);

                        // Pass shipping methods back
                        session.completeShippingContactSelection(
                            window.ApplePaySession.STATUS_SUCCESS,
                            shippingMethods,
                            {
                                label: this.getDisplayName(),
                                amount: this.getGrandTotalAmount()
                            },
                            getApplePayLineItems(totals, this.getPriceIncludesTax()),
                        );
                    }.bind(this)).fail(function (error) {
                        session.abort();
                        // eslint-disable-next-line
                        alert($t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.'));
                        console.error('Braintree ApplePay: Unable to get totals', error);
                        return false;
                    });

                }.bind(this)).fail(function (result) {
                    session.abort();
                    // eslint-disable-next-line
                    alert($t('We\'re unable to find any shipping methods for you. Please try an alternative payment method.'));
                    // eslint-disable-next-line
                    console.error('Braintree ApplePay: Unable to find shipping methods for estimate-shipping-methods', result);
                    return false;
                });
            },

            /**
             * Record which shipping method has been selected & Updated totals
             */
            onShippingMethodSelect: function (event, session) {
                let shippingMethod = event.shippingMethod,
                    payload = {
                        'addressInformation': {
                            'address': {
                                'countryId': this.shippingAddress.country_id,
                                'region': this.shippingAddress.region,
                                'regionId': regionDataModel.getRegionId(this.shippingAddress.country_id,
                                    this.shippingAddress.region),
                                'postcode': this.shippingAddress.postcode
                            },
                            'shipping_method_code': this.shippingMethods[shippingMethod.identifier].method_code,
                            'shipping_carrier_code': this.shippingMethods[shippingMethod.identifier].carrier_code
                        }
                    };

                this.shippingMethod = shippingMethod.identifier;

                updateTotals(payload, this.getStoreCode(), this.getQuoteId())
                .done(function (r) {
                    this.setGrandTotalAmount(r.base_grand_total);

                    session.completeShippingMethodSelection(
                        window.ApplePaySession.STATUS_SUCCESS,
                        {
                            label: this.getDisplayName(),
                            amount: this.getGrandTotalAmount()
                        },
                        getApplePayLineItems(r, this.getPriceIncludesTax())
                    );
                }.bind(this));
            },

            /**
             * Place the order
             */
            startPlaceOrder: function (nonce, event, session, device_data) {
                let shippingContact = event.payment.shippingContact,
                    billingContact = event.payment.billingContact,
                    payload = {
                        'addressInformation': {
                            'shipping_address': {
                                'email': shippingContact.emailAddress,
                                'telephone': removeNonDigitCharacters(_.get(shippingContact, 'phoneNumber', '')),
                                'firstname': shippingContact.givenName,
                                'lastname': shippingContact.familyName,
                                'street': shippingContact.addressLines,
                                'city': shippingContact.locality,
                                'region': shippingContact.administrativeArea,
                                'region_id': regionDataModel.getRegionId(
                                    shippingContact.countryCode.toUpperCase(), shippingContact.administrativeArea),
                                'region_code': null,
                                'country_id': shippingContact.countryCode.toUpperCase(),
                                'postcode': shippingContact.postalCode,
                                'same_as_billing': 0,
                                'customer_address_id': 0,
                                'save_in_address_book': 0
                            },
                            'billing_address': {
                                'email': shippingContact.emailAddress,
                                'telephone': removeNonDigitCharacters(_.get(shippingContact, 'phoneNumber', '')),
                                'firstname': billingContact.givenName,
                                'lastname': billingContact.familyName,
                                'street': billingContact.addressLines,
                                'city': billingContact.locality,
                                'region': billingContact.administrativeArea,
                                'region_id': regionDataModel.getRegionId(
                                    billingContact.countryCode.toUpperCase(), billingContact.administrativeArea),
                                'region_code': null,
                                'country_id': billingContact.countryCode.toUpperCase(),
                                'postcode': billingContact.postalCode,
                                'same_as_billing': 0,
                                'customer_address_id': 0,
                                'save_in_address_book': 0
                            },
                            'shipping_method_code': this.shippingMethod
                                ? this.shippingMethods[this.shippingMethod].method_code : '' ,
                            'shipping_carrier_code': this.shippingMethod
                                ? this.shippingMethods[this.shippingMethod].carrier_code : ''
                        }
                    };

                // Set addresses

                setShippingInformation(payload, this.getStoreCode(), this.getQuoteId())
                    .then(() => {
                        // Submit payment information
                        let paymentInformation = {
                            'email': shippingContact.emailAddress,
                            'paymentMethod': {
                                'method': 'braintree_applepay',
                                'additional_data': {
                                    'payment_method_nonce': nonce,
                                    'device_data': device_data
                                }
                            }
                        };

                        return createPayment(paymentInformation, this.getStoreCode(), this.getQuoteId())
                    })
                    .then(() => {
                        session.completePayment(window.ApplePaySession.STATUS_SUCCESS);
                        document.location = this.getActionSuccess();
                    })
                    .catch(function () {
                        session.completePayment(window.ApplePaySession.STATUS_FAILURE);
                        alert($t('We\'re unable to take your payment through Apple Pay. Please try an again or use an alternative payment method.'));
                        return false;
                    });
            }
        });
    });
