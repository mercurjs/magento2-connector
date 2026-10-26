# Developing the connector

## Local setup

```bash
composer install --ignore-platform-reqs
composer lint      # php -l on every file
composer analyse   # phpstan
composer test      # phpunit, Test/Unit
```

CI (`.github/workflows/ci.yml`) runs the same three commands. Unit tests boot from `Test/bootstrap.php`, which stubs the Magento framework classes and factories the modules depend on, so no Magento installation is needed to run them.

## Trying a change on a store

The package needs a Mercur API running [`@mercurjs/magento2-connector-plugin`](https://github.com/mercurjs/mercur/tree/main/packages/plugins/magento2-connector) with its external payment provider registered. Point `composer.json` of the store at your checkout:

```json
{
  "repositories": [{ "type": "path", "url": "../magento2-connector" }],
  "require": { "mercurjs/magento2-connector": "@dev" }
}
```

Then install and connect:

```bash
composer require mercurjs/magento2-connector:@dev
bin/magento module:enable Mercur_Core Mercur_Process Mercur_Connector \
  Mercur_OfferIndexer Mercur_Catalog Mercur_Settlement Mercur_Frontend Mercur_GraphQl Mercur_Inventory
bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush
```

Fill in **Stores > Configuration > Mercur > Marketplace > Connection** (API URL, admin and publishable keys, sales channel, region) and use the **Connection check** button; see [CONFIGURATION.md](CONFIGURATION.md). After a constructor or `di.xml` change, clear `generated/code/Mercur` and `generated/metadata` before `cache:flush`.

## Running syncs by hand

```bash
bin/magento mercur:sync                      # every task, delta
bin/magento mercur:sync offers --full        # one task, full resync
bin/magento mercur:process:run <id>          # run one pending process
bin/magento mercur:process:clear-history --days=30
```

Tasks: `taxonomy`, `product_push`, `sellers`, `offers`, `products`, `refunds`, `order_retry`, `full`. Each run is a row under **Mercur > Processes** in the admin with its log; [ARCHITECTURE.md](ARCHITECTURE.md) explains what each task does and when cron runs it.

## Layout

Each top-level directory is one Magento module (`Core`, `Process`, `Connector`, `OfferIndexer`, `Catalog`, `Settlement`, `Frontend`, `GraphQl`, `Inventory`), registered together by `registration.php`. Tests live in `Test/Unit/<Module>/` and mirror the module layout.

## Pull requests

Keep one logical change per commit with a unit test for the behaviour it adds or fixes. Describe in the PR how you verified the change on a store: which sync you ran, what the storefront or admin showed.
