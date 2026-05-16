<?php

declare(strict_types=1);

namespace App\ParametersObject;

/**
 * Holds the result of allocating an entire order across warehouses.
 *
 * Lines are grouped by productId so OrderService can iterate per-item
 * when writing OrderItemReservation records.
 */
final class AllocationResultPo
{
    /** @var AllocationLinePo[] */
    private array $lines = [];

    /** @var array<int, int> productId -> totalAllocated */
    private array $allocatedPerProduct = [];

    /** @var array<int, int> productId -> quantityRequested */
    private array $requestedPerProduct;

    /**
     * @param array<int, int> $requestedPerProduct productId -> quantityRequested
     */
    public function __construct(array $requestedPerProduct)
    {
        $this->requestedPerProduct = $requestedPerProduct;

        foreach (array_keys($requestedPerProduct) as $productId) {
            $this->allocatedPerProduct[$productId] = 0;
        }
    }

    public function addLine(AllocationLinePo $line): void
    {
        $this->lines[] = $line;
        $this->allocatedPerProduct[$line->productId] += $line->quantityAllocated;
    }

    /** @return AllocationLinePo[] */
    public function getLines(): array
    {
        return $this->lines;
    }

    /** @return AllocationLinePo[] */
    public function getLinesForProduct(int $productId): array
    {
        return array_values(
            array_filter(
                $this->lines,
                static fn(AllocationLinePo $l) => $l->productId === $productId
            )
        );
    }

    public function getTotalAllocated(): int
    {
        return array_sum($this->allocatedPerProduct);
    }

    public function getTotalRequested(): int
    {
        return array_sum($this->requestedPerProduct);
    }

    public function getAllocatedForProduct(int $productId): int
    {
        return $this->allocatedPerProduct[$productId] ?? 0;
    }

    public function getRequestedForProduct(int $productId): int
    {
        return $this->requestedPerProduct[$productId] ?? 0;
    }

    public function getMissingForProduct(int $productId): int
    {
        return max(0, $this->getRequestedForProduct($productId) - $this->getAllocatedForProduct($productId));
    }

    public function isProductFullyAllocated(int $productId): bool
    {
        return $this->getMissingForProduct($productId) === 0;
    }

    public function isFullyAllocated(): bool
    {
        return array_all(
            array_keys($this->requestedPerProduct),
            fn($productId) => $this->isProductFullyAllocated($productId)
        );

    }

    /** @return array<int, int> productId -> missingQuantity, only for unfulfilled products */
    public function getMissingPerProduct(): array
    {
        $missing = [];
        foreach (array_keys($this->requestedPerProduct) as $productId) {
            $qty = $this->getMissingForProduct($productId);
            if ($qty > 0) {
                $missing[$productId] = $qty;
            }
        }

        return $missing;
    }
}
