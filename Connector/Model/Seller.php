<?php

declare(strict_types=1);

namespace Mercur\Connector\Model;

use Magento\Framework\Model\AbstractModel;
use Mercur\Connector\Model\ResourceModel\Seller as SellerResource;

class Seller extends AbstractModel
{
    public const STATUS_OPEN = 'open';

    protected function _construct(): void
    {
        $this->_init(SellerResource::class);
    }

    public function getSellerId(): string
    {
        return (string) $this->getData('seller_id');
    }

    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    public function isPremium(): bool
    {
        return (bool) $this->getData('premium');
    }

    /**
     * A seller only sells while its account is open and it is not inside a scheduled closure.
     */
    public function isSelling(?\DateTimeInterface $now = null): bool
    {
        if ($this->getData('status') !== self::STATUS_OPEN) {
            return false;
        }

        $now ??= new \DateTimeImmutable();
        $from = $this->getData('closed_from');
        $to = $this->getData('closed_to');
        if (!$from || !$to) {
            return true;
        }

        return !($now >= new \DateTimeImmutable($from) && $now <= new \DateTimeImmutable($to));
    }
}
