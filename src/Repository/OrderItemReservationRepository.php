<?php

declare(strict_types=1);

namespace App\Repository;

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
}
