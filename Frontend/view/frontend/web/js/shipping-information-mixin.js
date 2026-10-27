define([
    'Mercur_Frontend/js/model/seller-shipping',
    'Mercur_Frontend/js/model/marketplace-shipping-total'
], function (sellerShipping, marketplaceShipping) {
    'use strict';

    /**
     * The "Shipping Method" box on the payment step names each seller's method
     * instead of the placeholder carrier.
     */
    return function (Component) {
        return Component.extend({
            getShippingMethodTitle: function () {
                var parts = sellerShipping.describe(),
                    own = marketplaceShipping.isPlaceholder() ? '' : this._super();

                if (own) {
                    parts.unshift(own);
                }

                return parts.join(', ');
            }
        });
    };
});
