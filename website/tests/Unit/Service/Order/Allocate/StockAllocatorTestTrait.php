<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Order\Allocate;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseLocation;
use App\ParametersObject\AllocationResultPo;
use App\Service\Order\Allocate\AllocationStrategyInterface;
use App\Service\Order\Allocate\StockAllocator;

trait StockAllocatorTestTrait
{
    private const int WH1 = 1;
    private const int WH2 = 2;

    private const int LAPTOP = 1;
    private const int MOUSE = 2;

    /**
     * @return array<string, array{
     *     warehouseStock: array<int, array<int, int>>,
     *     requestedQuantities: array<int, int>,
     *     expectedTotalAllocated: int,
     *     expectedIsFullyAllocated: bool,
     *     expectedMissingPerProduct: array<int, int>,
     *     expectedWarehousesUsed: int,
     * }>
     */
    public static function allocationScenariosProvider(): array
    {
        return [
            'Single warehouse, single product fully covered' => [
                'warehouseStock' => [self::WH1 => [self::LAPTOP => 50]],
                'requestedQuantities' => [self::LAPTOP => 30],

                'expectedTotalAllocated' => 30,
                'expectedIsFullyAllocated' => true,
                'expectedMissingPerProduct' => [],
                'expectedWarehousesUsed' => 1,
            ],

            'Single warehouse, single product insufficient stock' => [
                'warehouseStock' => [self::WH1 => [self::LAPTOP => 10]],
                'requestedQuantities' => [self::LAPTOP => 30],

                'expectedTotalAllocated' => 10,
                'expectedIsFullyAllocated' => false,
                'expectedMissingPerProduct' => [self::LAPTOP => 20],
                'expectedWarehousesUsed' => 1,
            ],

            'No stock available, nothing allocated' => [
                'warehouseStock' => [],
                'requestedQuantities' => [self::LAPTOP => 10],

                'expectedTotalAllocated' => 0,
                'expectedIsFullyAllocated' => false,
                'expectedMissingPerProduct' => [self::LAPTOP => 10],
                'expectedWarehousesUsed' => 0,
            ],

            'Two warehouses one fully covers both products' => [
                // W1 can cover both, W2 can only cover product 1.
                // Should use only W1.
                'warehouseStock' => [
                    self::WH1 => [self::LAPTOP => 60, self::MOUSE => 60],
                    self::WH2 => [self::LAPTOP => 60, self::MOUSE => 0],
                ],
                'requestedQuantities' => [self::LAPTOP => 50, self::MOUSE => 50],

                'expectedTotalAllocated' => 100,
                'expectedIsFullyAllocated' => true,
                'expectedMissingPerProduct' => [],
                'expectedWarehousesUsed' => 1,
            ],

            'All three warehouses needed for picking' => [
                // W1: Laptop=50, Mouse=0   -> total=50
                // W2: Laptop=0,  Mouse=50  -> total=50
                // W3: Laptop=10, Mouse=10  -> total=20
                // Order: Laptop=60, Mouse=60
                //
                // Naive greedy (per-item):
                //   Laptop -> W1(50) then W3(10) = 2 WHs
                //   Mouse  -> W2(50) then W3(10) = 2 WHs
                //   Total: 3 unique warehouses used ✗
                //
                // Global scoring:
                //   W1 fully covers 0 products, contributes 50 to Laptop
                //   W2 fully covers 0 products, contributes 50 to Mouse
                //   W3 fully covers 0 products, contributes 20 total
                //   Tiebreak by contribution: W1=50, W2=50 (tie) -> pick either
                //   After W1: remaining = Laptop=10, Mouse=60
                //   Now W2 covers Mouse partially (50<60)
                //   W2 contribution=50 Mouse, W3=10 Mouse + 10 Laptop
                //   W2 wins -> take 50 Mouse
                //   Remaining: Laptop=10, Mouse=10
                //   W3: fully covers both -> take from W3
                //   Result: 3 WHs — this case genuinely needs 3
                'warehouseStock' => [
                    self::WH1 => [self::LAPTOP => 50, self::MOUSE => 0],
                    self::WH2 => [self::LAPTOP => 0, self::MOUSE => 50],
                    3 => [self::LAPTOP => 10, self::MOUSE => 10],
                ],
                'requestedQuantities' => [self::LAPTOP => 60, self::MOUSE => 60],

                'expectedTotalAllocated' => 120,
                'expectedIsFullyAllocated' => true,
                'expectedMissingPerProduct' => [],
                'expectedWarehousesUsed' => 3,
            ],

            'Skip third warehouse when two warehouses cover the order' => [
                // W1: Laptop=50, Mouse=30  -> can cover Laptop alone, partial Mouse
                // W2: Laptop=30, Mouse=50  -> can cover Mouse alone, partial Laptop
                // W3: Laptop=10, Mouse=20  -> small specialist
                // Order: Laptop=50, Mouse=50
                //
                // Scoring round 1:
                //   W1: fullyCovers=1(Laptop), contribution=80
                //   W2: fullyCovers=1(Mouse),  contribution=80
                //   W3: fullyCovers=0,         contribution=30
                //   W1 and W2 tie on fullyCovers and contribution -> tiebreaker
                //   Both have same totalAvailable=80 -> pick either (say W1)
                // After W1: remaining=Laptop=0, Mouse=20
                // Round 2:
                //   W2: fullyCovers=1(Mouse=50>=20), contribution=20
                //   W3: fullyCovers=1(Mouse=20>=20), contribution=20
                //   Tie on fullyCovers+contribution -> tiebreaker (FewestWarehouses: W2 wins, more stock)
                // Result: 2 warehouses ✓ (no W3 needed)
                'warehouseStock' => [
                    self::WH1 => [self::LAPTOP => 50, self::MOUSE => 30],
                    self::WH2 => [self::LAPTOP => 30, self::MOUSE => 50],
                    3 => [self::LAPTOP => 10, self::MOUSE => 20],
                ],
                'requestedQuantities' => [self::LAPTOP => 50, self::MOUSE => 50],

                'expectedTotalAllocated' => 100,
                'expectedIsFullyAllocated' => true,
                'expectedMissingPerProduct' => [],
                'expectedWarehousesUsed' => 2,
            ],

            'Four warehouses but single one covers full order' => [
                'warehouseStock' => [
                    self::WH1 => [self::LAPTOP => 200, self::MOUSE => 200],
                    self::WH2 => [self::LAPTOP => 50, self::MOUSE => 0],
                    3 => [self::LAPTOP => 0, self::MOUSE => 50],
                    4 => [self::LAPTOP => 10, self::MOUSE => 10],
                ],
                'requestedQuantities' => [self::LAPTOP => 100, self::MOUSE => 100],

                'expectedTotalAllocated' => 200,
                'expectedIsFullyAllocated' => true,
                'expectedMissingPerProduct' => [],
                'expectedWarehousesUsed' => 1,
            ],

            'Partial order due to insufficient stock' => [
                'warehouseStock' => [
                    self::WH1 => [self::LAPTOP => 5, self::MOUSE => 5],
                    self::WH2 => [self::LAPTOP => 5, self::MOUSE => 5],
                ],
                'requestedQuantities' => [self::LAPTOP => 20, self::MOUSE => 20],

                'expectedTotalAllocated' => 20,
                'expectedIsFullyAllocated' => false,
                'expectedMissingPerProduct' => [self::LAPTOP => 10, self::MOUSE => 10],
                'expectedWarehousesUsed' => 2,
            ],
        ];
    }

    /**
     * @param array<int, array<int, int>> $warehouseStock warehouseId -> productId -> available
     * @return array<int, array<int, WarehouseLocation[]>>
     */
    private function createLocationsByWarehouse(array $warehouseStock): array
    {
        $grouped = [];
        $globalId = 1;

        foreach ($warehouseStock as $warehouseId => $productAvailabilities) {
            foreach ($productAvailabilities as $productId => $available) {
                if ($available <= 0) {
                    continue;
                }
                $grouped[$warehouseId][$productId][] = $this->createWarehouseLocation(
                    id: $globalId++,
                    warehouseId: $warehouseId,
                    productId: $productId,
                    available: $available,
                );
            }
        }

        return $grouped;
    }

    /**
     * @param array<int, array<int, int[]>> $structure warehouseId -> productId -> [available, ...]
     * @return array<int, array<int, WarehouseLocation[]>>
     */
    private function createLocationsByWarehouseWithLocations(array $structure): array
    {
        $grouped = [];
        $globalId = 1;

        foreach ($structure as $warehouseId => $productLocations) {
            foreach ($productLocations as $productId => $availabilities) {
                foreach ($availabilities as $available) {
                    $grouped[$warehouseId][$productId][] = $this->createWarehouseLocation(
                        id: $globalId++,
                        warehouseId: $warehouseId,
                        productId: $productId,
                        available: $available,
                    );
                }
            }
        }

        return $grouped;
    }

    private function createProduct(int $id): Product
    {
        $product = new Product();
        $product->setId($id);

        return $product;
    }

    private function createWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = new Warehouse();
        $warehouse->setId($warehouseId);
        $warehouse->setTitle('Warehouse ' . $warehouseId);

        return $warehouse;
    }

    private function createWarehouseLocation(int $id, int $warehouseId, int $productId, int $available): WarehouseLocation
    {
        $warehouseLocation = new WarehouseLocation();
        $warehouseLocation->setId($id);
        $warehouseLocation->setWarehouse($this->createWarehouse($warehouseId));
        $warehouseLocation->setProduct($this->createProduct($productId));
        $warehouseLocation->setLocationCode('LOC-' . $id);
        $warehouseLocation->setQuantity($available);
        $warehouseLocation->setQuantityReserved(0);

        return $warehouseLocation;
    }

    private function countUniqueWarehouses(array $locationsByWarehouse, AllocationResultPo $result): int
    {
        return count(
            array_unique(
                array_map(
                    fn($line) => $this->getWarehouseIdForLocation($locationsByWarehouse, $line->warehouseLocationId),
                    $result->getLines()
                )
            )
        );
    }

    private function getAllocator(AllocationStrategyInterface $strategy): StockAllocator
    {
        return new StockAllocator($strategy);
    }

    /**
     * @param array<int, array<int, WarehouseLocation[]>> $locationsByWarehouse
     * @param int $locationId
     * @return int
     */
    private function getWarehouseIdForLocation(array $locationsByWarehouse, int $locationId): int
    {
        foreach ($locationsByWarehouse as $warehouseId => $byProduct) {
            foreach ($byProduct as $locations) {
                if (array_any($locations, fn($location) => $location->getId() === $locationId)) {
                    return $warehouseId;
                }
            }
        }

        throw new RuntimeException('Location ' . $locationId . ' not found in structure.');
    }
}
