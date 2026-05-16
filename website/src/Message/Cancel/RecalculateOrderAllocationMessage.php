<?php

namespace App\Message\Cancel;

readonly class RecalculateOrderAllocationMessage
{
    public function __construct(
        public int $orderId,
    ) {
    }
}
