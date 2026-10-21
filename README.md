<br />
<p align="center">
  <a href="https://mercurjs.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo_dark.svg">
      <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo.svg">
      <img alt="Mercur" width="160" src="https://raw.githubusercontent.com/mercurjs/mercur/main/apps/docs/logo.svg">
    </picture>
  </a>

  <h1 align="center">Mercur connector for Magento 2</h1>

  <p align="center">
    Sell marketplace offers from the Magento storefront you already run, next to your own products.
    <br />
    <a href="https://docs.mercurjs.com">Documentation</a>
    ·
    <a href="https://github.com/mercurjs/magento2-connector/issues/new?labels=enhancement">Request a feature</a>
    ·
    <a href="https://github.com/mercurjs/magento2-connector/issues/new?labels=bug">Report a bug</a>
    <br />
  </p>
</p>

## Status

[![License](https://img.shields.io/github/license/mercurjs/magento2-connector)](LICENSE)
[![CI](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml/badge.svg)](https://github.com/mercurjs/magento2-connector/actions/workflows/ci.yml)

| Capability | Status |
| --- | --- |
| Seller and offer mirror with delta sync | Beta |
| Best offer per SKU and website | Beta |
| Mixed cart checkout with per-seller shipping | Beta |
| Order split into one Mercur order per seller | Beta |
| Seller shipments and cancellations back into Magento | Beta |
| Credit memos reported to Mercur | Beta |
| Taxonomy push and seller product import | Beta |
| GraphQL fields on `ProductInterface` | Beta |

This connector is in its first release cycle. Install it on a staging store, run the flow end to end, and open an issue for anything that does not match this page. Watch [releases](https://github.com/mercurjs/magento2-connector/releases) to get notified of updates.

## Overview

### What is this?

This is a Composer package of eight Magento 2 modules that connect a Magento Open Source or Adobe Commerce store to a [Mercur](https://mercurjs.com) marketplace. Magento keeps what it already does well: the storefront, the cart, checkout, payment capture, and your own catalog. Mercur takes the marketplace work: sellers, their offers, the split of one checkout into one order per seller, commission lines, and payouts. The connector keeps a copy of Mercur's sellers and offers inside Magento and talks to Mercur at the four points where the two systems must agree.

| Concern | Owner |
| --- | --- |
| Category tree, attributes, your own products, stock and prices | Magento |
| Storefront, cart, checkout, customer payment | Magento |
| Sellers, offers, seller product submissions | Mercur |
| Per-seller orders, commission lines, payouts, seller fulfillment | Mercur |

### What happens on a mixed order?

A shopper buys one of your own products and one seller offer in the same checkout and pays once:

1. The product page lists every active seller offer next to your own stock. Adding an offer sends `mercur_offer_id` with the add-to-cart request, and the line is priced by the offer.
2. At the shipping step the connector copies the seller lines into a shadow Mercur cart, asks Mercur for shipping options per seller, applies the cheapest option for each seller unless the shopper chose another, and adds the sum as a “Marketplace shipping” total. Your own carriers keep pricing your own lines.
3. The shopper pays in Magento.
4. After `sales_order_place_after` the connector records the capture on Mercur's external payment provider and completes the cart. Mercur answers with an order group, one order per seller, and the connector stores those ids on the Magento order and its items.
5. Sellers fulfill in the Mercur vendor panel. Shipments and tracking numbers arrive through a signed webhook and become Magento shipments.
6. Mercur computes commission lines when the cart completes and pays sellers from the recorded capture. Magento never computes commissions or payouts.

If Mercur is unreachable at step 4, the Magento order still succeeds and the `order_retry` process completes the Mercur side on its next run.

### Does it change how Magento sells your own products?

No. A line without `mercur_offer_id` is a normal Magento line: your price, your stock, your carriers. The connector only skips Magento's stock validation and stock deduction for seller lines, because the seller owns that stock.

## Get started

You need Magento Open Source or Adobe Commerce 2.4.4 or later, PHP 8.1 or later, and a Mercur API running the `@mercurjs/magento2-connector-plugin` with its external payment provider registered.

Install the package and enable the modules:

```bash
composer require mercurjs/magento2-connector
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Open **Stores > Configuration > Mercur > Marketplace** and fill in the connection:

| Setting | What to enter |
| --- | --- |
| Mercur API URL | The base URL of your Mercur API |
| Admin API key | A secret API key bound to a role that holds the `integrations` permission |
| Publishable API key | The publishable key of the sales channel below |
| Mercur sales channel ID | The sales channel the shadow cart is created in |
| Mercur region ID | The region whose prices this website shows, one per website |
| Signing secret | Shown once in Mercur under **Settings > Magento** when you add the endpoint `https://your_shop_domain/mercur/webhook` |
| Best offer rule | How the buy box chooses between your stock and seller offers |

In Mercur, subscribe the webhook endpoint to the offer, seller, product, and order events. Then push your taxonomy and run the first full sync:

```bash
bin/magento mercur:sync taxonomy
bin/magento mercur:sync full
```

Cron keeps the mirror current from there: sellers every 15 minutes, offers every 5 minutes, published products hourly, and a full resync every night. The schedules live in the same configuration section. Every run is a process you can inspect under **Mercur > Processes**.

## Developers

Install the dev dependencies and run the checks:

```bash
composer install --ignore-platform-reqs
composer lint
composer test
```

Unit tests cover the parts that do not need a Magento install: offer and seller mapping, delta paging, webhook signatures, and best-offer selection. Everything that touches Magento runs against a real store.

The package is one Composer component holding eight modules, each with one job:

| Module | Responsibility |
| --- | --- |
| `Mercur_Core` | Configuration, API client, logger |
| `Mercur_Process` | Process table, runner, cron, the `mercur:sync` command, admin grid |
| `Mercur_Connector` | Seller and offer mirror, delta sync, shadow cart, marketplace shipping total, order placement, webhook receiver |
| `Mercur_OfferIndexer` | Best offer per SKU and website |
| `Mercur_Catalog` | Taxonomy push, published product import |
| `Mercur_Settlement` | Credit memos reported to Mercur |
| `Mercur_Frontend` | Product page offers block, shipping option endpoints |
| `Mercur_GraphQl` | `mercur_offers` and `mercur_buy_box` on `ProductInterface` |

Webhooks arrive at `POST /mercur/webhook`, signed with HMAC-SHA256 in the `x-mercur-signature` header. Handlers are registered per event in `Connector/etc/di.xml`, so a project can add its own without touching the package.

## Compatibility

| Platform | Version | Status |
| --- | --- | --- |
| Magento Open Source | 2.4.4 and later | Supported |
| Adobe Commerce | 2.4.4 and later | Supported |
| PHP | 8.1, 8.2, 8.3 | Supported, tested in CI |
| Mercur | 2.3.8 and later with `@mercurjs/magento2-connector-plugin` | Required |

## Known gaps

The storefront pieces are a reference implementation to adapt to your theme:

- The cart page does not label seller lines yet
- Seller shipping options are picked automatically unless your checkout calls `GET /mercur/shipping/options` and `POST /mercur/shipping/choose`
- Magento's own carriers still price the whole cart when they quote first-party shipping

## Contributing

Open an issue before a large change so we can agree on the approach. Pull requests need a green CI run and, for anything that touches Magento, a note on how you verified it on a store.

## License

This repository is licensed under the [MIT License](LICENSE).
