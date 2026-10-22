<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Framework\Event\ManagerInterface;
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

class OfferSync implements TaskInterface
{
    public const CODE = 'offers';
    public const EVENT_IMPORTED = 'mercur_offers_imported';

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
        private readonly ManagerInterface $events
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
        $latest = $this->pager->walk(
            fn (int $offset) => $this->api->listOffers($since, $offset),
            'offers',
            function (array $rows) use (&$seen, &$skus, &$count, $process): void {
                $upserts = [];
                $deleted = [];
                foreach ($rows as $offer) {
                    if (!empty($offer['deleted_at'])) {
                        $deleted[] = $offer['id'];
                        continue;
                    }
                    $row = $this->mapper->map($offer);
                    if ($row === null) {
                        continue;
                    }
                    $upserts[] = $row;
                    $seen[] = $row['offer_id'];
                    $skus[$row['product_sku']] = true;
                }
                $this->offers->upsert($upserts);
                $this->offers->deleteByIds($deleted);
                $this->prices->deleteForOffers($deleted);
                $this->syncPrices(array_column($upserts, 'offer_id'), $process);
                $count += count($rows);
            }
        );

        if ($full) {
            $this->offers->deleteNotIn($seen);
            $this->prices->deleteOrphans();
        }
        if ($latest) {
            $this->cursor->set(self::CODE, $latest);
        }

        $process->output("Processed $count offer(s) across " . count($skus) . ' SKU(s)');
        $this->events->dispatch(self::EVENT_IMPORTED, ['skus' => array_keys($skus), 'full' => $full]);
    }

    /**
     * Prices every offer once per selling website, from that website's Mercur
     * region, so price lists and region currencies apply.
     *
     * @param string[] $offerIds
     */
    private function syncPrices(array $offerIds, Process $process): void
    {
        foreach ($this->websites->all() as $websiteId => $scope) {
            foreach (array_chunk($offerIds, self::PRICE_BATCH) as $batch) {
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
    }
}
