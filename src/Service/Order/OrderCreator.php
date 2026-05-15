<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\DTO\CreateOrderRequest;
use App\Entity\Order;
use App\Exception\ProductDoesNotExistException;

class OrderCreator
{
    public function __construct(
        private readonly CreateOrderRequestValidator $createOrderRequestValidator,
    ) {
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     * @return Order
     * @throws ProductDoesNotExistException
     */
    public function createOrder(CreateOrderRequest $createOrderRequest): Order
    {
        $this->createOrderRequestValidator->validate($createOrderRequest);

        return new Order();
    }
}
