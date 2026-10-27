define([
    'jquery',
    'uiComponent',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/get-totals',
    'Mercur_Frontend/js/model/seller-shipping',
    'mage/cookies'
], function ($, Component, urlBuilder, quote, getTotals, sellerShipping) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mercur_Frontend/seller-shipping',
            readonly: false
        },

        initialize: function () {
            this._super();
            this.sellers = sellerShipping.sellers;
            this.loading = sellerShipping.loading;

            quote.totals.subscribe(sellerShipping.load);
            sellerShipping.load();

            return this;
        },

        choose: function (seller, option) {
            if (this.readonly || seller.chosenId() === option.id) {
                return true;
            }
            seller.chosenId(option.id);
            $.post(urlBuilder.build('mercur/shipping/choose'), {
                'seller_id': seller.seller_id,
                'option_id': option.id,
                'form_key': $.mage.cookies.get('form_key')
            }).done(function () {
                getTotals([]);
            });

            return true;
        }
    });
});
