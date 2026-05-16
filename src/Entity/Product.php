<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    /** @var Collection<int, WarehouseLocation> */
    #[ORM\OneToMany(targetEntity: WarehouseLocation::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    private Collection $warehouseStocks;

    public function __construct()
    {
        $this->warehouseStocks = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /** @return Collection<int, WarehouseLocation> */
    public function getWarehouseStocks(): Collection
    {
        return $this->warehouseStocks;
    }

    public function setWarehouseStocks(Collection $warehouseStocks): void
    {
        $this->warehouseStocks = $warehouseStocks;
    }
}
