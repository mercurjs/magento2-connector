define(['jquery', 'Mercur_Frontend/js/offers-filter'], function ($, filterOffers) {
    'use strict';

    return function (widget) {
        $.widget('mage.configurable', widget, {
            _reloadPrice: function () {
                this._super();
                filterOffers(this.simpleProduct || null);
            }
        });

        return $.mage.configurable;
    };
});
