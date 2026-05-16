<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\Table(name: 'warehouses')]
class Warehouse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    /** @var Collection<int, WarehouseLocation> */
    #[ORM\OneToMany(targetEntity: WarehouseLocation::class, mappedBy: 'warehouse', cascade: ['persist', 'remove'])]
    private Collection $stocks;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
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
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function setStocks(Collection $stocks): void
    {
        $this->stocks = $stocks;
    }
}
