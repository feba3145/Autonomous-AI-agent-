define(['Magento_PaymentServicesPaypal/js/lib/script-loader'], function (scriptLoader) {
    'use strict';

    class PaymentSdkLoader {
        constructor() {
            this._preferredUrl = null;
            this._fallbackUrl = null;
            this._gqlEndpoint = null;
            this._customerToken = null;
            this._storeViewCode = null;
            this._paymentLocation = null;
            this._shouldInitVault = false;
        }

        /**
         * [Required] Sets the preferred URL to fetch the PaymentSDK.js file from.
         *
         * @param preferredUrl {string}
         */
        withPreferredUrl(preferredUrl) {
            this._preferredUrl = preferredUrl;
            return this;
        }

        /**
         * [Optional] Sets the fallback URL to fetch the PaymentSDK.js file from.
         *
         * @param fallbackUrl {string | null}
         */
        withFallbackUrl(fallbackUrl) {
            this._fallbackUrl = fallbackUrl;
            return this;
        }

        /**
         * [Optional] Adobe Commerce GraphQL API URL, e.g., "https://magento.test/graphql". When not set, this defaults
         * to "/graphql".
         *
         * @param graphqlEndpoint {string}
         */
        withGraphqlEndpoint(graphqlEndpoint) {
            this._gqlEndpoint = graphqlEndpoint;
            return this;
        }

        /**
         * [Optional] Adobe Commerce GraphQL customer authorization token.
         *
         * @param graphqlCustomerToken {string | null}
         */
        withGraphqlCustomerToken(graphqlCustomerToken) {
            this._customerToken = graphqlCustomerToken;
            return this;
        }

        /**
         * [Optional] The Adobe Commerce store view code on which the Payment Services SDK should perform GraphQL
         * requests. When not set, no 'Store' header will be sent.
         *
         * @param storeViewCode {string}
         */
        forStoreView(storeViewCode) {
            this._storeViewCode = storeViewCode;
            return this;
        }

        /**
         * [Optional] Call this to initialize the 'sdk.Payment' namespace as part of the load.
         *
         * @param location {string} payment method location ("CHECKOUT", "PRODUCT_DETAIL", "MINICART", or "CART")
         */
        withPaymentNamespace(location) {
            this._paymentLocation = location;
            return this;
        }

        /**
         * [Optional] Call this to initialize the 'sdk.Vault' namespace as part of the load.
         */
        withVaultNamespace() {
            this._shouldInitVault = true;
            return this;
        }

        load() {
            const pullScriptParams = this._constructPullScriptParams();
            const initializeSdkParams = this._constructInitializeSdkParams();
            return load(pullScriptParams, initializeSdkParams);
        }

        _constructPullScriptParams() {
            if (!this._preferredUrl) {
                throw new Error("Missing required argument: 'preferredUrl'.");
            }

            return {
                preferredUrl: this._preferredUrl,
                fallbackUrl: this._fallbackUrl || null,
            };
        }

        _constructInitializeSdkParams() {
            return {
                gqlEndpoint: this._gqlEndpoint || null,
                customerToken: this._customerToken || null,
                storeViewCode: this._storeViewCode || null,
                paymentLocation: this._paymentLocation || null,
                shouldInitVault: !!this._shouldInitVault,
            };
        }
    }

    ////////////
    /// Load ///
    ////////////

    function load(pullScriptParams, initializeSdkParams) {
        const memoKey = createLoadMemoKey(pullScriptParams, initializeSdkParams);
        if (!(memoKey in loadMemo)) {
            loadMemo[memoKey] = doLoad(pullScriptParams, initializeSdkParams);
        }
        return loadMemo[memoKey];
    }

    const loadMemo = {}; // memoized calls to load()

    function createLoadMemoKey(pullScriptParams, initializeSdkParams) {
        const pullScriptParamsJson = objToSortedJsonString(pullScriptParams);
        const initSdkParamsJson = objToSortedJsonString(initializeSdkParams);
        return `{pullScriptParams:${pullScriptParamsJson},initializeSdkParams:${initSdkParamsJson}}`;
    }

    async function doLoad(pullScriptParams, initializeSdkParams) {
        const PaymentServicesSDK = await pullScript(pullScriptParams);
        return await initializeSdk(PaymentServicesSDK, initializeSdkParams);
    }

    ///////////////////////
    /// Pull SDK script ///
    ///////////////////////

    /**
     * null or {
     *   preferredUrl: string,
     *   fallbackUrl: string|null,
     *   promise: Promise<{PaymentServicesSDK: typeof PaymentServicesSDK}>
     * }
     */
    let cachedPullScriptResult = null;

    function pullScript(pullScriptParams) {
        if (!cachedPullScriptResult) {
            cachedPullScriptResult = doPullScript(pullScriptParams);
        }
        if (pullScriptParams.preferredUrl !== cachedPullScriptResult.preferredUrl
            || pullScriptParams.fallbackUrl !== cachedPullScriptResult.fallbackUrl) {
            const urls = (data) => `urls(preferred = ${data.preferredUrl}, fallback = ${data.fallbackUrl})`;
            console.warn(
                `Ignoring request to pull Payment Services SDK script from ${urls(pullScriptParams)}, as `
                + `loading multiple versions of the Payment Services SDK script on the same page is not supported, `
                + `and the Payment Services SDK script was already loaded from ${urls(cachedPullScriptResult)}.`
            );
        }
        return cachedPullScriptResult.promise;
    }

    function doPullScript({preferredUrl, fallbackUrl}) {
        const promise = scriptLoader.loadCustom({url: preferredUrl})
            .catch((error) => {
                if (fallbackUrl) {
                    console.warn(
                        `Failed to load Payment Services SDK script from preferred URL ${preferredUrl}. Retrying with fallback URL ${fallbackUrl}.`,
                        error,
                    );
                } else {
                    console.warn(
                        `Failed to load Payment Services SDK script from preferred URL ${preferredUrl}. Retrying with same URL as no fallback URL was provided.`,
                        error,
                    );
                }
                return scriptLoader.loadCustom({url: fallbackUrl || preferredUrl});
            })
            .then(() => {
                if ("PaymentServicesSDK" in window) {
                    return {
                        PaymentServicesSDK: window.PaymentServicesSDK
                    };
                } else {
                    throw new Error("Script loaded, but Payment Services SDK not found.");
                }
            });

        return {preferredUrl, fallbackUrl, promise};
    }

    //////////////////////
    /// Initialize SDK ///
    //////////////////////

    function initializeSdk(paymentServicesScript, initializeSdkParams) {
        const memoKey = createInitializeSdkMemoKey(initializeSdkParams);
        if (!(memoKey in initializeSdkMemo)) {
            initializeSdkMemo[memoKey] = doInitializeSdk(paymentServicesScript, initializeSdkParams);
        }
        return initializeSdkMemo[memoKey];
    }

    const initializeSdkMemo = {}; // memoized calls to initializeSdk
    const createInitializeSdkMemoKey = objToSortedJsonString;

    function doInitializeSdk({PaymentServicesSDK}, initializeSdkParams) {
        const sdkConfig = constructSdkConfig(initializeSdkParams);
        const sdk = new PaymentServicesSDK(sdkConfig);

        const namespaceInitFutures = [];
        if (initializeSdkParams.paymentLocation) {
            namespaceInitFutures.push(sdk.Payment.init({
                location: initializeSdkParams.paymentLocation,
            }));
        }
        if (initializeSdkParams.shouldInitVault) {
            namespaceInitFutures.push(sdk.Vault.init());
        }

        return Promise
            .all(namespaceInitFutures)
            .then(() => sdk);
    }

    function constructSdkConfig(initializeSdkParams) {
        const sdkConfig = {};

        if (initializeSdkParams.gqlEndpoint) {
            sdkConfig["apiUrl"] = initializeSdkParams.gqlEndpoint;
        }

        if (initializeSdkParams.customerToken) {
            sdkConfig["getCustomerToken"] = () => initializeSdkParams.customerToken;
        }

        if (initializeSdkParams.storeViewCode) {
            sdkConfig["storeViewCode"] = initializeSdkParams.storeViewCode;
        }

        return sdkConfig;
    }

    function objToSortedJsonString(obj) {
        return JSON.stringify(obj, Object.keys(obj).sort());
    }

    return () => new PaymentSdkLoader();
});
