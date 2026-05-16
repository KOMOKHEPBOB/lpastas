<?php

namespace App\Service\Order\Factory;

use App\Entity\OrderItem;
use App\Entity\OrderItemReservation;
use App\Entity\WarehouseLocation;

class OrderItemReservationFactory
{
    public function createOrderItemReservation(
        OrderItem $orderItem,
        WarehouseLocation $warehouseLocation,
        int $quantity,
    ): OrderItemReservation
    {
        $reservation = new OrderItemReservation();
        $reservation->setOrderItem($orderItem);
        $reservation->setWarehouseLocation($warehouseLocation);
        $reservation->setQuantityReserved($quantity);

        return $reservation;
    }
}
