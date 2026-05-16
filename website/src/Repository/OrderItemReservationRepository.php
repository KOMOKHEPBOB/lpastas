<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderItemReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItemReservation>
 */
class OrderItemReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItemReservation::class);
    }

    /**
     * @param Order $order
     * @return OrderItemReservation[]
     */
    public function findOrderReservations(Order $order): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->select('r, wl')
            ->innerJoin('r.warehouseLocation', 'wl')
            ->innerJoin('r.orderItem', 'oi')
            ->andWhere('oi.order = :order')

            ->setParameter('order', $order);

        return $qb->getQuery()->getResult();
    }
}
