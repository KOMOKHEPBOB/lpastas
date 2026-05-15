<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateOrderRequest
{
    /**
     * @param OrderItemRequest[] $orderItemRequests
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public readonly array $orderItemRequests,
    ) {
    }
}
