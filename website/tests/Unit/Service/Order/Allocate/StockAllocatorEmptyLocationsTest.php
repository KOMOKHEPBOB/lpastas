<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Order\Allocate;

use App\Service\Order\Allocate\AllocationStrategies\EmptyLocationsStrategy;
use App\Service\Order\Allocate\StockAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('OrderApi')]
final class StockAllocatorEmptyLocationsTest extends TestCase
{
    use StockAllocatorTestTrait;

    private StockAllocator $emptyLocationsStrategyAllocator;

    protected function setUp(): void
    {
        $this->emptyLocationsStrategyAllocator = $this->getAllocator(new EmptyLocationsStrategy());
    }

    #[Test]
    public function shouldPreferWarehouseWithLessStockWhenEmptyLocationsStrategyIsSelected(): void
    {
        $warehouseStock = [
            self::WH1 => [self::MOUSE => 80],
            self::WH2 => [self::MOUSE => 20],
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $this->emptyLocationsStrategyAllocator->allocate($locationsByWarehouse, [self::MOUSE => 20]);

        $this->assertTrue($result->isFullyAllocated());
        $usedWarehouseId = $this->getWarehouseIdForLocation(
            $locationsByWarehouse,
            $result->getLines()[0]->warehouseLocationId
        );
        $this->assertSame(self::WH2, $usedWarehouseId, 'EmptyLocationsFirst should prefer WH2 (less stock).');
    }

    #[Test]
    public function shouldPrioritiseLocationsWithSmallestStockWhenEmptyLocationsStrategyIsSelected(): void
    {
        // L1=40 available, L2=15 available. Order=45.
        // EmptyLocationsFirst → smallest first: drain L2(15) fully, then take 30 from L1.
        $locationsByWarehouse = $this->createLocationsByWarehouseWithLocations([
            self::WH1 => [self::LAPTOP => [40, 15]],
        ]);

        $result = $this->emptyLocationsStrategyAllocator->allocate($locationsByWarehouse, [self::LAPTOP => 45]);

        $lines = $result->getLines();
        $this->assertTrue($result->isFullyAllocated());
        $this->assertCount(2, $lines);
        $this->assertSame(15, $lines[0]->quantityAllocated, 'Should drain the smallest location first.');
        $this->assertSame(30, $lines[1]->quantityAllocated);
    }
}
