define(['jquery'], function ($) {
    'use strict';

    var HIDDEN = 'mercur-offer--hidden';

    /**
     * Shows the offer rows of the selected variant and, when the operator
     * cannot sell that variant, points the main add-to-cart form at its best
     * seller offer.
     *
     * @param {String|Number|null} childId
     */
    return function (childId) {
        var $offers = $('#mercur-offers'),
            children = $offers.data('mercurChildren') || {},
            child = childId ? children[String(childId)] : null,
            $input = $('#product_addtocart_form input[name="mercur_offer_id"]');

        if (!$offers.length) {
            return;
        }

        $offers.find('[data-child-id]').each(function () {
            var $row = $(this),
                visible = !childId || String($row.data('childId')) === String(childId);

            $row.toggleClass(HIDDEN, !visible).attr('aria-hidden', visible ? null : 'true');
        });

        if (child && String(child.operator_available) === '0' && child.best_offer_id) {
            $input.val(child.best_offer_id);
        } else {
            $input.val('');
        }
    };
});
