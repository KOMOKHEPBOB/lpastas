<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\WarehouseLocationRepository;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('OrderApi')]
final class WarehouseLocationRepositoryTest extends IntegrationTestCase
{
    private WarehouseLocationRepository $warehouseLocationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouseLocationRepository = $this->get(WarehouseLocationRepository::class);
    }

    // -------------------------------------------------------------------------
    // findAndLock
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldReturnEmptyArrayWhenFindAndLockCalledWithEmptyIds(): void
    {
        $result = $this->warehouseLocationRepository->findAndLock([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function shouldReturnEmptyArrayWhenFindAndLockCalledWithNonExistentIds(): void
    {
        $result = $this->warehouseLocationRepository->findAndLock([999999, 999998]);

        self::assertSame([], $result);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function shouldNotThrowWhenFindAndLockCalledWithValidIdsOnEmptyDatabase(): void
    {
        $this->warehouseLocationRepository->findAndLock([1, 2, 3]);
    }

    #[Test]
    public function shouldReturnEmptyArrayWhenFindProductIdsInStockCalledWithEmptyProductIds(): void
    {
        $result = $this->warehouseLocationRepository->findProductIdsInStock([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function shouldReturnEmptyArrayWhenFindProductIdsInStockCalledWithNonExistentProductIds(): void
    {
        $result = $this->warehouseLocationRepository->findProductIdsInStock([999999, 999998]);

        self::assertSame([], $result);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function shouldNotThrowWhenFindProductIdsInStockCalledWithValidIdsOnEmptyDatabase(): void
    {
        $this->warehouseLocationRepository->findProductIdsInStock([1, 2, 3]);
    }
}
