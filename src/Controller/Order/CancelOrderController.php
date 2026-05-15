<?php

namespace App\Controller\Order;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders/{order}/cancel', name: 'api.v1.orders.cancel', methods: ['PATCH'])]
class CancelOrderController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'Canceled']);
    }
}
