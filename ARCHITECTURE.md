# Architecture

## Modules

| Module | Responsibility |
| --- | --- |
| `Mercur_Core` | Configuration, API client, logger |
| `Mercur_Process` | Process table, runner, cron, `mercur:sync` / `mercur:process:*` commands, admin grid and process pages |
| `Mercur_Connector` | Seller and offer mirror with prices per website, offer lines on every add-to-cart path, shadow cart, marketplace carrier and shipping totals, order placement, fulfillment and return sync, admin sellers and offers grids |
| `Mercur_OfferIndexer` | Best offer per SKU and website |
| `Mercur_Catalog` | Taxonomy push, published seller product import as configurable products with images, operator product push, stock from offers |
| `Mercur_Settlement` | Credit memos reported to Mercur; refunds and cancellations from Mercur executed in Magento |
| `Mercur_Frontend` | Product page offers, cart and checkout seller panels, seller pages, marketplace orders in the customer account |
| `Mercur_GraphQl` | Offers and buy box on products, `mercur_offer_id` on cart items, seller shipping on carts, marketplace data on order items, `mercurSeller` |
| `Mercur_Inventory` | Optional, for Multi-Source Inventory: keeps seller lines out of salable quantity checks, reservations and source deduction |

## What moves where

| Direction | Data | Task | Cron job (schedule path) |
| --- | --- | --- | --- |
| Mercur → Magento | Sellers (`mercur_seller`) | `sellers` | `mercur_sync_sellers` (`mercur/sync/sellers_cron`) |
| Mercur → Magento | Offers, prices per website, stock on imported products (`mercur_offer`, `mercur_offer_price`) | `offers` | `mercur_sync_offers` (`mercur/sync/offers_cron`) |
| Mercur → Magento | Published seller products as configurable products, images into the media gallery | `products` | `mercur_sync_products` (`mercur/sync/products_cron`) |
| Magento → Mercur | Attributes of the marketplace attribute group and the category tree | `taxonomy` | run by hand or as part of `full` |
| Magento → Mercur | Your own products, so sellers can offer on them (opt-in) | `product_push` | `mercur_push_products` (`mercur/sync/product_push_cron`) |
| Magento → Mercur | Orders not yet sent or captured | `order_retry` | `mercur_retry_orders` (`mercur/sync/order_retry_cron`) |
| Mercur → Magento | Refunds that no webhook delivered | `refunds` | `mercur_poll_refunds` (`mercur/sync/refunds_cron`) |
| both | Everything above, one child process per step | `full` | `mercur_sync_full` (`mercur/sync/full_cron`) |

Delta runs only fetch what changed since the last successful run; `--full` re-reads everything. Webhooks from Mercur do not apply their payload: they enqueue the matching delta task, so a change lands after the next cron tick plus the sync itself. Cron is the floor, webhooks make it fast.

### Offers and seller products

An offer is mapped to a Magento SKU by exact SKU first, then by identifier (EAN, UPC or barcode against the attributes configured in **Identifier attributes**). Seller products that Magento does not carry are imported as a configurable parent (`mercur-<handle>`) with the variants as not-visible children; missing variant axes are created as dropdown attributes in the marketplace attribute group. Products that match one of your own products by identifier, and products that were pushed from Magento, are never imported back.

### Operator product push

When **Push operator products** is on, enabled and visible products are created in Mercur as published products (configurables with children as variants, simples as single-variant products) with images, categories and the attributes of the marketplace attribute group. Saving or deleting a product keeps Mercur in step. Pushed products carry `metadata.source = magento`, which is how the importer recognises them.

## How an order flows

1. Seller lines are priced by the seller and stay out of Magento's cart price rules: a rule never discounts them, and subtotal or quantity conditions only count your own lines. Seller lines skip Magento's stock checks, reservations and source deduction. Your carriers quote your own lines; a cart of seller lines only gets the "Shipped by the sellers" method, and each seller's shipping is charged in a separate "Seller shipping" total, taxed with the shipping tax class.
2. The order is placed in Magento first. A shadow cart in Mercur mirrors the seller lines during checkout.
3. Once the order is in one of the configured statuses (`pending` and `processing` by default) it is created in Mercur with the payment authorized: one order group, one child order per seller.
4. The capture is confirmed to Mercur when an invoice is paid, so seller payouts only follow money that was actually collected. A cancellation before that releases the authorization.
5. Sellers fulfil and ship their lines in Mercur. Each fulfillment becomes a Magento shipment with tracking. With **Leave seller lines to the sellers** on (the default) seller lines cannot be shipped from the Magento admin or the REST ship endpoint, and the order stays in Processing until every seller shipment has been recorded.
6. Refunds started in Mercur are executed as Magento credit memos; credit memos created in Magento are reported to Mercur, marketplace shipping included.

Orders that could not be sent or captured are retried on the order retry schedule; the admin order view offers Send, Capture and Resync buttons for the rest.

## Processes

Background work is queued as a row in `mercur_process` and run by the `mercur_process_run_pending` cron every minute under a global lock, so two cron workers never run the same process. Cron and webhook enqueues are deduplicated on task code plus parameters while an identical process is still pending or running. A process that throws `Mercur\Process\Api\RetryLaterException` is rescheduled with exponential backoff up to its maximum attempts; a fatal error, or no output for longer than the configured timeout, marks it failed or timed out. A full resync queues one child process per step so each has its own log.

The grid under **Mercur > Processes** can run, stop, delete and inspect processes. `bin/magento mercur:process:run <id>` runs one pending process; `bin/magento mercur:process:clear-history [--days=N]` deletes finished ones, which the `mercur_process_clear_history` cron also does daily.
