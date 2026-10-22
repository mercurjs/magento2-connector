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
| Seller offers next to your own stock | ✔ |
| Mixed cart checkout with per-seller shipping | ✔ |
| One Mercur order per seller from a single payment | ✔ |
| Seller shipments and cancellations back into Magento | ✔ |
| Refunds reported to Mercur | ✔ |
| Taxonomy push and seller product import | ✔ |
| GraphQL fields on `ProductInterface` | ✔ |

Watch [Mercur releases](https://github.com/mercurjs/mercur/releases) to get notified of updates.

## Overview

### What is this?

This is a Composer package of Magento 2 modules that connects a Magento Open Source or Adobe Commerce store to a [Mercur](https://mercurjs.com) marketplace. Magento keeps the storefront, cart, checkout, payment and your own catalog. Mercur keeps sellers, their offers, the split into one order per seller, commissions and payouts.

For a more detailed overview head over to the [Magento 2 integration guide](https://docs.mercurjs.com/resources/integrations/magento2).

### Does Magento still own first-party sales?

Yes. Your own products sell exactly as before, with your prices, your stock and your carriers. Only lines bought from a seller skip Magento's stock checks, because the seller owns that stock.

## Quick start

```bash
composer require mercurjs/magento2-connector
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl
bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush
```

Then follow the [setup guide](https://docs.mercurjs.com/resources/integrations/magento2) to install the Mercur plugin, connect the two and run the first sync.

## Developers

```bash
composer install --ignore-platform-reqs
composer lint
composer test
```

The package needs a Mercur API running [`@mercurjs/magento2-connector-plugin`](https://github.com/mercurjs/mercur/tree/main/packages/plugins/magento2-connector) with its external payment provider registered.

| Module | Responsibility |
| --- | --- |
| `Mercur_Core` | Configuration, API client, logger |
| `Mercur_Process` | Process table, runner, cron, `mercur:sync` command, admin grid |
| `Mercur_Connector` | Seller and offer mirror, shadow cart, marketplace shipping, order placement, webhooks |
| `Mercur_OfferIndexer` | Best offer per SKU and website |
| `Mercur_Catalog` | Taxonomy push, published product import |
| `Mercur_Settlement` | Credit memos reported to Mercur |
| `Mercur_Frontend` | Product page offers block, shipping option endpoints |
| `Mercur_GraphQl` | `mercur_offers` and `mercur_buy_box` on `ProductInterface` |

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
