<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\DTO\CreateOrderRequest;
use App\Service\Order\OrderCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders', name: 'api.v1.orders.create', methods: ['POST'])]
class CreateOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderCreator $orderCreator,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload] CreateOrderRequest $createOrderRequest,
    ): JsonResponse {
        $order = $this->orderCreator->createOrder($createOrderRequest);

        return new JsonResponse(['success' => true, 'message' => 'Created']);
    }
}
