/**
 * @api
 */
define(
    [
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (storage, errorProcessor, fullScreenLoader) {
        'use strict';

        return function (serviceUrl, payload, messageContainer) {
            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl, JSON.stringify(payload)
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                }
            ).always(
                function () {
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
