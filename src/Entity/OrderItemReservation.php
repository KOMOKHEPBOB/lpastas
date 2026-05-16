<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemReservationRepository::class)]
#[ORM\Table(name: 'order_item_reservations')]
class OrderItemReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: OrderItem::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private OrderItem $orderItem;

    #[ORM\ManyToOne(targetEntity: WarehouseLocation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private WarehouseLocation $warehouseLocation;

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

    public function getWarehouseLocation(): WarehouseLocation
    {
        return $this->warehouseLocation;
    }

    public function setWarehouseLocation(WarehouseLocation $warehouseLocation): void
    {
        $this->warehouseLocation = $warehouseLocation;
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
