<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WarehouseLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query\QueryException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseLocation>
 */
class WarehouseLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseLocation::class);
    }

    /**
     * Rows are locked in particular order so that all concurrent transactions touching the same products
     * acquire locks in a consistent sequence, preventing deadlocks. E.g.
     * [Lock] 5 -> [Wait] 8
     * [Lock] 8 -> [Wait] 5
     *
     * @param int[] $warehouseLocationIds
     * @return WarehouseLocation[]
     * @throws QueryException
     */
    public function findAndLock(array $warehouseLocationIds): array
    {
        return $this->createQueryBuilder('wl')
            ->where('wl.id IN (:ids)')
            ->indexBy('wl', 'wl.id')
            ->orderBy('wl.id', 'ASC')  // guarantees consistent lock acquisition order

            ->setParameter('ids', $warehouseLocationIds)

            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * @param int[] $productIds
     * @return int[]
     */
    public function findProductIdsInStock(array $productIds): array
    {
        $qb = $this->createQueryBuilder('wl');

        $qb
            ->select('wl.id')
            ->addSelect('(wl.quantity - wl.quantityReserved) AS available')

            ->where('wl.product IN (:productIds)')
            ->andWhere('wl.quantity > wl.quantityReserved')
            ->orderBy('wl.warehouse', 'ASC')
            ->addOrderBy('wl.product', 'ASC')
            ->addOrderBy('available', 'DESC')
            ->setParameter('productIds', $productIds, ArrayParameterType::INTEGER);

        return $qb->getQuery()->getResult();
    }
}
