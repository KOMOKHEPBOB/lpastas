<?php

declare(strict_types=1);

namespace App\Service\Order\Cancel;

use App\Entity\Order;
use App\Entity\OrderItemReservation;
use App\Enum\OrderStatus;
use App\Exception\DomainException;
use App\Exception\InternalException;
use App\Message\Cancel\RecalculateOrderAllocationMessage;
use App\Repository\OrderItemReservationRepository;
use App\Repository\OrderRepository;
use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderCanceler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly OrderRepository $orderRepository,
        private readonly OrderItemReservationRepository $orderItemReservationRepository,
        private readonly WarehouseLocationRepository $warehouseLocationRepository,
    ) {
    }

    /**
     * @param Order $order
     * @return void
     * @throws DomainException
     * @throws InternalException
     */
    public function cancelOrder(Order $order): void
    {
        $order->getStatus()->assertOrderCanTransition($order->getId(), OrderStatus::Cancelled);

        $this->entityManager->wrapInTransaction(function () use ($order): void {
            $this->orderRepository->findAndLock($order->getId());

            $reservations = $this->orderItemReservationRepository->findOrderReservations($order);
            $locationIds = array_map(static fn ($reservation) => $reservation->getWarehouseLocation()->getId(), $reservations);
            $releasedProductIds = $this->getUniqueReleasedProductIds($reservations);

            $lockedLocations = $this->warehouseLocationRepository->findAndLock($locationIds);
            foreach ($reservations as $reservation) {
                $locationId = $reservation->getWarehouseLocation()->getId();

                $lockedLocations[$locationId]->releaseQuantityReserved($reservation->getQuantityReserved());
                $this->entityManager->remove($reservation);
            }

            $order->setStatus(OrderStatus::Cancelled);

            $ordersToRecalculate = $this->orderRepository->findOrdersToRecalculate($order, $releasedProductIds);
            $this->queueRecalculations($ordersToRecalculate);
        });
    }

    /**
     * @param OrderItemReservation[] $reservations
     * @return int[]
     */
    private function getUniqueReleasedProductIds(array $reservations): array
    {
        $releasedProductIds = array_map(
            static fn($reservation) => $reservation->getWarehouseLocation()->getProduct()->getId(),
            $reservations
        );

        return array_unique($releasedProductIds);
    }

    /**
     * @param array $ordersToRecalculate
     * @return void
     * @throws InternalException
     */
    private function queueRecalculations(array $ordersToRecalculate): void
    {
        if (empty($ordersToRecalculate)) {
            return;
        }

        try {
            foreach ($ordersToRecalculate as $order) {
                $this->messageBus->dispatch(new RecalculateOrderAllocationMessage($order->getId()));
            }
        } catch (ExceptionInterface $e) {
            throw new InternalException(
                message: $e->getMessage(),
                previous: $e,
            );
        }
    }
}
