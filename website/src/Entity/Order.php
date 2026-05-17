<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Exception\DomainException;
use App\Repository\OrderRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'])]
    private Collection $orderItems;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    /**
     * @param OrderStatus $newStatus
     * @return void
     * @throws DomainException
     */
    public function setStatus(OrderStatus $newStatus): void
    {
        if (isset($this->status)) {
            $this->status->assertCanTransitionToAny($newStatus);
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, OrderItem> */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addItem(OrderItem $item): void
    {
        $this->orderItems->add($item);
    }
}
