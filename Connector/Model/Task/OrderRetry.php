<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

/**
 * Orders that paid but never reached Mercur (API down at placement) are
 * completed on the next run.
 */
class OrderRetry implements TaskInterface
{
    public const CODE = 'order_retry';

    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly Synchronizer $synchronizer
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Retry marketplace order creation';
    }

    public function execute(Process $process, array $params): void
    {
        $collection = $this->orders->create();
        $collection->addFieldToFilter('mercur_cart_id', ['notnull' => true])
            ->addFieldToFilter('mercur_order_group_id', ['null' => true])
            ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
            ->setPageSize(50);

        foreach ($collection as $order) {
            $process->output('Retrying ' . $order->getIncrementId());
            $this->synchronizer->place($order);
        }
    }
}
