var config = {
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Mercur_Frontend/js/configurable-offers-mixin': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'Mercur_Frontend/js/swatch-offers-mixin': true
            },
            'Magento_Checkout/js/view/summary/shipping': {
                'Mercur_Frontend/js/summary-shipping-mixin': true
            },
            'Magento_Checkout/js/view/shipping-information': {
                'Mercur_Frontend/js/shipping-information-mixin': true
            }
        }
    }
};
