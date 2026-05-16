<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Entity\WarehouseLocation;
use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\Query\QueryException;

readonly class LockedProductLocationsProvider
{
    public function __construct(
        private WarehouseLocationRepository $warehouseLocationRepository,
    ) {
    }

    /**
     * @param array $productIds
     * @return array<int, array<int, WarehouseLocation[]>> warehouse ID -> product ID -> WarehouseLocation[]
     * @throws QueryException
     */
    public function getLockedProductLocations(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $inStockProductIds = $this->warehouseLocationRepository->findProductIdsInStock($productIds);
        if (empty($inStockProductIds)) {
            return [];
        }

        $lockedLocations = $this->warehouseLocationRepository->findAndLock($inStockProductIds);

        $grouped = [];
        foreach ($lockedLocations as $location) {
            $warehouseId = $location->getWarehouseId();
            $productId = $location->getProductId();

            $grouped[$warehouseId][$productId][] = $location;
        }

        return $grouped;
    }
}
