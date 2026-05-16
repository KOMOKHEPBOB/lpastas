<?php

declare(strict_types=1);

namespace App\ParametersObject;

final readonly class AllocationLinePo
{
    public function __construct(
        public int $productId,
        public int $warehouseLocationId,
        public int $quantityAllocated,
    ) {
    }
}
