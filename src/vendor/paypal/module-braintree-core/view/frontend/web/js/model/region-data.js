define([
    'knockout',
    'PayPal_Braintree/js/actions/get-country-directory',
], function (ko, getCountryDirectory) {
    'use strict';

    const _countryDirectory = ko.observable({});
    const _countryList = ko.observable({});

    getCountryDirectory().then(({countryDirectory, countryList}) => {
        _countryDirectory(countryDirectory);
        _countryList(countryList);
    });

    return {
        getCountryDirectory: function () {
            return _countryDirectory();
        },

        getCountryList: function () {
            return _countryList();
        },

        /**
         * Get Region ID
         *
         * @param countryCode
         * @param regionName
         * @returns {number|*|null}
         */
        getRegionId: function (countryCode, regionName) {
            if (typeof regionName !== 'string') {
                return null;
            }

            regionName = regionName.toLowerCase().replace(/[^A-Z0-9]/ig, '');

            if (typeof this.getCountryDirectory()[countryCode] !== 'undefined'
                && typeof this.getCountryDirectory()[countryCode][regionName] !== 'undefined')
            {
                return this.getCountryDirectory()[countryCode][regionName];
            }

            return 0;
        },

        /**
         * Get Region ID by region code
         *
         * @param countryCode
         * @param regionCode
         * @returns {number|*|null}
         */
        getRegionIdByCode: function (countryCode, regionCode) {
            if (typeof regionCode !== 'string') {
                return null;
            }

            if (typeof this.getCountryList()[countryCode] !== 'undefined'
                && typeof this.getCountryList()[countryCode][regionCode] !== 'undefined')
            {
                return this.getCountryList()[countryCode][regionCode];
            }

            return 0;
        },

    };
});
