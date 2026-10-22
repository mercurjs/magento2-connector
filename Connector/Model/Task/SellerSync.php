<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Framework\Event\ManagerInterface;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;
use Mercur\Connector\Model\ResourceModel\Seller as SellerResource;
use Mercur\Connector\Model\Sync\Cursor;
use Mercur\Connector\Model\Sync\Pager;
use Mercur\Connector\Model\Sync\SellerMapper;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

class SellerSync implements TaskInterface
{
    public const CODE = 'sellers';
    public const EVENT_IMPORTED = 'mercur_seller_imported';

    public function __construct(
        private readonly AdminApi $api,
        private readonly Pager $pager,
        private readonly Cursor $cursor,
        private readonly SellerMapper $mapper,
        private readonly SellerResource $sellers,
        private readonly OfferResource $offers,
        private readonly ManagerInterface $events
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Import sellers';
    }

    public function execute(Process $process, array $params): void
    {
        $full = (bool) ($params['full'] ?? false);
        $since = $full ? null : $this->cursor->get(self::CODE);
        $process->output($since ? "Delta since $since" : 'Full import');

        $count = 0;
        $latest = $this->pager->walk(
            fn (int $offset) => $this->api->listSellers($since, $offset),
            'sellers',
            function (array $rows) use (&$count, $full): void {
                $upserts = [];
                $deleted = [];
                $paused = [];
                $resumed = [];
                foreach ($rows as $seller) {
                    if (!empty($seller['deleted_at'])) {
                        $deleted[] = $seller['id'];
                        continue;
                    }
                    $upserts[] = $this->mapper->map($seller);
                    if ($this->mapper->isSelling($seller)) {
                        $resumed[] = $seller['id'];
                    } else {
                        $paused[] = $seller['id'];
                    }
                }
                $this->sellers->upsert($upserts);
                $this->sellers->deleteByIds($deleted);
                $this->offers->setActiveForSellers($paused, false);
                $this->offers->setActiveForSellers($resumed, true);
                $this->offers->setActiveForSellers($deleted, false);
                $this->events->dispatch(self::EVENT_IMPORTED, [
                    'sellers' => $upserts,
                    'deleted' => $deleted,
                    'full' => $full,
                ]);
                $count += count($rows);
            }
        );

        if ($latest) {
            $this->cursor->set(self::CODE, $latest);
        }
        $process->output("Processed $count seller(s)");
    }
}
