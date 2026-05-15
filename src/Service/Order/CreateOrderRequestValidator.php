<?php

namespace App\Service\Order;

use App\DTO\CreateOrderRequest;
use App\Exception\ProductDoesNotExistException;
use App\Repository\ProductRepository;
use function count;

readonly class CreateOrderRequestValidator
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    /**
     * @param CreateOrderRequest $request
     * @return void
     * @throws ProductDoesNotExistException
     */
    public function validate(CreateOrderRequest $request): void
    {
        $productsIds = $this->getUniqueProductIds($request);
        $foundProductsCount = $this->productRepository->count(['id' => $productsIds]);

        if (count($productsIds) !== $foundProductsCount) {
            throw new ProductDoesNotExistException('One or more products do not exist');
        }
    }

    /**
     * @param CreateOrderRequest $request
     * @return int[]
     */
    private function getUniqueProductIds(CreateOrderRequest $request): array
    {
        $productsIds = [];
        foreach ($request->orderItemRequests as $orderItemRequest) {
            if (in_array($orderItemRequest->productId, $productsIds, true)) {
                continue;
            }

            $productsIds[] = $orderItemRequest->productId;
        }

        return $productsIds;
    }
}
