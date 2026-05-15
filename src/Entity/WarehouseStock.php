<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseStockRepository::class)]
#[ORM\Table(name: 'warehouse_stocks')]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_product', columns: ['warehouse_id', 'product_id'])]
class WarehouseStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Warehouse::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $quantityReserved;

    public function getId(): int
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

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
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
}
