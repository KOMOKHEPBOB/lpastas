<?php

declare(strict_types=1);

namespace App\Service\Order\Create;

use App\DTO\CreateOrderRequest;
use App\Exception\ApiException;
use App\Service\Order\Allocate\OrderAllocator;
use Doctrine\ORM\EntityManagerInterface;

readonly class CreateOrderRequestHandler
{
    public function __construct(
        private CreateOrderRequestValidator $createOrderRequestValidator,
        private EntityManagerInterface $entityManager,
        private OrderAllocator $orderAllocator,
        private OrderCreator $orderCreator,
    ) {
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     * @return array
     * @throws ApiException
     */
    public function handleCreateOrderRequest(CreateOrderRequest $createOrderRequest): array
    {
        $this->createOrderRequestValidator->validate($createOrderRequest);

        return $this->entityManager->wrapInTransaction(function () use ($createOrderRequest): array {
            $order = $this->orderCreator->createAndPersistOrder($createOrderRequest);

            return [
                'order' => $order,
                'missingItems' => $this->orderAllocator->allocateAndReturnMissingItems($order),
            ];
        });
    }
}
