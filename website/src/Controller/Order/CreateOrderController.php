<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\DTO\CreateOrderRequest;
use App\Exception\ApiException;
use App\Service\Order\Create\CreateOrderRequestHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/orders', name: 'api.v1.orders.create', methods: ['POST'])]
class CreateOrderController extends AbstractController
{
    public function __construct(
        private readonly CreateOrderRequestHandler $createOrderRequestHandler,
    ) {
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     * @return JsonResponse
     * @throws ApiException
     */
    public function __invoke(
        #[MapRequestPayload] CreateOrderRequest $createOrderRequest,
    ): JsonResponse {
        $result = $this->createOrderRequestHandler->handleCreateOrderRequest($createOrderRequest);

        return new JsonResponse(
            [
                'success' => true,
                'order' => $result['order']->getId(),
                'missing_items' => $result['missingItems'],
            ],
            Response::HTTP_CREATED
        );
    }
}
