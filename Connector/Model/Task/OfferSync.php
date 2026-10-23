<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Mercur\Connector\Model\Catalog\Identifier;
use Mercur\Connector\Model\Catalog\ProductMatcher;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;
use Mercur\Connector\Model\ResourceModel\OfferPrice;
use Mercur\Connector\Model\Sync\Cursor;
use Mercur\Connector\Model\Sync\OfferMapper;
use Mercur\Connector\Model\Sync\OfferPriceMapper;
use Mercur\Connector\Model\Sync\Pager;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Api\StoreApi;
use Mercur\Core\Model\Scope\WebsiteScope;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;
use Psr\Log\LoggerInterface;

class OfferSync implements TaskInterface
{
    public const CODE = 'offers';
    public const EVENT_IMPORTED = 'mercur_offers_imported';
    public const EVENT_MAPPED_BEFORE = 'mercur_offer_mapped_before';
    public const EVENT_MAPPED_AFTER = 'mercur_offer_mapped_after';

    private const PRICE_BATCH = 100;

    public function __construct(
        private readonly AdminApi $api,
        private readonly StoreApi $storeApi,
        private readonly Pager $pager,
        private readonly Cursor $cursor,
        private readonly OfferMapper $mapper,
        private readonly OfferPriceMapper $priceMapper,
        private readonly OfferResource $offers,
        private readonly OfferPrice $prices,
        private readonly WebsiteScope $websites,
        private readonly ManagerInterface $events,
        private readonly LoggerInterface $logger,
        private readonly ProductMatcher $matcher
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Import offers';
    }

    public function execute(Process $process, array $params): void
    {
        $full = (bool) ($params['full'] ?? false);
        $since = $full ? null : $this->cursor->get(self::CODE);
        $process->output($since ? "Delta since $since" : 'Full import');

        $seen = [];
        $skus = [];
        $count = 0;
        $failed = 0;
        $matched = 0;
        $priceFailures = 0;
        $latest = $this->pager->walk(
            fn (int $offset) => $this->api->listOffers($since, $offset),
            'offers',
            function (array $rows) use (&$seen, &$skus, &$count, &$failed, &$matched, &$priceFailures, $process): void {
                $this->matcher->prime(array_map(static fn (array $offer) => [
                    'sku' => $offer['product_variant']['sku'] ?? null,
                    'identifiers' => Identifier::fromOffer($offer),
                ], $rows));
                $upserts = [];
                $deleted = [];
                foreach ($rows as $offer) {
                    try {
                        if (!empty($offer['deleted_at'])) {
                            $deleted[] = $offer['id'];
                            continue;
                        }
                        $variantSku = $offer['product_variant']['sku'] ?? null;
                        $productSku = $this->matcher->resolve($variantSku, Identifier::fromOffer($offer));
                        $row = $this->mapRow($offer, $productSku);
                        if ($row === null) {
                            continue;
                        }
                        if ($productSku !== null && $productSku !== $variantSku) {
                            $matched++;
                        }
                        $upserts[] = $row;
                        $seen[] = $row['offer_id'];
                        $skus[$row['product_sku']] = true;
                    } catch (\Throwable $e) {
                        $failed++;
                        $id = (string) ($offer['id'] ?? '?');
                        $process->output("Offer $id skipped: " . $e->getMessage());
                        $this->logger->warning('Mercur offer import skipped a row', ['offer_id' => $id, 'exception' => $e]);
                    }
                }
                // SKUs an offer leaves, by deletion or by resolving to another product, need their index rebuilt too.
                foreach ($this->offers->skusForIds(array_merge($deleted, array_column($upserts, 'offer_id'))) as $sku) {
                    $skus[$sku] = true;
                }
                $this->offers->upsert($upserts);
                $this->offers->deleteByIds($deleted);
                $this->prices->deleteForOffers($deleted);
                $priceFailures += $this->syncPrices(array_column($upserts, 'offer_id'), $process);
                $count += count($rows);
            }
        );

        if ($full) {
            if ($seen === []) {
                $process->output('No offers returned by Mercur; keeping the existing rows instead of deleting them all');
            } else {
                $this->offers->deleteNotIn($seen);
                $this->prices->deleteOrphans();
            }
        }
        if ($latest) {
            $this->cursor->set(self::CODE, $latest);
        }

        $process->output(sprintf(
            'Processed %d offer(s) across %d SKU(s), %d matched by identifier, %d skipped, %d price batch(es) failed',
            $count,
            count($skus),
            $matched,
            $failed,
            $priceFailures
        ));
        $this->events->dispatch(self::EVENT_IMPORTED, ['skus' => array_keys($skus), 'full' => $full]);
    }

    private function mapRow(array $offer, ?string $productSku): ?array
    {
        $transport = new DataObject(['skip' => false]);
        $this->events->dispatch(self::EVENT_MAPPED_BEFORE, ['offer' => $offer, 'transport' => $transport]);
        if ($transport->getData('skip')) {
            return null;
        }
        $row = $this->mapper->map($offer, $productSku);
        if ($row === null) {
            return null;
        }
        $mutable = new DataObject($row);
        $this->events->dispatch(self::EVENT_MAPPED_AFTER, ['row' => $mutable, 'offer' => $offer]);

        return $mutable->getData();
    }

    /**
     * Prices every offer once per selling website, from that website's Mercur
     * region, so price lists and region currencies apply. A failing batch
     * keeps its previous prices and is counted rather than aborting the run.
     *
     * @param string[] $offerIds
     * @return int failed batches
     */
    private function syncPrices(array $offerIds, Process $process): int
    {
        $failures = 0;
        foreach ($this->websites->all() as $websiteId => $scope) {
            foreach (array_chunk($offerIds, self::PRICE_BATCH) as $batch) {
                try {
                    $this->syncPriceBatch($batch, $websiteId, $scope, $process);
                } catch (\Throwable $e) {
                    $failures++;
                    $process->output(sprintf(
                        'Price sync failed for %d offer(s) on website %d: %s',
                        count($batch),
                        $websiteId,
                        $e->getMessage()
                    ));
                    $this->logger->warning('Mercur offer price batch failed', [
                        'website_id' => $websiteId,
                        'offer_ids' => $batch,
                        'exception' => $e,
                    ]);
                }
            }
        }

        return $failures;
    }

    /**
     * @param string[] $batch
     * @param array{store_id:int, region_id:string, currency_code:string} $scope
     */
    private function syncPriceBatch(array $batch, int $websiteId, array $scope, Process $process): void
    {
        $priced = $this->storeApi->listPricedOffers($batch, $scope['region_id'], $scope['store_id']);
        $rows = [];
        foreach ($priced as $offer) {
            $row = $this->priceMapper->map($offer, $websiteId, $scope['currency_code']);
            if ($row === null) {
                $process->output(sprintf(
                    'Offer %s has no %s price in region %s',
                    $offer['id'] ?? '?',
                    $scope['currency_code'],
                    $scope['region_id']
                ));
                continue;
            }
            $rows[] = $row;
        }
        $unpriced = array_values(array_diff($batch, array_column($rows, 'offer_id')));
        $this->prices->deleteForOffers($unpriced, $websiteId);
        $this->prices->upsert($rows);
    }
}
