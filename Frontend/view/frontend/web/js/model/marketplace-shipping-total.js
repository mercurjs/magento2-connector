define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals'
], function (quote, totals) {
    'use strict';

    var PLACEHOLDER = 'mercur_mercur';

    return {
        amount: function () {
            var segment = totals.getSegment('mercur_shipping');

            return segment ? parseFloat(segment.value) || 0 : 0;
        },

        /**
         * The cart holds only seller lines, so the selected Magento method is the zero-priced placeholder carrier.
         */
        isPlaceholder: function () {
            var method = quote.shippingMethod();

            return !!method && method.carrier_code + '_' + method.method_code === PLACEHOLDER;
        }
    };
});
