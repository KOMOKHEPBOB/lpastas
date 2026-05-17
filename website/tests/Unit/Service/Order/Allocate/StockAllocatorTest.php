<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Order\Allocate;

use App\Service\Order\Allocate\AllocationStrategies\FewestWarehousesStrategy;
use App\Service\Order\Allocate\StockAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('OrderApi')]
final class StockAllocatorTest extends TestCase
{
    use StockAllocatorTestTrait;

    private StockAllocator $anyStrategyAllocator;

    protected function setUp(): void
    {
        $this->anyStrategyAllocator = $this->getAllocator(new FewestWarehousesStrategy());
    }

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
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $this->anyStrategyAllocator->allocate($locationsByWarehouse, $requestedQuantities);

        $this->assertSame($expectedTotalAllocated, $result->getTotalAllocated());
        $this->assertSame($expectedIsFullyAllocated, $result->isFullyAllocated());
        $this->assertSame($expectedMissingPerProduct, $result->getMissingPerProduct());
        $this->assertSame($expectedWarehousesUsed, $this->countUniqueWarehouses($locationsByWarehouse, $result));
    }

    #[Test]
    public function shouldPickWarehouseWhichFullyCoversMostOfTheRemainingItems(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 60, self::MOUSE => 30], // fully covers Laptop, partial Mouse
            self::WH2 => [self::LAPTOP => 60, self::MOUSE => 60], // fully covers both
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $this->anyStrategyAllocator->allocate(
            $locationsByWarehouse,
            [self::LAPTOP => 50, self::MOUSE => 50]
        );

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
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 80, self::MOUSE => 0],
            self::WH2 => [self::LAPTOP => 0, self::MOUSE => 80],
            3 => [self::LAPTOP => 0, self::MOUSE => 20],
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $this->anyStrategyAllocator->allocate(
            $locationsByWarehouse,
            [self::LAPTOP => 80, self::MOUSE => 20]
        );

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(80 + 20, $result->getTotalAllocated());
        $this->assertSame(2, $this->countUniqueWarehouses($locationsByWarehouse, $result));
    }

    #[Test]
    public function shouldAllocateAllAmountIfThereIsNotEnoughStock(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 5],
            self::WH2 => [self::LAPTOP => 3],
        ];

        $result = $this->anyStrategyAllocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 20]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(8, $result->getTotalAllocated());
        $this->assertSame(12, $result->getMissingForProduct(self::LAPTOP));
    }

    #[Test]
    public function shouldAllocateAllAmountIfThereIsNotEnoughStockOfNeededProduct(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 100],
            self::WH2 => [self::MOUSE => 5],
        ];

        $result = $this->anyStrategyAllocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::MOUSE => 10]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(5, $result->getTotalAllocated());
        $this->assertSame(5, $result->getMissingForProduct(self::MOUSE));
    }

    #[Test]
    public function shouldNotAllocateMoreThanAvailableFromAnyLocation(): void
    {
        $locationsByWarehouse = $this->createLocationsByWarehouseWithLocations([
            self::WH1 => [self::LAPTOP => [10]],
            self::WH2 => [self::LAPTOP => [20]],
        ]);

        $originalAvailability = [];
        foreach ($locationsByWarehouse as $byProduct) {
            foreach ($byProduct as $locations) {
                foreach ($locations as $location) {
                    $originalAvailability[$location->getId()] = $location->getQuantityAvailable();
                }
            }
        }

        $result = $this->anyStrategyAllocator->allocate($locationsByWarehouse, [self::LAPTOP => 25]);

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
    public function shouldProvideMissingQuantityPerProduct(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 5, self::MOUSE => 3],
        ];

        $result = $this->anyStrategyAllocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 10, self::MOUSE => 10]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertSame(5, $result->getMissingForProduct(self::LAPTOP));
        $this->assertSame(7, $result->getMissingForProduct(self::MOUSE));
        $this->assertSame([self::LAPTOP => 5, self::MOUSE => 7], $result->getMissingPerProduct());
    }

    #[Test]
    public function shouldBeAbleToProvideLinesPerProduct(): void
    {
        $warehouseStock = [
            self::WH1 => [self::LAPTOP => 50, self::MOUSE => 50],
        ];

        $result = $this->anyStrategyAllocator->allocate(
            $this->createLocationsByWarehouse($warehouseStock),
            [self::LAPTOP => 20, self::MOUSE => 30]
        );

        $linesForLaptop = $result->getLinesForProduct(self::LAPTOP);
        $linesForMouse = $result->getLinesForProduct(self::MOUSE);

        $this->assertNotEmpty($linesForLaptop);
        $this->assertNotEmpty($linesForMouse);

        foreach ($linesForLaptop as $line) {
            $this->assertSame(self::LAPTOP, $line->productId);
        }
        foreach ($linesForMouse as $line) {
            $this->assertSame(self::MOUSE, $line->productId);
        }
    }
}
