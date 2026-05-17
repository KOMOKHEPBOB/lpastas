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
        $order->getStatus()->assertCanTransitionToAny(
            OrderStatus::Reserved,
            OrderStatus::PartiallyReserved,
        );

        $requestedQuantitiesPerProduct = $this->getRequestedQuantitiesPerProduct($order);
        $lockedLocationsByWarehouse = $this->getLockedProductLocations($requestedQuantitiesPerProduct);
        $allocationResult = $this->stockAllocator->allocate($lockedLocationsByWarehouse, $requestedQuantitiesPerProduct);
        $this->persistOrderReservations($order, $allocationResult);

        $missingItems = $allocationResult->getMissingPerProduct();
        $this->updateOrderStatus($missingItems, $order);

        return $missingItems;
    }

    /**
     * @param array $quantityByProduct
     * @return array<int, array<int, WarehouseLocation[]>>
     * @throws DatabaseException
     */
    private function getLockedProductLocations(array $quantityByProduct): array
    {
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

        return $locationsByWarehouse;
    }

    /**
     * @param Order $order
     * @return array<int, int>
     */
    private function getRequestedQuantitiesPerProduct(Order $order): array
    {
        $requestedQuantityByPerProduct = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $requestedQuantityByPerProduct[$orderItem->getProduct()->getId()] = $orderItem->getQuantityRequested();
        }

        return $requestedQuantityByPerProduct;
    }

    /**
     * @param Order $order
     * @param AllocationResultPo $allocationResult
     * @return void
     * @throws DatabaseException
     */
    private function persistOrderReservations(Order $order, AllocationResultPo $allocationResult): void
    {
        try {
            foreach ($order->getOrderItems() as $orderItem) {
                $this->persistReservationsForItem($orderItem, $allocationResult);
            }
        } catch (OptimisticLockException|ORMException $e) {
            throw new DatabaseException(
                message: 'Could not reserve stock: ' . $e->getMessage(),
                previous: $e,
            );
        }
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
     * @param array $missingItems
     * @param Order $order
     * @return void
     * @throws DomainException
     */
    private function updateOrderStatus(array $missingItems, Order $order): void
    {
        if (!empty($missingItems)) {
            $order->setStatus(OrderStatus::PartiallyReserved);

            return;
        }

        $order->setStatus(OrderStatus::Reserved);
    }
}
