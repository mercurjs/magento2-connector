<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Sync;

class SellerMapper
{
    public function map(array $seller): array
    {
        return [
            'seller_id' => $seller['id'],
            'name' => $seller['name'] ?? '',
            'handle' => $seller['handle'] ?? '',
            'status' => $seller['status'] ?? 'pending_approval',
            'premium' => (int) ($seller['is_premium'] ?? false),
            'default_leadtime_to_ship' => $seller['default_leadtime_to_ship'] ?? null,
            'closed_from' => $this->timestamp($seller['closed_from'] ?? null),
            'closed_to' => $this->timestamp($seller['closed_to'] ?? null),
            'description' => $seller['description'] ?? null,
            'logo' => $seller['logo'] ?? null,
            'updated_at' => $this->timestamp($seller['updated_at'] ?? null),
        ];
    }

    public function isSelling(array $seller, ?\DateTimeImmutable $now = null): bool
    {
        if (($seller['status'] ?? 'open') !== 'open') {
            return false;
        }
        $from = $seller['closed_from'] ?? null;
        $to = $seller['closed_to'] ?? null;
        if (!$from || !$to) {
            return true;
        }
        $now ??= new \DateTimeImmutable();

        return !($now >= new \DateTimeImmutable($from) && $now <= new \DateTimeImmutable($to));
    }

    private function timestamp(?string $iso): ?string
    {
        return $iso ? (new \DateTimeImmutable($iso))->format('Y-m-d H:i:s') : null;
    }
}
