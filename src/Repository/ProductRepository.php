<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\QueryException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param int[] $requestedProductIds
     * @return int[]
     */
    public function findExistingIds(array $requestedProductIds): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.id IN (:ids)')

            ->setParameter('ids', $requestedProductIds)
        ;

        return $qb->getQuery()->getSingleColumnResult();
    }

    /**
     * @param int[] $ids
     * @return Product[]
     * @throws QueryException
     */
    public function findPerProductId(array $ids): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->indexBy('p', 'p.id')

            ->setParameter('ids', $ids)
        ;

        return $qb->getQuery()->getResult();
    }
}
