<?php

declare(strict_types=1);

namespace Mercur\Connector\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\MarketplaceOrderView;
use Mercur\Core\Model\Config;

class Marketplace extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly MarketplaceOrderView $view,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function hasMarketplaceLines(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }
        foreach ($order->getAllItems() as $item) {
            if ($item->getData('mercur_offer_id')) {
                return true;
            }
        }

        return false;
    }

    public function isShippingLocked(): bool
    {
        $order = $this->getOrder();

        return $order !== null && $this->config->lockSellerShipping((int) $order->getStoreId());
    }

    public function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $order = $this->getOrder();

        return $order ? $this->view->build($order) : [];
    }

    public function getStatusLabel(string $status): string
    {
        return MarketplaceOrderView::statusLabel($status);
    }
}
