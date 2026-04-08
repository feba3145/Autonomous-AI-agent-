define(['mage/storage'], function (storage) {
    'use strict';

    return function () {
        return storage.get("rest/V1/directory/countries").then(function (result) {
            const countryDirectory = {};
            const countryList = {};
            let i, data, x, region;
            for (i = 0; i < result.length; ++i) {
                data = result[i];
                countryDirectory[data.two_letter_abbreviation] = {};
                countryList[data.two_letter_abbreviation] = {};
                if (typeof data.available_regions !== 'undefined') {
                    for (x = 0; x < data.available_regions.length; ++x) {
                        region = data.available_regions[x];
                        countryDirectory[data.two_letter_abbreviation][region.name.toLowerCase().replace(/[^A-Z0-9]/ig, '')] = region.id;
                        countryList[data.two_letter_abbreviation][region.code] = region.id;
                    }
                }
            }

            return {countryDirectory, countryList};
        }.bind(this));
    };
});
