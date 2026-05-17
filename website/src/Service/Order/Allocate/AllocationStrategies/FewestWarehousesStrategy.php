<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate\AllocationStrategies;

use App\Service\Order\Allocate\AllocationStrategyInterface;

final class FewestWarehousesStrategy implements AllocationStrategyInterface
{
    public function warehouseTiebreakerScore(int $totalAvailable): int
    {
        return $totalAvailable; // more stock = preferred
    }

    public function sortLocations(array &$locations, array $locationIndex): void
    {
        // more stock = preferred
        usort(
            $locations,
            static fn($a, $b) => ($locationIndex[$b->getId()] ?? 0) <=> ($locationIndex[$a->getId()] ?? 0)
        );
    }
}
