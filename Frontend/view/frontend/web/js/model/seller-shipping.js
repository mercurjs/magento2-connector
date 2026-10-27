define([
    'jquery',
    'ko',
    'underscore',
    'mage/url'
], function ($, ko, _, urlBuilder) {
    'use strict';

    var sellers = ko.observableArray([]),
        loading = ko.observable(false);

    function wrap(seller) {
        var chosen = _.findWhere(seller.options, {chosen: true});

        seller.chosenId = ko.observable(chosen ? chosen.id : null);
        seller.chosenOption = ko.pureComputed(function () {
            return _.findWhere(seller.options, {id: seller.chosenId()}) || null;
        });

        return seller;
    }

    return {
        sellers: sellers,
        loading: loading,

        load: _.debounce(function () {
            loading(true);
            $.getJSON(urlBuilder.build('mercur/shipping/options'))
                .done(function (response) {
                    sellers(_.map(response.sellers || [], wrap));
                })
                .always(function () {
                    loading(false);
                });
        }, 250),

        /**
         * "Sole Society: Standard Shipping" per seller, for the places Magento shows one shipping method title.
         */
        describe: function () {
            return _.map(sellers(), function (seller) {
                var option = seller.chosenOption();

                return option ? seller.seller_name + ': ' + option.name : seller.seller_name;
            });
        }
    };
});
