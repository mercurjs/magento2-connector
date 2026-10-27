define([
    'Mercur_Frontend/js/model/marketplace-shipping-total'
], function (marketplaceShipping) {
    'use strict';

    /**
     * One "Shipping" line in the totals: the operator's shipping plus what the sellers charge,
     * without the placeholder carrier's title when only sellers ship.
     */
    return function (Component) {
        return Component.extend({
            getShippingMethodTitle: function () {
                return marketplaceShipping.isPlaceholder() ? '' : this._super();
            },

            getValue: function () {
                if (!this.isCalculated()) {
                    return this._super();
                }

                return this.getFormattedPrice(
                    parseFloat(this.totals()['shipping_amount'] || 0) + marketplaceShipping.amount()
                );
            },

            getExcludingValue: function () {
                if (!this.isCalculated()) {
                    return this._super();
                }

                return this.getFormattedPrice(
                    parseFloat(this.totals()['shipping_amount'] || 0) + marketplaceShipping.amount()
                );
            },

            getIncludingValue: function () {
                if (!this.isCalculated()) {
                    return this._super();
                }

                return this.getFormattedPrice(
                    parseFloat(this.totals()['shipping_incl_tax'] || 0) + marketplaceShipping.amount()
                );
            }
        });
    };
});
