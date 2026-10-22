define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/get-totals',
    'mage/cookies'
], function ($, ko, _, Component, urlBuilder, quote, getTotals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mercur_Frontend/seller-shipping',
            readonly: false
        },

        initialize: function () {
            this._super();
            this.sellers = ko.observableArray([]);
            this.loading = ko.observable(false);
            this.refresh = _.debounce(this.load.bind(this), 250);

            quote.totals.subscribe(this.refresh);
            this.load();

            return this;
        },

        load: function () {
            var self = this;

            this.loading(true);
            $.getJSON(urlBuilder.build('mercur/shipping/options'))
                .done(function (response) {
                    self.sellers(_.map(response.sellers || [], function (seller) {
                        var chosen = _.findWhere(seller.options, {chosen: true});

                        seller.chosenId = ko.observable(chosen ? chosen.id : null);
                        seller.chosenOption = ko.pureComputed(function () {
                            return _.findWhere(seller.options, {id: seller.chosenId()}) || null;
                        });

                        return seller;
                    }));
                })
                .always(function () {
                    self.loading(false);
                });
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
