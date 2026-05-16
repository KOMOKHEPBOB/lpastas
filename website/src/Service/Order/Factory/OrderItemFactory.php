<?php

declare(strict_types=1);

namespace App\Service\Order\Factory;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;

class OrderItemFactory
{
    public function createOrderItem(Order $order, Product $product, int $quantity): OrderItem
    {
        $orderItem = new OrderItem();
        $orderItem->setOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantityRequested($quantity);

        return $orderItem;
    }
}
