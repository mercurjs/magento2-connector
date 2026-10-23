<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Framework\DataObject;

/**
 * The connector's own columns that travel from the quote to the order when
 * it is placed. Magento's quote-to-order converters only keep the fields of
 * the sales interfaces, so a fieldset cannot carry them; they are copied by
 * CopyMarketplaceFieldsToOrder instead.
 */
final class MarketplaceFields
{
    public const ORDER = ['mercur_cart_id', 'mercur_shipping_methods'];

    public const ADDRESS = [
        'mercur_shipping_amount',
        'base_mercur_shipping_amount',
        'mercur_shipping_tax_amount',
        'base_mercur_shipping_tax_amount',
        'mercur_shipping_incl_tax',
        'base_mercur_shipping_incl_tax',
    ];

    public const ITEM = ['mercur_offer_id', 'mercur_seller_id', 'mercur_line_item_id'];

    /**
     * @param string[] $fields
     */
    public static function copy(DataObject $source, DataObject $target, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $source->getData($field);
            if ($value !== null) {
                $target->setData($field, $value);
            }
        }
    }
}
