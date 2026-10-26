# Configuration

Every setting lives under **Stores > Configuration > Mercur > Marketplace**. The scope a field accepts decides which storefronts can differ. Set the region on each website that sells offers: that website is priced from that region, and its base currency must match the region's.

## Connection

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

The **Connection check** button calls the Mercur status endpoint with the saved credentials of the scope being edited and looks up the configured region.

## Offers, delivery and checkout

| Field | Path | Scope |
| --- | --- | --- |
| Best offer rule | `mercur/offers/best_offer_rule` | default, website |
| Label for your own stock | `mercur/offers/operator_label` | default, website, store view |
| Identifier attributes | `mercur/offers/identifier_attributes` | default |
| Show estimated delivery, cut-off time, business days, transit days | `mercur/delivery/*` | default, website |
| Seller shipping prices include tax | `mercur/checkout/shipping_prices_include_tax` | default, website |
| Remove unavailable seller lines automatically | `mercur/checkout/auto_remove_unavailable` | default, website |

## Orders

| Field | Path | Scope |
| --- | --- | --- |
| Create in Mercur when the order status is | `mercur/order/auto_create_statuses` | default, website, store view |
| Leave seller lines to the sellers | `mercur/order/lock_seller_shipping` | default, website, store view |

## Catalog

| Field | Path | Scope |
| --- | --- | --- |
| Marketplace attribute group | `mercur/catalog/attribute_group` | default |
| Attribute set for imported products | `mercur/catalog/attribute_set_id` | default |
| Import published seller products | `mercur/catalog/import_products` | default |
| Push operator products | `mercur/catalog/export_products` | default |
| Status, visibility and websites of imported products | `mercur/catalog/{imported_product_status,imported_product_visibility,website_ids}` | default |
| Import product images, maximum size, download timeout | `mercur/catalog/{import_images,image_max_size_mb,image_timeout_sec}` | default |

## Webhooks, synchronisation and processes

| Field | Path | Scope |
| --- | --- | --- |
| Signing secret, maximum event age | `mercur/webhook/{secret,max_age_sec}` | default |
| Sellers, offers, products, operator product push, full, refunds, order retry schedules | `mercur/sync/*_cron` | default |
| Page size | `mercur/sync/page_size` | default, website |
| Process timeout, maximum attempts, history | `mercur/process/{timeout_sec,max_attempts,history_days}` | default |

Subscribe the Mercur webhook endpoint to every event listed in [EVENTS.md](EVENTS.md); the signing secret must match the one on the Mercur endpoint.
