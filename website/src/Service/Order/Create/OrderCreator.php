<?php

declare(strict_types=1);

namespace App\Service\Order\Create;

use App\DTO\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\Product;
use App\Exception\ApiException;
use App\Exception\DatabaseException;
use App\Repository\ProductRepository;
use App\Service\Order\Factory\OrderFactory;
use App\Service\Order\Factory\OrderItemFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;

readonly class OrderCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderFactory $orderFactory,
        private OrderItemFactory $orderItemFactory,
        private ProductRepository $productRepository,
    ) {
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     * @return Order
     * @throws ApiException
     */
    public function createAndPersistOrder(CreateOrderRequest $createOrderRequest): Order
    {
        $productsPerId = $this->getProductsPerId($createOrderRequest);

        $order = $this->orderFactory->createOrder();
        $this->entityManager->persist($order);

        foreach ($createOrderRequest->orderItemRequests as $orderItemRequest) {
            $orderItem = $this->orderItemFactory->createOrderItem(
                $order,
                $productsPerId[$orderItemRequest->productId],
                $orderItemRequest->quantity,
            );
            $order->addItem($orderItem);
            $this->entityManager->persist($order);
        }

        return $order;
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     * @return Product[]
     * @throws DatabaseException
     */
    private function getProductsPerId(CreateOrderRequest $createOrderRequest): array
    {
        $productIds = array_map(
            static fn($request) => $request->productId,
            $createOrderRequest->orderItemRequests
        );

        try {
            return $this->productRepository->findPerProductId($productIds);
        } catch (QueryException $e) {
            throw new DatabaseException(
                message: 'Could not fetch products from database.',
                previous: $e,
            );
        }
    }
}
