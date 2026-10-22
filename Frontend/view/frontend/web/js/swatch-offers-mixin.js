define(['jquery', 'Mercur_Frontend/js/offers-filter'], function ($, filterOffers) {
    'use strict';

    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {
            _UpdatePrice: function () {
                this._super();
                filterOffers(this.getProduct() || null);
            }
        });

        return $.mage.SwatchRenderer;
    };
});
