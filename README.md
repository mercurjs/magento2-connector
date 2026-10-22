<a href="https://mercurjs.com">
  <img alt="Mercur Magento 2 Connector" src=".github/banner.png" width="100%">
</a>

<p align="center">
    Sell marketplace offers from your Magento 2 store, next to your own products.
    <br />
    <a href="https://docs.mercurjs.com/resources/integrations/magento2">Documentation</a>
    ·
    <a href="https://github.com/mercurjs/mercur/issues/new?template=feature_request.md">Request Features</a>
    ·
    <a href="https://github.com/mercurjs/mercur/issues/new?template=bug_report.md">Report Bugs</a>
    <br />
</p>

## Status

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml/badge.svg)](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml)

| Features | Status |
| --- | --- |
| Seller offers next to your own stock, with a buy box | ✔ |
| Prices per website from its Mercur region, with price list strike-through and quantity tiers | ✔ |
| Seller-only SKUs stay buyable when you are out of stock | ✔ |
| "Sold by" on every cart, checkout, order, email and PDF line | ✔ |
| Cart and checkout grouped by seller, with a shipping choice per seller | ✔ |
| Estimated delivery dates with order cut-off time | ✔ |
| One Mercur order per seller from a single payment | ✔ |
| Seller shipments, partial and multi-parcel, with tracking | ✔ |
| Delivery, return and cancellation updates from sellers | ✔ |
| Refunds started in Mercur executed as Magento credit memos | ✔ |
| Magento refunds reported to Mercur, marketplace shipping included | ✔ |
| Seller pages | ✔ |
| Sellers, offers and marketplace order details in the Magento admin | ✔ |
| Multi-Source Inventory | ✔ |
| Seller products imported as configurable products | ✔ |
| Taxonomy push | ✔ |
| GraphQL and REST: offers, buying from a seller, seller shipping, seller pages | ✔ |

Watch [Mercur releases](https://github.com/mercurjs/mercur/releases) to get notified of updates.

## Overview

### What is this?

This is a Composer package of Magento 2 modules that connects a Magento Open Source or Adobe Commerce store to a [Mercur](https://mercurjs.com) marketplace. Magento keeps the storefront, cart, checkout, payment and your own catalog. Mercur keeps sellers, their offers, the split into one order per seller, commissions and payouts.

For a more detailed overview head over to the [Magento 2 integration guide](https://docs.mercurjs.com/resources/integrations/magento2).

### Does Magento still own first-party sales?

Yes. Your own products sell exactly as before, with your prices, your stock and your carriers. Lines bought from a seller skip Magento's stock checks, reservations and source deduction, because the seller owns that stock, and your carriers only quote your own lines. A cart of seller lines only gets the "Shipped by the sellers" method, and each seller's shipping is charged in a separate "Seller shipping" total.

### What is not supported yet?

- Buyer and seller messaging, order incidents and seller ratings
- Documents sellers attach to orders
- A third-party tax engine for seller lines (Magento taxes them with the product's tax class)
- Mercur promotions (Magento cart rules still apply to seller lines)
- Shipping one order to several addresses

## Quick start

```bash
composer require mercurjs/magento2-connector
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl
# Stores running Multi-Source Inventory also enable:
bin/magento module:enable Mercur_Inventory
bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush
```

Set **Stores > Configuration > Mercur > Marketplace > Connection > Mercur region ID** on each website that sells offers; that website is priced from that region and its base currency must match the region's. Subscribe the Mercur webhook endpoint to every event, including `shipment.created`, `delivery.created`, `order.return_requested`, `order.return_received` and `order.refund_requested`.

Then follow the [setup guide](https://docs.mercurjs.com/resources/integrations/magento2) to install the Mercur plugin, connect the two and run the first sync.

## Developers

```bash
composer install --ignore-platform-reqs
composer lint
composer analyse
composer test
```

The package needs a Mercur API running [`@mercurjs/magento2-connector-plugin`](https://github.com/mercurjs/mercur/tree/main/packages/plugins/magento2-connector) with its external payment provider registered.

| Module | Responsibility |
| --- | --- |
| `Mercur_Core` | Configuration, API client, logger |
| `Mercur_Process` | Process table, runner, cron, `mercur:sync` command, admin grid |
| `Mercur_Connector` | Seller and offer mirror with prices per website, offer lines on every add-to-cart path, shadow cart, marketplace carrier and shipping totals, order placement, fulfillment and return sync, admin sellers and offers grids |
| `Mercur_OfferIndexer` | Best offer per SKU and website |
| `Mercur_Catalog` | Taxonomy push, published product import as simple or configurable products, stock from offers |
| `Mercur_Settlement` | Credit memos reported to Mercur; refunds and cancellations from Mercur executed in Magento |
| `Mercur_Frontend` | Product page offers, cart and checkout seller panels, seller pages, marketplace orders in the customer account |
| `Mercur_GraphQl` | Offers and buy box on products, `mercur_offer_id` on cart items, seller shipping on carts, marketplace data on order items, `mercurSeller` |
| `Mercur_Inventory` | Optional, for Multi-Source Inventory: keeps seller lines out of salable quantity checks, reservations and source deduction |

## Magento compatibility

| Platform | Version | Status |
| --- | --- | --- |
| Magento Open Source | 2.4.4 and later | Supported |
| Adobe Commerce | 2.4.4 and later | Supported |
| PHP | 8.1, 8.2, 8.3 | Supported |
| Mercur | 2.3.8 and later | Required |

## Contributing

Open an issue before a large change. Pull requests need a green CI run and a note on how you verified the change on a store.

## License

This repo is licensed under MIT.
