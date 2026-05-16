<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderItemReservationRepository;
use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;

class OrderUnAllocator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemReservationRepository $orderItemReservationRepository,
        private readonly WarehouseLocationRepository $warehouseLocationRepository,
    ) {
    }

    /**
     * @param Order $order
     * @return void
     * @throws QueryException
     */
    public function unAllocateOrder(Order $order): void
    {
        $reservations = $this->orderItemReservationRepository->findOrderReservations($order);
        $locationIds = array_map(static fn ($reservation) => $reservation->getWarehouseLocation()->getId(), $reservations);
        $lockedLocations = $this->warehouseLocationRepository->findAndLock($locationIds);
        foreach ($reservations as $reservation) {
            $locationId = $reservation->getWarehouseLocation()->getId();

            $lockedLocations[$locationId]->releaseQuantityReserved($reservation->getQuantityReserved());
            $this->entityManager->remove($reservation);
        }

        $order->setStatus(OrderStatus::Pending);
    }
}
