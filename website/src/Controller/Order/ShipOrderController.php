<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Entity\Order;
use App\Exception\DomainException;
use App\Service\Order\Ship\OrderShipper;
use Doctrine\ORM\Query\QueryException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders/{order}/ship', name: 'api.v1.orders.ship', methods: ['PATCH'])]
class ShipOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderShipper $orderShipper,
    ) {
    }

    /**
     * @param Order $order
     * @return JsonResponse
     * @throws DomainException
     * @throws QueryException
     */
    public function __invoke(Order $order): JsonResponse
    {
        $this->orderShipper->shipOrder($order);

        return new JsonResponse(['success' => true, 'order' => $order->getId(), 'message' => 'Shipped']);
    }
}
