define([
    'jquery',
    'ko',
    'mage/url'
], function ($, ko, urlBuilder) {
    'use strict';

    return {
        viewModel: {
            visible: ko.observable(false),
            newAddressFormVisible: ko.observable(false),
            selectExistingVisible: ko.observable(false),
            currentAddresses: ko.observableArray([]),
            currentShippingId: ko.observable(null),
            useForSelected: ko.observable(false),
            saveAddressDisabled: ko.observable(true),
            confirmationVisibleType: ko.observable(null),
            defaultForAllAddressId: ko.observable(null),
            isLookup: ko.observable(true),
            newAddress: {
                firstName: document.getElementById('firstname').value,
                lastName: document.getElementById('lastname').value,
                street: document.getElementById('street_1').value,
                street2: document.getElementById('street_2').value,
                city: document.getElementById('city').value,
                postcode: document.getElementById('zip').value,
                country: document.getElementById('country').value,
                telephone: document.getElementById('telephone').value,
                region: document.getElementById('region_id').value
            },
            currentCountryId: ko.observable(null),
            countries: ko.observableArray(null)
        },
        validatedPostCodeExample: [],

        /**
         * Show address modal
         *
         * @param storeCode
         */
        showAddressModal: function (storeCode) {
            let self = this;

            this.viewModel.visible(true);
            this.viewModel.selectExistingVisible(true);
            this.viewModel.useForSelected(false);
            this.clearAddressField();

            fetch(urlBuilder.build('graphql'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Store': storeCode
                },
                body: JSON.stringify({
                    query: `{
                        customer {
                            addresses {
                                id,
                                street,
                                country_id,
                                region {
                                    region_code
                                },
                                telephone,
                                postcode,
                                firstname,
                                lastname,
                                city
                            }
                        }
                    }`
                })
            }).then(response => response.json()).then(response => {
                const addresses = response.data.customer?.addresses || [];

                self.viewModel.currentAddresses.removeAll();

                //Get addresses from response and put them in an observable array
                //The template looks at the array and builds the <select> dropdown form
                for (let i = 0; i < addresses.length; i++) {
                    const address = {
                        id: addresses[i].id,
                        firstname: addresses[i].firstname,
                        lastname: addresses[i].lastname,
                        region: {
                            region_code: addresses[i].region.region_code
                        },
                        telephone: addresses[i].telephone,
                        postcode: addresses[i].postcode,
                        country_id: addresses[i].country_id,
                        city: addresses[i].city,
                        street: addresses[i].street.join(', ')
                    };

                    self.viewModel.currentAddresses.push(address);
                }

                self.viewModel.saveAddressDisabled(true);
            });
        },

        /**
         * toggle (show/hide) add new address form
         *
         * @param show
         */
        toggleNewAddAddressForm: function (show) {
            this.viewModel.newAddressFormVisible(show);
            this.viewModel.selectExistingVisible(!show);
            this.viewModel.isLookup(show);
            this.clearAddressField();
        },

        /**
         * Clear address fields
         */
        clearAddressField: function () {
            $('#cc_c2a').remove();
            this.viewModel.currentShippingId(null);
            this.viewModel.newAddress.street = null;
            this.viewModel.newAddress.street = null;
            this.viewModel.newAddress.street2 = null;
            this.viewModel.newAddress.city = null;
            this.viewModel.newAddress.postcode = null;
            this.viewModel.newAddress.telephone = null;
            this.viewModel.newAddress.country = this.viewModel.currentCountryId();
        },

        /**
         * Update current address ID
         */
        updateCurrentAddressId: function () {
            this.viewModel.useForSelected(false);
        }
    };
});
