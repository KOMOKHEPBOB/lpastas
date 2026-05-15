<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderReservationRepository::class)]
#[ORM\Table(name: 'order_reservations')]
class OrderItemReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private OrderItem $orderItem;

    #[ORM\ManyToOne(targetEntity: WarehouseStock::class)]
    #[ORM\JoinColumn(nullable: false)]
    private WarehouseStock $warehouseStock;

    #[ORM\Column(type: 'integer')]
    private int $quantityReserved;

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrderItem(): OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(OrderItem $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    public function getWarehouseStock(): WarehouseStock
    {
        return $this->warehouseStock;
    }

    public function setWarehouseStock(WarehouseStock $warehouseStock): void
    {
        $this->warehouseStock = $warehouseStock;
    }

    public function getQuantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(int $quantityReserved): void
    {
        $this->quantityReserved = $quantityReserved;
    }
}
