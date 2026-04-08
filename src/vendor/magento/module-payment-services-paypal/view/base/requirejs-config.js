/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    map: {
        '*': {
            scriptLoader: 'Magento_PaymentServicesPaypal/js/lib/script-loader-wrapper',
            paymentSdkLoader: 'Magento_PaymentServicesPaypal/js/lib/payment-sdk-loader',
        }
    },
    shim: {
        'Magento_PaymentServicesPaypal/js/lib/script-loader': {
            init: function () {
                'use strict';

                return {
                    load: window.paypalLoadScript,
                    loadCustom: window.paypalLoadCustomScript
                };
            }
        }
    }
};
