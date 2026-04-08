/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'scriptLoader'
], function (Component, loadSdkScript) {
    'use strict';

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypal',
            renderContainer: null,
            amount: null,
            message: null
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.sdkLoaded = loadSdkScript(this.scriptParams, this.sdkNamespace);

            return this;
        },

        /**
         * Update amount
         *
         * @param {*} amount
         */
        updateAmount: function (amount) {
            this.amount = amount;
            if (this.message) {
                this.message.dataset.ppAmount = this.amount;
            }
        },

        /**
         * Render message
         *
         * @return {Promise}
         */
        render: function () {
            return this.sdkLoaded.then(function () {
                const styles = this.getStyles();

                if (!styles) {
                    return;
                }

                this.message = document.createElement('div');
                this.message.dataset.ppMessage = true;
                this.message.dataset.ppStyleLayout = styles.layout || '';
                this.message.dataset.ppStyleLogoPosition = styles.logo?.position || '';
                this.message.dataset.ppStyleLogoType = styles.logo?.type || '';
                this.message.dataset.ppStyleTextAlign = styles.text?.align || '';
                this.message.dataset.ppStyleTextColor = styles.text?.color || '';
                this.message.dataset.ppStyleTextSize = styles.text?.size || '';
                this.message.dataset.ppAmount = this.amount;
                this.message.dataset.ppPlacement = this.placement;

                const container = document.querySelector(this.renderContainer);
                if (container) {
                    container.append(this.message);
                }
            }.bind(this)).catch(function (exception) {
                console.log('Error: Failed to load PayPal SDK script!');
                console.log(exception.message);
            });
        },

        /**
         * Gets the Pay Later Message styling for the given placement.
         *
         * Returns null if the placement is disabled.
         *
         * @returns {Object|bool}
         */
        getStyles: function () {
            if (!this.styles) {
                return {};
            }

            let parsedStyles = JSON.parse(this.styles),
                placement = this.placement === 'payment' ? 'checkout' : this.placement,
                styles = parsedStyles[placement];

            if (!styles) {
                return false;
            }

            return styles;
        }
    });
});
