<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mercur\Connector\Model\Order\PaymentCapture;
use Mercur\Connector\Model\Order\SendGate;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Core\Model\Config;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

/**
 * Orders that never reached Mercur (API down when they were committed) and
 * paid orders whose capture was not confirmed are completed on the next run.
 */
class OrderRetry implements TaskInterface
{
    public const CODE = 'order_retry';

    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly Synchronizer $synchronizer,
        private readonly PaymentCapture $capture,
        private readonly Config $config
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Retry marketplace order creation and payment capture';
    }

    public function execute(Process $process, array $params): void
    {
        $this->retryPlacement($process);
        $this->retryCapture($process);
    }

    private function retryPlacement(Process $process): void
    {
        $collection = $this->orders->create();
        $collection->addFieldToFilter('mercur_cart_id', ['notnull' => true])
            ->addFieldToFilter('mercur_order_group_id', ['null' => true])
            ->addFieldToFilter('mercur_sent_at', ['null' => true])
            ->addFieldToFilter('state', ['nin' => [Order::STATE_CANCELED, Order::STATE_CLOSED]])
            ->setPageSize(50);

        foreach ($collection as $order) {
            $storeId = (int) $order->getStoreId();
            if (!$this->config->isEnabled($storeId)
                || !SendGate::shouldSend((string) $order->getStatus(), $this->config->getAutoCreateStatuses($storeId), false, true)) {
                continue;
            }
            $process->output('Placing ' . $order->getIncrementId());
            try {
                $this->synchronizer->place($order);
            } catch (\Throwable $e) {
                $process->output('  failed: ' . $e->getMessage());
            }
        }
    }

    private function retryCapture(Process $process): void
    {
        $collection = $this->orders->create();
        $collection->addFieldToFilter('mercur_order_group_id', ['notnull' => true])
            ->addFieldToFilter('mercur_captured_at', ['null' => true])
            ->addFieldToFilter('total_paid', ['gt' => 0])
            ->addFieldToFilter('state', ['nin' => [Order::STATE_CANCELED, Order::STATE_CLOSED]])
            ->setPageSize(50);

        foreach ($collection as $order) {
            if (!$this->config->isEnabled((int) $order->getStoreId())) {
                continue;
            }
            $process->output('Capturing ' . $order->getIncrementId());
            try {
                $this->capture->capture($order);
            } catch (\Throwable $e) {
                $process->output('  failed: ' . $e->getMessage());
            }
        }
    }
}
