<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseLocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseLocationRepository::class)]
#[ORM\Table(name: 'warehouse_locations')]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_location_code', columns: ['warehouse_id', 'location_code'])]
#[ORM\Index(name: 'idx_product_id', columns: ['product_id'])]
class WarehouseLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Warehouse::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\Column(name: 'warehouse_id', type: 'integer', insertable: false, updatable: false)]
    private int $warehouseId;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'warehouseLocations')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(name: 'product_id', type: 'integer', insertable: false, updatable: false)]
    private int $productId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $locationCode;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $quantityReserved = 0;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getLocationCode(): string
    {
        return $this->locationCode;
    }

    public function setLocationCode(string $locationCode): void
    {
        $this->locationCode = $locationCode;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(int $quantityReserved): void
    {
        $this->quantityReserved = $quantityReserved;
    }

    public function getQuantityAvailable(): int
    {
        return max(0, $this->getQuantity() - $this->getQuantityReserved());
    }

    public function reserve(int $amount): void
    {
        $available = $this->getQuantityAvailable();

        if ($amount > $available) {
            throw new \DomainException(sprintf(
                'Cannot reserve %d units at location "%s" in warehouse "%s": only %d available.',
                $amount,
                $this->getLocationCode(),
                $this->getWarehouse()->getTitle(),
                $available,
            ));
        }

        $this->setQuantityReserved($this->getQuantityReserved() + $amount);
    }
}
