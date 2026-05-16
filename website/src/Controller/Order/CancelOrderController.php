<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Entity\Order;
use App\Exception\DomainException;
use App\Service\Order\Cancel\OrderCanceler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders/{order}/cancelation', name: 'api.v1.orders.cancel', methods: ['PATCH'])]
class CancelOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderCanceler $orderCanceler,
    ) {
    }

    /**
     * @param Order $order
     * @return JsonResponse
     * @throws DomainException
     */
    public function __invoke(Order $order): JsonResponse
    {
        $this->orderCanceler->cancelOrder($order);

        return new JsonResponse(['success' => true, 'order_id' => $order->getId(), 'message' => 'Canceled']);
    }
}
