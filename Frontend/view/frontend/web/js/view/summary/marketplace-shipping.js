define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals'
], function (Component, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mercur_Frontend/summary/marketplace-shipping'
        },

        segment: function () {
            return totals.getSegment('mercur_shipping');
        },

        isDisplayed: function () {
            var segment = this.segment();

            return !!segment && parseFloat(segment.value) > 0;
        },

        getValue: function () {
            var segment = this.segment();

            return this.getFormattedPrice(segment ? segment.value : 0);
        }
    });
});
