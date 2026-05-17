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
final class StockAllocatorFewestWarehousesTest extends TestCase
{
    use StockAllocatorTestTrait;

    private StockAllocator $fewestWarehousesStrategyAllocator;

    protected function setUp(): void
    {
        $this->fewestWarehousesStrategyAllocator = $this->getAllocator(new FewestWarehousesStrategy());
    }

    #[Test]
    public function shouldPreferWarehouseWithMoreStockWhenFewestWarehousesStrategyIsSelected(): void
    {
        $warehouseStock = [
            self::WH1 => [self::MOUSE => 80],
            self::WH2 => [self::MOUSE => 20],
        ];
        $locationsByWarehouse = $this->createLocationsByWarehouse($warehouseStock);

        $result = $this->fewestWarehousesStrategyAllocator->allocate($locationsByWarehouse, [self::MOUSE => 20]);

        $this->assertTrue($result->isFullyAllocated());
        $usedWarehouseId = $this->getWarehouseIdForLocation(
            $locationsByWarehouse,
            $result->getLines()[0]->warehouseLocationId
        );
        $this->assertSame(self::WH1, $usedWarehouseId, 'FewestWarehouses should prefer WH1 (more stock).');
    }

    #[Test]
    public function shouldPrioritiseLocationsWithBiggestStockWhenFewestWarehousesStrategyIsSelected(): void
    {
        // L1=40 available, L2=15 available. Order=45.
        // FewestWarehouses → largest first: take 40 from L1, then 5 from L2.
        $locationsByWarehouse = $this->createLocationsByWarehouseWithLocations([
            self::WH1 => [self::LAPTOP => [40, 15]],
        ]);

        $result = $this->fewestWarehousesStrategyAllocator->allocate($locationsByWarehouse, [self::LAPTOP => 45]);

        $lines = $result->getLines();
        $this->assertTrue($result->isFullyAllocated());
        $this->assertCount(2, $lines);
        $this->assertSame(40, $lines[0]->quantityAllocated, 'Should take from the fullest location first.');
        $this->assertSame(5, $lines[1]->quantityAllocated);
    }
}
