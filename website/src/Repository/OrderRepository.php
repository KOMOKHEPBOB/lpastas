<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findAndLock(int $orderId): Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.id = :orderId')
            ->setMaxResults(1)

            ->setParameter('orderId', $orderId)

            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getSingleResult();
    }

    /**
     * @param  int[] $releasedProductIds
     * @return Order[]
     */
    public function findOrdersToRecalculate(Order $orderToExclude, array $releasedProductIds): array
    {
        $qb = $this->createQueryBuilder('o');

        // Select IDs of Reserved orders with reservations in more than one warehouse.
        $multiWarehouseSubQuery = $this->createQueryBuilder('o2')
            ->select('o2.id')
            ->join('o2.orderItems', 'oi2')
            ->join('oi2.reservations', 'r2')
            ->join('r2.warehouseLocation', 'wl2')
            ->andWhere('o2.status = :reservedStatus')
            ->andWhere('o2.id != :orderToExcludeId')
            ->groupBy('o2.id')
            ->having('COUNT(DISTINCT wl2.warehouse) > 1')
            ->getDQL();

        $qb
            ->distinct()
            ->join('o.orderItems', 'oi')
            ->andWhere('o.id != :orderToExcludeId')
            ->andWhere('oi.product IN (:releasedProductIds)')
            ->andWhere('o.status = :partiallyReservedStatus OR (o.status = :reservedStatus AND o.id IN (' . $multiWarehouseSubQuery . '))')

            ->setParameter('orderToExcludeId', $orderToExclude->getId())
            ->setParameter('releasedProductIds', $releasedProductIds)
            ->setParameter('partiallyReservedStatus', OrderStatus::PartiallyReserved)
            ->setParameter('reservedStatus', OrderStatus::Reserved);

        return $qb->getQuery()->getResult();
    }
}
