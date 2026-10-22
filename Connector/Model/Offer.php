<?php

declare(strict_types=1);

namespace Mercur\Connector\Model;

use Magento\Framework\Model\AbstractModel;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;

class Offer extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(OfferResource::class);
    }

    public function getOfferId(): string
    {
        return (string) $this->getData('offer_id');
    }

    public function getProductSku(): string
    {
        return (string) $this->getData('product_sku');
    }

    public function getSellerId(): string
    {
        return (string) $this->getData('seller_id');
    }

    public function getPrice(): float
    {
        return (float) $this->getData('price');
    }

    public function getOriginPrice(): ?float
    {
        $value = $this->getData('origin_price');

        return $value === null ? null : (float) $value;
    }

    public function getQuantity(): ?int
    {
        $value = $this->getData('quantity');

        return $value === null ? null : (int) $value;
    }

    public function isActive(): bool
    {
        return (bool) $this->getData('active');
    }

    public function isAvailable(int $qty = 1): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        if (!$this->getData('manage_inventory') || $this->getData('allow_backorder')) {
            return true;
        }

        return ($this->getQuantity() ?? 0) >= $qty;
    }

    /**
     * @return array<int, array{min_quantity:int|null,max_quantity:int|null,amount:float}>
     */
    public function getPriceRanges(): array
    {
        $raw = $this->getData('price_ranges');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The unit price for a quantity: the cheaper of the current price and the
     * quantity tier that covers it.
     */
    public function getPriceForQuantity(int $qty): float
    {
        $best = $this->getPrice();
        foreach ($this->getPriceRanges() as $range) {
            $min = $range['min_quantity'] ?? 1;
            $max = $range['max_quantity'] ?? null;
            if ($qty >= (int) $min && ($max === null || $qty <= (int) $max)) {
                $best = min($best, (float) $range['amount']);
            }
        }

        return $best;
    }

    public function getConditionLabel(): ?string
    {
        $label = (string) $this->getData('condition_label');
        if ($label !== '') {
            return $label;
        }
        $code = (string) $this->getData('condition_code');

        return $code === '' ? null : $code;
    }

    public function getSellerName(): string
    {
        return (string) $this->getData('seller_name');
    }

    public function getSellerHandle(): string
    {
        return (string) $this->getData('seller_handle');
    }

    /**
     * Days the seller needs before handing the parcel over, falling back to the seller default.
     */
    public function getLeadtimeToShip(): ?int
    {
        $value = $this->getData('leadtime_to_ship') ?? $this->getData('seller_default_leadtime_to_ship');

        return $value === null ? null : (int) $value;
    }
}
