<?php

declare(strict_types=1);

namespace App\Service\Order\Ship;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Exception\DomainException;
use App\Repository\OrderItemReservationRepository;
use App\Repository\OrderRepository;
use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;

class OrderShipper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly OrderItemReservationRepository $orderItemReservationRepository,
        private readonly WarehouseLocationRepository $warehouseLocationRepository,
    ) {
    }

    /**
     * @param Order $order
     * @return void
     * @throws DomainException
     * @throws QueryException
     */
    public function shipOrder(Order $order): void
    {
        $this->validate($order);

        $this->entityManager->wrapInTransaction(function () use ($order): void {
            $this->orderRepository->findAndLock($order);

            $reservations = $this->orderItemReservationRepository->findOrderReservations($order);
            $locationIds = array_map(static fn ($reservation) => $reservation->getWarehouseLocation()->getId(), $reservations);

            $lockedLocations = $this->warehouseLocationRepository->findAndLock($locationIds);
            foreach ($reservations as $reservation) {
                $locationId = $reservation->getWarehouseLocation()->getId();

                $lockedLocations[$locationId]->consumeQuantityReserved($reservation->getQuantityReserved());
            }

            $order->setStatus(OrderStatus::Shipped);
        });
    }

    /**
     * @param Order $order
     * @return void
     * @throws DomainException
     */
    private function validate(Order $order): void
    {
        if (in_array($order->getStatus(), [OrderStatus::Reserved, OrderStatus::PartiallyReserved], true)) {
            return;
        }

        throw new DomainException(
            'Trying to ship order #%d with invalid status %d',
            $order->getId(),
            $order->getStatus()->value
        );
    }
}
