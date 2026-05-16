<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Enum\AllocationStrategy;
use App\ParametersObject\AllocationLinePo;
use App\ParametersObject\AllocationResultPo;

final class StockAllocator
{
    private AllocationStrategy $strategy;

    public function __construct(string $strategy)
    {
        $this->strategy = AllocationStrategy::from($strategy);
    }

    /**
     * @param array $locationsByWarehouse warehouseId -> productId -> WarehouseLocation[] (sorted by available DESC)
     * @param array<int, int> $requestedQuantities productId -> quantityRequested
     * @return AllocationResultPo
     */
    public function allocate(array $locationsByWarehouse, array $requestedQuantities): AllocationResultPo
    {
        $result = new AllocationResultPo($requestedQuantities);
        $remaining = $requestedQuantities;

        [$warehouseIndex, $locationIndex] = $this->buildAvailabilityIndexes($locationsByWarehouse);

        while (!empty($remaining)) {
            $bestWarehouseId = $this->pickBestWarehouse($warehouseIndex, $remaining);
            if ($bestWarehouseId === null) {
                break;
            }

            $this->allocateFromWarehouse(
                $locationsByWarehouse[$bestWarehouseId],
                $warehouseIndex[$bestWarehouseId],
                $locationIndex,
                $remaining,
                $result,
            );

            foreach (array_keys($remaining) as $productId) {
                if ($remaining[$productId] <= 0) {
                    unset($remaining[$productId]);
                }
            }

            if ($this->isWarehouseExhaustedForRemaining($warehouseIndex[$bestWarehouseId], $remaining)) {
                unset($warehouseIndex[$bestWarehouseId]);
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<int, int>> $warehouseIndex warehouseId -> productId -> available
     * @param array<int, int> $remaining productId -> needed
     */
    private function pickBestWarehouse(array $warehouseIndex, array $remaining): ?int
    {
        $bestWarehouseId = null;
        $bestScore = null;

        foreach ($warehouseIndex as $warehouseId => $availableByProduct) {
            $score = $this->scoreWarehouse($availableByProduct, $remaining);

            if ($score['contribution'] === 0) {
                continue;
            }

            if ($bestScore === null || $this->compareScores($score, $bestScore) > 0) {
                $bestScore = $score;
                $bestWarehouseId = $warehouseId;
            }
        }

        return $bestWarehouseId;
    }

    /**
     * @param array $locationsByProduct productId -> locations
     * @param array<int, int> $warehouseIndex productId -> total available (mutable)
     * @param array<int, int> $locationIndex locationId -> available (mutable)
     * @param array<int, int> $remaining productId -> needed (mutable)
     * @param AllocationResultPo $result
     */
    private function allocateFromWarehouse(
        array $locationsByProduct,
        array &$warehouseIndex,
        array &$locationIndex,
        array &$remaining,
        AllocationResultPo $result,
    ): void {
        foreach ($remaining as $productId => &$needed) {
            if ($needed <= 0) {
                continue;
            }

            $locations = $locationsByProduct[$productId] ?? [];

            foreach ($locations as $location) {
                if ($needed <= 0) {
                    break;
                }

                $locationAvailable = $locationIndex[$location->getId()] ?? 0;
                if ($locationAvailable <= 0) {
                    continue;
                }

                $toAllocate = min($needed, $locationAvailable);

                $result->addLine(
                    new AllocationLinePo(
                        productId: $productId,
                        warehouseLocationId: $location->getId(),
                        quantityAllocated: $toAllocate,
                    )
                );

                $needed -= $toAllocate;
                $warehouseIndex[$productId] -= $toAllocate;
                $locationIndex[$location->getId()] -= $toAllocate;
            }
        }
        unset($needed);
    }

    /**
     * @param array $locationsByWarehouse
     * @return array{array<int, array<int, int>>, array<int, int>}
     */
    private function buildAvailabilityIndexes(array $locationsByWarehouse): array
    {
        $warehouseIndex = [];
        $locationIndex = [];

        foreach ($locationsByWarehouse as $warehouseId => $locationsByProduct) {
            $warehouseIndex[$warehouseId] = [];

            foreach ($locationsByProduct as $productId => $locations) {
                $productTotal = 0;

                foreach ($locations as $location) {
                    $available = $location->getQuantityAvailable();
                    $locationIndex[$location->getId()] = $available;
                    $productTotal += $available;
                }

                $warehouseIndex[$warehouseId][$productId] = $productTotal;
            }
        }

        return [$warehouseIndex, $locationIndex];
    }

    /**
     * @param array{fullyCovers: int, contribution: int, tiebreaker: int} $a
     * @param array{fullyCovers: int, contribution: int, tiebreaker: int} $b
     */
    private function compareScores(array $a, array $b): int
    {
        if ($a['fullyCovers'] !== $b['fullyCovers']) {
            return $a['fullyCovers'] <=> $b['fullyCovers'];
        }

        if ($a['contribution'] !== $b['contribution']) {
            return $a['contribution'] <=> $b['contribution'];
        }

        return $a['tiebreaker'] <=> $b['tiebreaker'];
    }

    /**
     * @param array<int, int> $warehouseAvailable productId -> available
     * @param array<int, int> $remaining productId -> needed
     */
    private function isWarehouseExhaustedForRemaining(array $warehouseAvailable, array $remaining): bool
    {
        foreach (array_keys($remaining) as $productId) {
            if (($warehouseAvailable[$productId] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $availableByProduct productId -> available in this warehouse
     * @param array<int, int> $remaining productId -> still needed
     * @return array{fullyCovers: int, contribution: int, tiebreaker: int}
     */
    private function scoreWarehouse(array $availableByProduct, array $remaining): array
    {
        $fullyCovers = 0;
        $contribution = 0;
        $totalAvailable = 0;

        foreach ($remaining as $productId => $needed) {
            $available = $availableByProduct[$productId] ?? 0;
            $contribution += min($available, $needed);
            $totalAvailable += $available;

            if ($available >= $needed) {
                $fullyCovers++;
            }
        }

        $tiebreaker = match ($this->strategy) {
            AllocationStrategy::FewestWarehouses => $totalAvailable,
            AllocationStrategy::EmptyLocationsFirst => -$totalAvailable,
        };

        return [
            'fullyCovers' => $fullyCovers,
            'contribution' => $contribution,
            'tiebreaker' => $tiebreaker,
        ];
    }
}
