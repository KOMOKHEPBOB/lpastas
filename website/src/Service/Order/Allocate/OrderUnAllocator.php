<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Exception\DomainException;
use App\Repository\OrderItemReservationRepository;
use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;

readonly class OrderUnAllocator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderItemReservationRepository $orderItemReservationRepository,
        private WarehouseLocationRepository $warehouseLocationRepository,
    ) {
    }

    /**
     * @param Order $order
     * @return void
     * @throws DomainException
     * @throws QueryException
     */
    public function unAllocateOrder(Order $order): void
    {
        $order->setStatus(OrderStatus::Pending);

        $reservations = $this->orderItemReservationRepository->findOrderReservations($order);
        $locationIds = array_map(static fn ($reservation) => $reservation->getWarehouseLocation()->getId(), $reservations);
        $lockedLocations = $this->warehouseLocationRepository->findAndLock($locationIds);
        foreach ($reservations as $reservation) {
            $locationId = $reservation->getWarehouseLocation()->getId();

            $lockedLocations[$locationId]->releaseQuantityReserved($reservation->getQuantityReserved());
            $this->entityManager->remove($reservation);
        }
    }
}
