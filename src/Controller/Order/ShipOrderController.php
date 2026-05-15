<?php

namespace App\Controller\Order;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders/{order}/ship', name: 'api.v1.orders.ship', methods: ['PATCH'])]
class ShipOrderController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'Shipped']);
    }
}
