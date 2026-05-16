<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $quantityRequested;

    /** @var Collection<int, OrderItemReservation> */
    #[ORM\OneToMany(
        targetEntity: OrderItemReservation::class,
        mappedBy: 'orderItem',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getQuantityRequested(): int
    {
        return $this->quantityRequested;
    }

    public function setQuantityRequested(int $quantityRequested): void
    {
        $this->quantityRequested = $quantityRequested;
    }

    /** @return Collection<int, OrderItemReservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(OrderItemReservation $reservation): void
    {
        $this->reservations->add($reservation);
    }

    public function clearReservations(): void
    {
        $this->reservations->clear();
    }
}
