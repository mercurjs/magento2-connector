<?php

declare(strict_types=1);

namespace Mercur\Connector\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory;

/**
 * Read-only "Marketplace offers" section on the product edit page: every
 * seller offer mirrored for this SKU, with its price per website.
 */
class MarketplaceOffers extends AbstractModifier
{
    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly CollectionFactory $offers,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $sku = (string) $this->locator->getProduct()->getSku();
        if ($sku === '') {
            return $meta;
        }

        $meta['mercur_offers'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Marketplace offers'),
                        'componentType' => 'fieldset',
                        'collapsible' => true,
                        'opened' => false,
                        'sortOrder' => 1000,
                    ],
                ],
            ],
            'children' => [
                'mercur_offers_table' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/html',
                                'content' => $this->table($sku),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    private function table(string $sku): string
    {
        $collection = $this->offers->create();
        $collection->addSkuFilter($sku)->joinSeller();
        if (!$collection->getSize()) {
            return '<p>' . $this->escaper->escapeHtml(__('No seller offers this SKU.')) . '</p>';
        }

        $websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websites[(int) $website->getId()] = (string) $website->getName();
        }
        $prices = $this->pricesByOffer(array_map(static fn ($o) => $o->getOfferId(), $collection->getItems()));

        $rows = '';
        foreach ($collection as $offer) {
            $priceText = [];
            foreach ($prices[$offer->getOfferId()] ?? [] as $row) {
                $priceText[] = ($websites[(int) $row['website_id']] ?? $row['website_id']) . ': '
                    . number_format((float) $row['price'], 2) . ' ' . $row['currency_code'];
            }
            $rows .= '<tr>'
                . '<td>' . $this->escaper->escapeHtml($offer->getSellerName()) . '</td>'
                . '<td>' . $this->escaper->escapeHtml((string) $offer->getData('seller_sku')) . '</td>'
                . '<td>' . $this->escaper->escapeHtml(implode(', ', $priceText) ?: (string) __('not priced')) . '</td>'
                . '<td>' . $this->escaper->escapeHtml($offer->getQuantity() === null ? (string) __('unlimited') : (string) $offer->getQuantity()) . '</td>'
                . '<td>' . $this->escaper->escapeHtml($offer->isActive() ? (string) __('Yes') : (string) __('No')) . '</td>'
                . '</tr>';
        }

        return '<table class="admin__table-secondary"><thead><tr>'
            . '<th>' . $this->escaper->escapeHtml(__('Seller')) . '</th>'
            . '<th>' . $this->escaper->escapeHtml(__('Seller SKU')) . '</th>'
            . '<th>' . $this->escaper->escapeHtml(__('Price')) . '</th>'
            . '<th>' . $this->escaper->escapeHtml(__('Available')) . '</th>'
            . '<th>' . $this->escaper->escapeHtml(__('Active')) . '</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * @param string[] $offerIds
     * @return array<string, array<int, array{website_id: int, price: string, currency_code: string}>>
     */
    private function pricesByOffer(array $offerIds): array
    {
        $collection = $this->offers->create();
        $connection = $collection->getConnection();
        $select = $connection->select()
            ->from($collection->getTable('mercur_offer_price'), ['offer_id', 'website_id', 'price', 'currency_code'])
            ->where('offer_id IN (?)', $offerIds);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(string) $row['offer_id']][] = $row;
        }

        return $result;
    }
}
