<?php

declare(strict_types=1);

namespace App\MessageHandler\Cancel;

use App\Message\Cancel\RecalculateOrderAllocationMessage;
use App\Repository\OrderRepository;
use App\Service\Order\Allocate\OrderAllocator;
use App\Service\Order\Allocate\OrderUnAllocator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RecalculateOrderAllocationMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly OrderAllocator $orderAllocator,
        private readonly OrderUnAllocator $orderUnAllocator,
    ) {
    }

    public function __invoke(RecalculateOrderAllocationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(function () use ($message) {
            $order = $this->orderRepository->findAndLock($message->orderId);
            $this->orderUnAllocator->unAllocateOrder($order);
            $this->orderAllocator->allocateAndReturnMissingItems($order);
        });
    }
}
