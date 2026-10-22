<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Delivery;

/**
 * Delivery window for a seller parcel: orders after the cut-off start on the
 * next business day, the seller hands over after its lead time, and the
 * carrier needs between the minimum and maximum transit days. All counts are
 * business days.
 */
final class Estimator
{
    /**
     * @param int[] $businessDays ISO weekday numbers, 1 = Monday
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable}
     */
    public function estimate(
        \DateTimeImmutable $now,
        ?int $leadtimeDays,
        int $transitMin,
        int $transitMax,
        string $cutoffTime,
        array $businessDays
    ): array {
        $businessDays = $businessDays === [] ? [1, 2, 3, 4, 5] : array_map('intval', $businessDays);
        $start = $now->setTime(0, 0);

        if (!$this->isBusinessDay($start, $businessDays) || $this->isPastCutoff($now, $cutoffTime)) {
            $start = $this->nextBusinessDay($start, $businessDays);
        }

        $handover = $this->addBusinessDays($start, max(0, (int) $leadtimeDays), $businessDays);
        $transitMin = max(0, $transitMin);
        $transitMax = max($transitMin, $transitMax);

        return [
            'from' => $this->addBusinessDays($handover, $transitMin, $businessDays),
            'to' => $this->addBusinessDays($handover, $transitMax, $businessDays),
        ];
    }

    private function isPastCutoff(\DateTimeImmutable $now, string $cutoffTime): bool
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $cutoffTime, $match)) {
            return false;
        }

        return (int) $now->format('G') * 60 + (int) $now->format('i') >= (int) $match[1] * 60 + (int) $match[2];
    }

    private function isBusinessDay(\DateTimeImmutable $day, array $businessDays): bool
    {
        return in_array((int) $day->format('N'), $businessDays, true);
    }

    private function nextBusinessDay(\DateTimeImmutable $day, array $businessDays): \DateTimeImmutable
    {
        do {
            $day = $day->modify('+1 day');
        } while (!$this->isBusinessDay($day, $businessDays));

        return $day;
    }

    private function addBusinessDays(\DateTimeImmutable $day, int $count, array $businessDays): \DateTimeImmutable
    {
        for ($i = 0; $i < $count; $i++) {
            $day = $this->nextBusinessDay($day, $businessDays);
        }

        return $day;
    }
}
