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
| Offers and seller products matched to your products by EAN, UPC or GTIN when the SKUs differ | ✔ |
| Seller product images imported into the media gallery and kept in step with Mercur | ✔ |
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

### How does an order flow?

Seller lines are priced by the seller and stay out of Magento's cart price rules: a rule never discounts them, and subtotal or quantity conditions only count your own lines. Each seller's shipping is taxed with the shipping tax class. The order is placed in Magento first; once it is committed in one of the configured statuses (Stores > Configuration > Mercur > Orders, `pending` and `processing` by default) it is created in Mercur with the payment authorized. The capture is confirmed to Mercur when an invoice is paid, so seller payouts only follow money that was actually collected; a cancellation before that releases the authorization. Orders that could not be sent or captured are retried on the order retry schedule, and the admin order view offers Send, Capture and Resync buttons for the rest. Seller lines are shipped by their sellers: by default they cannot be shipped from the Magento admin or the REST ship endpoint (Stores > Configuration > Mercur > Orders > Leave seller lines to the sellers), and the order stays in Processing until the connector has recorded a shipment for each fulfillment Mercur reports.

### What is not supported yet?

- Buyer and seller messaging, order incidents and seller ratings
- Documents sellers attach to orders
- A third-party tax engine for seller lines (Magento taxes them with the product's tax class)
- Mercur promotions
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
| `Mercur_Process` | Process table, runner, cron, `mercur:sync` / `mercur:process:*` commands, admin grid and process pages |
| `Mercur_Connector` | Seller and offer mirror with prices per website, offer lines on every add-to-cart path, shadow cart, marketplace carrier and shipping totals, order placement, fulfillment and return sync, admin sellers and offers grids |
| `Mercur_OfferIndexer` | Best offer per SKU and website |
| `Mercur_Catalog` | Taxonomy push, published product import as simple or configurable products, stock from offers |
| `Mercur_Settlement` | Credit memos reported to Mercur; refunds and cancellations from Mercur executed in Magento |
| `Mercur_Frontend` | Product page offers, cart and checkout seller panels, seller pages, marketplace orders in the customer account |
| `Mercur_GraphQl` | Offers and buy box on products, `mercur_offer_id` on cart items, seller shipping on carts, marketplace data on order items, `mercurSeller` |
| `Mercur_Inventory` | Optional, for Multi-Source Inventory: keeps seller lines out of salable quantity checks, reservations and source deduction |

### Configuration scopes

Every setting lives under **Stores > Configuration > Mercur > Marketplace**. The scope a field accepts decides which storefronts can differ.

| Field | Path | Scope |
| --- | --- | --- |
| Enabled | `mercur/general/enabled` | default, website, store view |
| Mercur API URL | `mercur/general/api_url` | default, website, store view |
| Admin API key | `mercur/general/api_key` | default, website, store view |
| Publishable API key | `mercur/general/publishable_key` | default, website, store view |
| Mercur sales channel ID | `mercur/general/sales_channel_id` | default, website, store view |
| Mercur region ID | `mercur/general/region_id` | default, website, store view |
| Channel | `mercur/general/channel` | default, website |
| Request timeout, retries, retry delay | `mercur/general/{timeout,retries,retry_delay_ms}` | default, website |
| API log level | `mercur/general/log_level` | default, website, store view |
| Best offer rule | `mercur/offers/best_offer_rule` | default, website |
| Label for your own stock | `mercur/offers/operator_label` | default, website, store view |
| Identifier attributes | `mercur/offers/identifier_attributes` | default |
| Delivery estimates | `mercur/delivery/*` | default, website |
| Create in Mercur when the order status is | `mercur/order/auto_create_statuses` | default, website, store view |
| Leave seller lines to the sellers | `mercur/order/lock_seller_shipping` | default, website, store view |
| Catalog import: attribute set and group, status, visibility, websites | `mercur/catalog/*` | default |
| Import product images, maximum size, download timeout | `mercur/catalog/{import_images,image_max_size_mb,image_timeout_sec}` | default |
| Webhook signing secret, maximum event age | `mercur/webhook/{secret,max_age_sec}` | default |
| Sync schedules | `mercur/sync/*_cron` | default |
| Page size | `mercur/sync/page_size` | default, website |
| Process timeout, attempts, history | `mercur/process/*` | default |

The **Connection check** button on the Connection group calls the Mercur status endpoint with the saved credentials of the scope being edited and looks up the configured region.

### Processes

Background work (seller and offer mirror, full resync, order retries) is queued as a row in `mercur_process` and run by the `mercur_process_run_pending` cron every minute under a global lock, so two cron workers never run the same process. Cron and webhook enqueues are deduplicated on task code plus parameters while an identical process is still pending or running. A process that throws `Mercur\Process\Api\RetryLaterException` is rescheduled with exponential backoff up to its maximum attempts; a fatal error or a process with no output for longer than the configured timeout is marked as failed or timed out. A full resync queues one child process per step so each has its own log. The grid under **Mercur > Processes** can run, stop, delete and inspect processes; `bin/magento mercur:process:run <id>` runs one pending process and `bin/magento mercur:process:clear-history [--days=N]` deletes finished ones, which the `mercur_process_clear_history` cron also does daily.

### Events

The connector dispatches Magento events you can observe from your own module:

- `mercur_api_call_before` — `request` (`Magento\Framework\DataObject` with `method`, `url`, `path`, `headers`, `body`, `store_id`; `headers` may be changed)
- `mercur_api_call_after` — `request`, `status`, `body`, `duration_ms`, `attempt`, `exception` (set when the transport failed)
- `mercur_webhook_received` — `event_name` and `payload` (`DataObject` with the event data; changes reach the handlers) before any webhook handler runs
- `mercur_seller_imported`, `mercur_offer_mapped_before`, `mercur_offer_mapped_after`, `mercur_cart_sync_before`, `mercur_order_place_before`, `mercur_order_place_after`, `mercur_fulfillment_synced`, `mercur_refund_reported` — see the respective module for the payload

Webhook deliveries are recorded in `mercur_webhook_event` by their `x-mercur-delivery` id (falling back to the payload `id`, then a digest of the body); a repeated delivery answers `{"received":true,"duplicate":true}` without running handlers, and a handler failure forgets the id so Mercur's retry is processed again.

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
