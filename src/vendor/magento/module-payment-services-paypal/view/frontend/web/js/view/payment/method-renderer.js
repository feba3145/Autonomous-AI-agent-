/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_PaymentServicesPaypal/js/helpers/is-fastlane-available'
], function (Component, rendererList, isFastlaneAvailable) {
    'use strict';

    const cardField = isFastlaneAvailable() ? {
        type: 'payment_services_paypal_fastlane',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/fastlane'
    } : {
        type: 'payment_services_paypal_hosted_fields',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/hosted-fields'
    };

    rendererList.push({
        type: 'payment_services_paypal_smart_buttons',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/smart-buttons'
    }, {
        type: 'payment_services_paypal_apple_pay',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/apple-pay'
    }, {
        type: 'payment_services_paypal_google_pay',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/google-pay'
    }, {
        type: 'payment_services_paypal_apm',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/apm'
    }, cardField);

    return Component.extend({});
});
