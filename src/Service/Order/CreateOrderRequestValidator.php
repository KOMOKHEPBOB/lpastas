<?php

namespace App\Service\Order;

use App\DTO\CreateOrderRequest;
use App\Exception\ProductDoesNotExistException;
use App\Repository\ProductRepository;

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
        $missingProductIds = $this->getMissingProductIds($request);
        if (empty($missingProductIds)) {
            return;
        }

        throw new ProductDoesNotExistException(sprintf(
            'Products do not exist: %s',
            implode(', ', $missingProductIds)
        ));
    }

    /**
     * @param CreateOrderRequest $request
     * @return int[]
     */
    private function getMissingProductIds(CreateOrderRequest $request): array
    {
        $requestedProductIds = $this->getUniqueProductIds($request);
        $existingProductIds = $this->productRepository->findExistingIds($requestedProductIds);

        return array_values(
            array_diff($requestedProductIds, $existingProductIds)
        );
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
