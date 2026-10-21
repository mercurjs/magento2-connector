<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;
use Mercur\Connector\Model\Sync\Cursor;
use Mercur\Connector\Model\Sync\OfferMapper;
use Mercur\Connector\Model\Sync\Pager;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

class OfferSync implements TaskInterface
{
    public const CODE = 'offers';
    public const EVENT_IMPORTED = 'mercur_offers_imported';

    public function __construct(
        private readonly AdminApi $api,
        private readonly Pager $pager,
        private readonly Cursor $cursor,
        private readonly OfferMapper $mapper,
        private readonly OfferResource $offers,
        private readonly StoreManagerInterface $storeManager,
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
        $currency = (string) $this->storeManager->getStore()->getBaseCurrencyCode();
        $process->output($since ? "Delta since $since" : 'Full import');

        $seen = [];
        $skus = [];
        $count = 0;
        $latest = $this->pager->walk(
            fn (int $offset) => $this->api->listOffers($since, $offset),
            'offers',
            function (array $rows) use (&$seen, &$skus, &$count, $currency): void {
                $upserts = [];
                $deleted = [];
                foreach ($rows as $offer) {
                    if (!empty($offer['deleted_at'])) {
                        $deleted[] = $offer['id'];
                        continue;
                    }
                    $row = $this->mapper->map($offer, $currency);
                    if ($row === null) {
                        continue;
                    }
                    $upserts[] = $row;
                    $seen[] = $row['offer_id'];
                    $skus[$row['product_sku']] = true;
                }
                $this->offers->upsert($upserts);
                $this->offers->deleteByIds($deleted);
                $count += count($rows);
            }
        );

        if ($full) {
            $this->offers->deleteNotIn($seen);
        }
        if ($latest) {
            $this->cursor->set(self::CODE, $latest);
        }

        $process->output("Processed $count offer(s) across " . count($skus) . ' SKU(s)');
        $this->events->dispatch(self::EVENT_IMPORTED, ['skus' => array_keys($skus), 'full' => $full]);
    }
}
