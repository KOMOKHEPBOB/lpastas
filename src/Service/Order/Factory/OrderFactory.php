<?php

namespace App\Service\Order\Factory;

use App\Entity\Order;
use App\Enum\OrderStatus;

class OrderFactory
{
    public function createOrder(): Order
    {
        $order = new Order();
        $order->setStatus(OrderStatus::Pending);

        return $order;
    }
}
