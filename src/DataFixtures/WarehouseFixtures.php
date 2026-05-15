<?php

namespace App\DataFixtures;

use App\Entity\Warehouse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WarehouseFixtures extends Fixture
{
    public const DATA = [
        ['title' => 'Vilnius', 'ref' => 'w1'],
        ['title' => 'Kaunas', 'ref' => 'w2'],
        ['title' => 'Klaipėda', 'ref' => 'w3'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $data) {
            $warehouse = new Warehouse();
            $warehouse->setTitle($data['title']);
            $manager->persist($warehouse);

            $this->addReference($data['ref'], $warehouse);
        }

        $manager->flush();
    }
}
