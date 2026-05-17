<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Entity\WarehouseLocation;

interface AllocationStrategyInterface
{
    /**
     * Tiebreaker score when warehouses are equal on fullyCovers + contribution.
     * Higher return value = preferred warehouse.
     */
    public function warehouseTiebreakerScore(int $totalAvailable): int;

    /**
     * Sorts locations in place according to the strategy's preference.
     * Uses $locationIndex (the live availability map) rather than entity state
     * so that already-allocated units within the current warehouse are reflected.
     *
     * @param WarehouseLocation[] $locations    (sorted in place)
     * @param array<int, int>     $locationIndex locationId -> currently available
     */
    public function sortLocations(array &$locations, array $locationIndex): void;
}
