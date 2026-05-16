<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    public const array DATA = [
        ['title' => 'P1', 'ref' => 'p1'],
        ['title' => 'P2', 'ref' => 'p2'],
        ['title' => 'P3', 'ref' => 'p3'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $data) {
            $product = new Product();
            $product->setTitle($data['title']);
            $manager->persist($product);

            $this->addReference($data['ref'], $product);
        }

        $manager->flush();
    }
}
