<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Order;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\MarketplaceOrderView;

class MarketplaceOrders extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly MarketplaceOrderView $view,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order ? $this->view->build($order) : [];
    }

    public function getStatusLabel(string $status): string
    {
        return MarketplaceOrderView::statusLabel($status);
    }
}
