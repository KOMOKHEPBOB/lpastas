<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WarehouseStockFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $quantity = 0;

        foreach (WarehouseFixtures::DATA as $warehouseData) {
            foreach (ProductFixtures::DATA as $productData) {
                $warehouseStock = new WarehouseStock();
                $warehouseStock->setProduct($this->getReference($productData['ref'], Product::class));
                $warehouseStock->setWarehouse($this->getReference($warehouseData['ref'], Warehouse::class));
                $warehouseStock->setQuantity($quantity++);
                $manager->persist($warehouseStock);
            }
        }

        $manager->flush();
    }
}
