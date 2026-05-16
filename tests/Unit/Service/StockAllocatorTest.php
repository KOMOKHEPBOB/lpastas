<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseLocation;
use App\Enum\AllocationStrategy;
use App\ParametersObject\AllocationResultPo;
use App\Service\Order\Allocate\StockAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('OrderApi')]
final class StockAllocatorTest extends TestCase
{
    private const int WH1 = 1;
    private const int WH2 = 2;

    private const int LAPTOP = 1;
    private const int MOUSE = 2;

    #[Test]
    #[DataProvider('allocationScenariosProvider')]
    public function shouldAllocateWholeOrderCorrectly(
        array $warehouseStock,
        array $requestedQuantities,
        int $expectedTotalAllocated,
        bool $expectedIsFullyAllocated,
        array $expectedMissingPerProduct,
        int $expectedWarehousesUsed,
    ): void {
        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $allocator->allocate($locationsByWarehouse, $requestedQuantities);

        $this->assertSame($expectedTotalAllocated, $result->getTotalAllocated());
        $this->assertSame($expectedIsFullyAllocated, $result->isFullyAllocated());
        $this->assertSame($expectedMissingPerProduct, $result->getMissingPerProduct());
        $this->assertSame($expectedWarehousesUsed, $this->countUniqueWarehouses($locationsByWarehouse, $result));
    }

    #[Test]
    public function shouldPickWarehouseWhichFullyCoversMostOfTheRemainingItems(): void
    {
        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 60, self::MOUSE => 30], // fully covers Laptop, partial Mouse
            self::WH2 => [self::LAPTOP => 60, self::MOUSE => 60], // fully covers both
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $allocator->allocate($locationsByWarehouse, [self::LAPTOP => 50, self::MOUSE => 50]);

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(
            1,
            $this->countUniqueWarehouses($locationsByWarehouse, $result),
            'W2 alone covers the order — only 1 WH should be used.'
        );
    }

    #[Test]
    public function shouldRescoreRemainingWarehousesAfterEachCommitedWarehouse(): void
    {
        // After committing W1 (large but only has Laptop), the remaining need
        // is Mouse=20 only. W3 fully covers Mouse=20 with less stock than W2
        // (Mouse=80). Under FewestWarehouses W2 wins tiebreaker (more stock).
        // Under EmptyLocationsFirst W3 wins (less stock).
        // Both result in 2 total warehouses — key thing is W3 is not picked
        // when it shouldn't be.
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 80, self::MOUSE => 0],  // covers Laptop only
            self::WH2 => [self::LAPTOP => 0, self::MOUSE => 80],  // covers Mouse well
            3 => [self::LAPTOP => 0, self::MOUSE => 20],          // covers Mouse exactly
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate($locationsByWarehouse, [self::LAPTOP => 80, self::MOUSE => 20]);

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(80 + 20, $result->getTotalAllocated());
        $this->assertSame(2, $this->countUniqueWarehouses($locationsByWarehouse, $result));
    }

    #[Test]
    public function shouldAllocateAllAmountIfThereIsNotEnoughStock(): void
    {
        // Total stock across all warehouses is less than requested.
        // The loop must terminate without infinite-looping even though
        // remaining is never fully emptied.
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 5],
            self::WH2 => [self::LAPTOP => 3],
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 20]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(8, $result->getTotalAllocated());
        $this->assertSame(12, $result->getMissingForProduct(1));
    }

    #[Test]
    public function shouldAllocateAllAmountIfThereIsNotEnoughStockOfNeededProduct(): void
    {
        // W1 has product 1 but order only needs product 2.
        // W2 has product 2 but zero stock.
        // After W2 is exhausted, W1 contributes nothing to remaining — must break.
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 100],  // wrong product
            self::WH2 => [self::MOUSE => 5],     // right product, insufficient
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::MOUSE => 10]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(5, $result->getTotalAllocated());
        $this->assertSame(5, $result->getMissingForProduct(self::MOUSE));
    }

    #[Test]
    public function shouldNotAllocateMoreThanRequested(): void
    {
        $locationsByWarehouse = $this->createLocationsByWarehouseWithLocations([
            self::WH1 => [self::LAPTOP => [10]],  // W1: one location, 10 available for product 1
            self::WH2 => [self::LAPTOP => [20]],  // W2: one location, 20 available for product 1
        ]);

        $originalAvailability = [];
        foreach ($locationsByWarehouse as $byProduct) {
            foreach ($byProduct as $locations) {
                foreach ($locations as $location) {
                    $originalAvailability[$location->getId()] = $location->getQuantityAvailable();
                }
            }
        }

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate($locationsByWarehouse, [self::LAPTOP => 25]);

        $this->assertTrue($result->isFullyAllocated());

        $allocatedPerLocation = [];
        foreach ($result->getLines() as $line) {
            $allocatedPerLocation[$line->warehouseLocationId] =
                ($allocatedPerLocation[$line->warehouseLocationId] ?? 0) + $line->quantityAllocated;
        }

        foreach ($allocatedPerLocation as $locationId => $totalAllocated) {
            $this->assertLessThanOrEqual(
                $originalAvailability[$locationId],
                $totalAllocated,
                sprintf(
                    'Location %d was allocated %d units but only had %d available.',
                    $locationId,
                    $totalAllocated,
                    $originalAvailability[$locationId],
                )
            );
        }
    }

    #[Test]
    public function shouldPrefersWarehouseWithMoreStockWHenFewestWarehousesStrategyIsSelected(): void
    {
        // Both W1 and W2 fully cover the remaining Mouse=20.
        // FewestWarehouses -> prefer W1 (more stock, 80 > 20).
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 0, self::MOUSE => 80], // more Mouse stock
            self::WH2 => [self::LAPTOP => 0, self::MOUSE => 20], // exactly enough Mouse stock
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::WH2 => 20]
        );

        $this->assertTrue($result->isFullyAllocated());
        // The allocation should come from W1 (location with 80 available).
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);
        $usedWarehouseId = $this->getWarehouseIdForLocation(
            $locationsByWarehouse,
            $result->getLines()[0]->warehouseLocationId
        );
        $this->assertSame(1, $usedWarehouseId, 'FewestWarehouses should prefer W1 (more stock).');
    }

    #[Test]
    public function shouldPickFromLessStockWarehouseWhenEmptyLocationStrategyIsSelected(): void
    {
        // Both W1 and W2 fully cover Mouse=20.
        // EmptyLocationsFirst -> prefer W2 (less stock, 20 < 80).
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 0, self::MOUSE => 80], // more Mouse stock
            self::WH2 => [self::LAPTOP => 0, self::MOUSE => 20], // exactly enough Mouse stock
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::EmptyLocationsFirst);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::WH2 => 20]
        );

        $this->assertTrue($result->isFullyAllocated());
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);
        $usedWarehouseId = $this->getWarehouseIdForLocation(
            $locationsByWarehouse,
            $result->getLines()[0]->warehouseLocationId
        );
        $this->assertSame(2, $usedWarehouseId, 'EmptyLocationsFirst should prefer W2 (less stock).');
    }

    #[Test]
    public function shouldPrioritiseLocationsWithTheBiggestStock(): void
    {
        // Single warehouse, two locations for the same product.
        // L1=40 available, L2=15 available. Order=45.
        // Should take all 40 from L1, then 5 from L2.
        $locationsByWarehouse = $this->createLocationsByWarehouseWithLocations([
            // two locations with qty 40 and 15
            self::WH1 => [self::LAPTOP => [40, 15]],
        ]);

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate($locationsByWarehouse, [self::LAPTOP => 45]);

        $allocationLines = $result->getLines();
        $this->assertTrue($result->isFullyAllocated());
        $this->assertCount(2, $allocationLines);
        $this->assertSame(40, $allocationLines[0]->quantityAllocated);
        $this->assertSame(5, $allocationLines[1]->quantityAllocated);
    }

    #[Test]
    public function shouldProvideMissingQuantityPerProduct(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 5, self::MOUSE => 3],
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 10, self::MOUSE => 10]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(5, $result->getMissingForProduct(self::LAPTOP));
        $this->assertSame(7, $result->getMissingForProduct(2));
        $this->assertSame([self::LAPTOP => 5, self::MOUSE => 7], $result->getMissingPerProduct());
    }

    #[Test]
    public function shouldBeAbleToProvideLinesPerProduct(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 50, self::MOUSE => 50],
        ];

        $allocator = $this->getStockAllocator(AllocationStrategy::FewestWarehouses);
        $result = $allocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 20, self::MOUSE => 30]
        );

        $linesForProduct1 = $result->getLinesForProduct(self::LAPTOP);
        $linesForProduct2 = $result->getLinesForProduct(self::MOUSE);

        $this->assertNotEmpty($linesForProduct1);
        $this->assertNotEmpty($linesForProduct2);

        foreach ($linesForProduct1 as $line) {
            $this->assertSame(self::LAPTOP, $line->productId);
        }
        foreach ($linesForProduct2 as $line) {
            $this->assertSame(self::MOUSE, $line->productId);
        }
    }

    /**
     * @return array<string, array{
     *     warehouseStock: array<int, array<int, int>>,
     *     requestedQuantities: array<int, int>,
     *
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

            'Two warehouses, one fully covers both products' => [
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

            'All three Warehouses have to be used for picking' => [
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

            'Skip third Warehouse when we can fully cover the order from two Warehouses' => [
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

            'Four Warehouses but we can fully cover the order from single one' => [
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

            'Partially cover the order because of insufficient stock' => [
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
                rsort($availabilities); // largest first, mirrors repository ordering
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
        $warehouse = $this->createWarehouse($warehouseId);
        $product = $this->createProduct($id);

        $warehouseLocation = new WarehouseLocation();
        $warehouseLocation->setId($id);
        $warehouseLocation->setWarehouse($warehouse);
        $warehouseLocation->setProduct($product);
        $warehouseLocation->setLocationCode('LOC-' . $id);
        $warehouseLocation->setQuantity($available);
        $warehouseLocation->setQuantityReserved(0);

        return $warehouseLocation;
    }

    private function getStockAllocator(AllocationStrategy $strategy): StockAllocator
    {
        return new StockAllocator($strategy->value);
    }

    /**
     * @param array<int, array<int, WarehouseLocation[]>> $locationsByWarehouse
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

        throw new \RuntimeException('Location ' . $locationId . ' not found in structure.');
    }
}
