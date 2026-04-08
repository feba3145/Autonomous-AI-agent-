define([
    'jquery',
    'uiComponent',
    'ko',
    'PayPal_Braintree/js/customer/modals/address-modal',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/url'
], function (
    $,
    Component,
    ko,
    addressModal,
    additionalValidators,
    urlBuilder
) {
    'use strict';
    return Component.extend({

        defaults: {
            addressModal: addressModal,
            deliveryIntervals: ko.observableArray(null),
            currentlySelectedInterval: ko.observable(null),
            minDatePickerValue: 1,
            standardDeliveryDays: 1,
            baseUrl: ko.observable(),
            updatedOrderEntityId: null,
            countryId: 'GB',
            submitBtnSelector: '#braintree_submit',
            phoneNumberMaxLength: ko.observable(11),
            phoneNumberMinLength: ko.observable(2),
            phoneNumberMaxLengthErrorVisible: ko.observable(false),
            phoneNumberMinLengthErrorVisible: ko.observable(false)
        },

        /**
         * @inheritDoc
         */
        initialize: function (config) {
            this._super(config);
            let self = this;

            additionalValidators.registerValidator({
                validate: function () {
                    const $form = $('#form-validate');

                    $form.validation();
                    return $form.validation('isValid');
                }
            });

            fetch(urlBuilder.build('graphql'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Store': this.storeCode
                },
                body: JSON.stringify({
                    query: `{
                        countries {
                            full_name_locale,
                            two_letter_abbreviation
                        }
                    }`
                })
            }).then(response => response.json()).then(response => {
                const countries = response.data.countries || [];

                countries.forEach(country => {
                    self.addressModal.viewModel.countries.push({
                        countryCode: country.two_letter_abbreviation,
                        countryName: country.full_name_locale
                    });
                });
            });
        },

        /**
         * Toggle submit
         *
         * @param disable
         */
        toggleSubmit: function (disable) {
            let submitBtn = $(this.submitBtnSelector);

            if (submitBtn.length) {
                submitBtn.attr('disabled', disable);
            }
        },

        /**
         * Show address modal
         *
         * @param storeCode
         */
        showAddressModal: function (storeCode) {
            this.addressModal.viewModel.selectExistingVisible(true);
            this.addressModal.viewModel.currentCountryId(this.countryId);
            this.addressModal.showAddressModal(storeCode);
            let addressLength = this.addressModal.viewModel.currentAddresses().length;

            this.addressModal.viewModel.newAddressFormVisible(addressLength === 0);
            this.showLookupForm();
            this.toggleSubmit(true);
        },

        /**
         * Show add new address form
         */
        showNewAddressForm: function () {
            this.addressModal.toggleNewAddAddressForm(true);
            if (this.addressModal.toggleNewAddAddressForm) {
                document.getElementById('form-validate').style.display = 'block';
            }
            this.addressModal.viewModel.isLookup(false);

            this.toggleSubmit(false);
        },

        /**
         * Show lookup form
         */
        showLookupForm: function () {
            this.addressModal.toggleNewAddAddressForm(true);
        },

        /**
         * Show existing selector
         */
        showExistingSelector: function () {
            this.addressModal.toggleNewAddAddressForm(false);
            if (this.addressModal.toggleNewAddAddressForm) {
                document.getElementById('form-validate').style.display = 'none';
            }

            this.toggleSubmit(false);
        }
    });
});
