<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseLocation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WarehouseLocationFixtures extends Fixture
{
    private const int LOCATIONS_PER_PRODUCT = 10;

    public function load(ObjectManager $manager): void
    {
        $warehouseLocationCodes = [];
        foreach (WarehouseFixtures::DATA as $warehouseData) {
            $warehouseLocationCodes[$warehouseData['ref']] = 0;
        }

        foreach (WarehouseFixtures::DATA as $warehouseData) {
            foreach (ProductFixtures::DATA as $productData) {
                $quantity = 0;

                for ($i = 0; $i < self::LOCATIONS_PER_PRODUCT; $i++) {
                    $warehouseLocation = new WarehouseLocation();
                    $warehouseLocation->setProduct($this->getReference($productData['ref'], Product::class));
                    $warehouseLocation->setWarehouse($this->getReference($warehouseData['ref'], Warehouse::class));
                    $warehouseLocation->setLocationCode('L' . $warehouseLocationCodes[$warehouseData['ref']]++);
                    $warehouseLocation->setQuantity($quantity++);
                    $manager->persist($warehouseLocation);
                }
            }
        }

        $manager->flush();
    }
}
