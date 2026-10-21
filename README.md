<br />
<p align="center">
  <a href="https://mercurjs.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo_dark.svg">
      <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo.svg">
      <img alt="Mercur" width="160" src="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo.svg">
    </picture>
  </a>

  <h1 align="center">Mercur Magento 2 Connector</h1>

  <p align="center">
    Sell marketplace offers from your Magento 2 store, next to your own products.
    <br />
    <a href="https://docs.mercurjs.com">Documentation</a>
    ·
    <a href="https://github.com/mercurjs/magento2-connector/issues/new?labels=enhancement">Request Features</a>
    ·
    <a href="https://github.com/mercurjs/magento2-connector/issues/new?labels=bug">Report Bugs</a>
    <br />
  </p>
</p>

## Status

[![License](https://img.shields.io/github/license/mercurjs/magento2-connector)](LICENSE)
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

Watch [releases](https://github.com/mercurjs/magento2-connector/releases) of this repo to get notified of updates.

## Overview

### What is this?

This is a Composer package of Magento 2 modules that connects a Magento Open Source or Adobe Commerce store to a [Mercur](https://mercurjs.com) marketplace. Magento keeps the storefront, cart, checkout, payment and your own catalog. Mercur keeps sellers, their offers, the split into one order per seller, commissions and payouts. The connector mirrors sellers and offers into Magento and talks to Mercur at the four points where the two must agree:

- Offers: seller offers are synced by SKU and indexed into a best offer per website
- Checkout: marketplace lines drive a shadow Mercur cart with per-seller shipping, then the captured payment completes it into an order group
- Webhooks: seller shipments, cancellations and catalog changes arrive signed at `/mercur/webhook`
- Taxonomy: your categories and attributes are pushed so sellers submit against them

For a more detailed overview head over to the [Mercur docs](https://docs.mercurjs.com).

### Does Magento still own first-party sales?

Yes. A cart line without `mercur_offer_id` is a normal Magento line with your price, stock and carriers. Only seller lines skip Magento's stock checks, because the seller owns that stock.

## Quick start

```bash
composer require mercurjs/magento2-connector
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl
bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush
```

Fill in the connection under **Stores > Configuration > Mercur > Marketplace** (API URL, admin and publishable keys, sales channel, region, webhook secret), register `https://your_shop_domain/mercur/webhook` in Mercur under **Settings > Magento**, then run the first sync:

```bash
bin/magento mercur:sync taxonomy
bin/magento mercur:sync full
```

Cron keeps the mirror current after that. Every run shows up under **Mercur > Processes**.

## Developers

```bash
composer install --ignore-platform-reqs
composer lint
composer test
```

The package needs a Mercur API running `@mercurjs/magento2-connector-plugin` with its external payment provider registered.

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
