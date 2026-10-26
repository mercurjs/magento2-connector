# Events

## Magento events

The connector dispatches Magento events you can observe from your own module:

- `mercur_api_call_before` — `request` (`Magento\Framework\DataObject` with `method`, `url`, `path`, `headers`, `body`, `store_id`; `headers` may be changed)
- `mercur_api_call_after` — `request`, `status`, `body`, `duration_ms`, `attempt`, `exception` (set when the transport failed)
- `mercur_webhook_received` — `event_name` and `payload` (`DataObject` with the event data; changes reach the handlers) before any webhook handler runs
- `mercur_seller_imported`, `mercur_offer_mapped_before`, `mercur_offer_mapped_after`, `mercur_cart_sync_before`, `mercur_order_place_before`, `mercur_order_place_after`, `mercur_fulfillment_synced`, `mercur_refund_reported` — see the respective module for the payload

## Mercur webhooks

Mercur calls `POST /mercur/webhook` on the store. The body is signed with HMAC-SHA256 in `x-mercur-signature` using the configured signing secret; events older than **Maximum event age** are rejected when that is set. Subscribe the endpoint to:

| Event | What the connector does |
| --- | --- |
| `seller.approved`, `seller.updated`, `seller.suspended`, `seller.unsuspended`, `seller.terminated`, `seller.unterminated` | Enqueues a seller delta sync |
| `offer.created`, `offer.updated`, `offer.deleted` | Enqueues an offer delta sync |
| `product.published` | Enqueues a published product import |
| `product.rejected` | Disables the imported product |
| `order.fulfillment_created`, `order.fulfillment_canceled`, `shipment.created`, `order.shipment_created`, `delivery.created`, `order.return_requested`, `order.return_received` | Re-reads the seller order: creates or updates the Magento shipment with tracking, records delivery and return status |
| `order.canceled` | Flags the seller's lines as canceled (refused when the seller declined) and refunds the shopper for those lines and their shipping |
| `order.refund_requested` | Executes the refund as a Magento credit memo |

Deliveries are recorded in `mercur_webhook_event` by their `x-mercur-delivery` id (falling back to the payload `id`, then a digest of the body). A repeated delivery answers `{"received":true,"duplicate":true}` without running handlers; a handler failure forgets the id so Mercur's retry is processed again.
