<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate\AllocationStrategies;

use App\Service\Order\Allocate\AllocationStrategyInterface;

final class EmptyLocationsStrategy implements AllocationStrategyInterface
{
    public function warehouseTiebreakerScore(int $totalAvailable): int
    {
        return -$totalAvailable; // less stock = preferred
    }

    public function sortLocations(array &$locations, array $locationIndex): void
    {
        // less stock = preferred
        usort(
            $locations,
            static fn($a, $b) => ($locationIndex[$a->getId()] ?? 0) <=> ($locationIndex[$b->getId()] ?? 0)
        );
    }
}
