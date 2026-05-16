<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class OrderItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public readonly int $productId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public readonly int $quantity,
    ) {
    }
}
