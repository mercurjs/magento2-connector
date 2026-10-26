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
    <a href="https://github.com/mercurjs/magento2-connector/issues/new">Report Bugs</a>
    <br />
</p>

## Status

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml/badge.svg)](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml)

| Feature | Status |
| --- | --- |
| Seller offers next to your own stock, with a buy box and seller pages | GA |
| Cart and checkout grouped by seller, shipping choice and delivery estimate per seller | GA |
| One Mercur order per seller from a single payment; seller shipments with tracking | GA |
| Refunds, returns and cancellations in both directions | GA |
| Seller products imported as configurable products, images included | GA |
| Your products pushed to Mercur so sellers can offer on them | Opt-in |
| Taxonomy push, EAN/UPC matching, Multi-Source Inventory, GraphQL and REST | GA |

Watch [releases](https://github.com/mercurjs/magento2-connector/releases) to get notified of updates.

## Overview

### What is this?

A Composer package of Magento 2 modules that connects a Magento Open Source or Adobe Commerce store to a [Mercur](https://mercurjs.com) marketplace.

- Magento keeps the storefront, cart, checkout, payment and your own catalog.
- Mercur keeps sellers, their offers, the split into one order per seller, commissions and payouts.
- The connector mirrors sellers, offers and seller products into Magento, places marketplace orders in Mercur and keeps shipments and refunds in step.

For a more detailed overview head over to the [Magento 2 integration guide](https://docs.mercurjs.com/resources/integrations/magento2).

### Does Magento still own first-party sales?

Yes. Your own products sell exactly as before, with your prices, your stock and your carriers. Seller lines are priced by the seller, skip Magento's stock checks and reservations, and are shipped by the seller. By default they cannot be shipped from the Magento admin.

### How does an order flow?

The order is placed in Magento first. Once it reaches a configured status it is created in Mercur with the payment authorized, the capture is confirmed when an invoice is paid, and each seller ships their own lines from Mercur; the Magento order completes when every seller shipment has been recorded. [ARCHITECTURE.md](ARCHITECTURE.md) has the full picture.

## Quick start

```bash
composer require mercurjs/magento2-connector
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl
bin/magento module:enable Mercur_Inventory   # stores running Multi-Source Inventory
bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush
```

Then follow the [setup guide](https://docs.mercurjs.com/resources/integrations/magento2) to install the Mercur plugin, connect the two stores and run the first sync.

## Developers

Start with [DEVELOPERS.md](DEVELOPERS.md) for local setup, the test suite and trying a change on a store.

Once you are set up, these cover the rest:

- [ARCHITECTURE.md](ARCHITECTURE.md) — modules, sync tasks, the process queue and how an order moves between the two systems
- [CONFIGURATION.md](CONFIGURATION.md) — every setting, its path and the scopes it accepts
- [EVENTS.md](EVENTS.md) — Magento events you can observe and the Mercur webhooks the connector handles

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
