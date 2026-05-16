<?php

declare(strict_types=1);

namespace App\Service\Order\Allocate;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\WarehouseLocation;
use App\Enum\OrderStatus;
use App\Exception\DatabaseException;
use App\Exception\DomainException;
use App\ParametersObject\AllocationResultPo;
use App\Service\Order\Factory\OrderItemReservationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query\QueryException;

readonly class OrderAllocator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LockedProductLocationsProvider $lockedProductLocationsProvider,
        private OrderItemReservationFactory $orderItemReservationFactory,
        private StockAllocator $stockAllocator,
    ) {
    }

    /**
     * @param Order $order
     * @return array<int, int> productId -> missingQuantity
     * @throws DatabaseException
     * @throws DomainException
     */
    public function allocateAndReturnMissingItems(Order $order): array
    {
        $this->validateOrder($order);

        $quantityByProduct = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $quantityByProduct[$orderItem->getProduct()->getId()] = $orderItem->getQuantityRequested();
        }

        try {
            $locationsByWarehouse = $this->lockedProductLocationsProvider->getLockedProductLocations(
                array_keys($quantityByProduct)
            );
        } catch (QueryException $e) {
            throw new DatabaseException(
                message: 'Could not obtain a lock: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $allocationResult = $this->stockAllocator->allocate($locationsByWarehouse, $quantityByProduct);

        try {
            foreach ($order->getOrderItems() as $orderItem) {
                $this->persistReservationsForItem($orderItem, $allocationResult);
            }
        } catch (OptimisticLockException | ORMException $e) {
            throw new DatabaseException(
                message: 'Could not reserve stock: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $missingItems = $allocationResult->getMissingPerProduct();
        if (!empty($missingItems)) {
            $order->setStatus(OrderStatus::PartiallyReserved);
        }

        $this->entityManager->flush();

        return $missingItems;
    }

    /**
     * @param OrderItem $orderItem
     * @param AllocationResultPo $allocationResult
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function persistReservationsForItem(
        OrderItem $orderItem,
        AllocationResultPo $allocationResult,
    ): void {
        $productId = $orderItem->getProduct()->getId();

        foreach ($allocationResult->getLinesForProduct($productId) as $line) {
            /** @var WarehouseLocation $location */
            $location = $this->entityManager->find(WarehouseLocation::class, $line->warehouseLocationId);

            $location->reserve($line->quantityAllocated);

            $reservation = $this->orderItemReservationFactory->createOrderItemReservation(
                $orderItem,
                $location,
                $line->quantityAllocated,
            );
            $orderItem->addReservation($reservation);
            $this->entityManager->persist($reservation);
        }
    }

    /**
     * @param Order $order
     * @return void
     * @throws DomainException
     */
    private function validateOrder(Order $order): void
    {
        if (!in_array($order->getStatus(), [OrderStatus::Pending, OrderStatus::PartiallyReserved], true)) {
            throw new DomainException(
                sprintf(
                    'Trying to allocate order #%d with invalid status %s',
                    $order->getId(),
                    $order->getStatus()->value,
                )
            );
        }
    }
}
