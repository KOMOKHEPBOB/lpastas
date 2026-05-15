<?php

namespace App\Tests\Unit\Service\Order;

use App\DTO\CreateOrderRequest;
use App\DTO\OrderItemRequest;
use App\Repository\ProductRepository;
use App\Service\Order\CreateOrderRequestValidator;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function count;

#[Group('OrderApi')]
class CreateOrderRequestValidatorTest extends TestCase
{
    private const int QUANTITY = 5;

    private CreateOrderRequestValidator $createOrderRequestValidator;
    private ProductRepository|MockObject $productRepositoryMock;

    protected function setUp(): void
    {
        $this->productRepositoryMock = $this->createMock(ProductRepository::class);

        $this->createOrderRequestValidator = new CreateOrderRequestValidator(
            $this->productRepositoryMock,
        );
    }

    public function testShouldNotThrowExceptionIfAllProductsExist(): void
    {
        $productIds = [5, 40];
        $createOrderRequest = $this->createOrderRequest($productIds);

        $this->productRepositoryMock
            ->expects($this->once())
            ->method('count')
            ->with(['id' => $productIds])
            ->willReturn(count($productIds));

        $this->createOrderRequestValidator->validate($createOrderRequest);
    }

    private function createOrderRequest(array $productIds): CreateOrderRequest
    {
        $orderItemRequests = [];
        foreach ($productIds as $productId) {
            $orderItemRequests[] = new OrderItemRequest(
                $productId,
                self::QUANTITY,
            );
        }

        return new CreateOrderRequest($orderItemRequests);
    }
}
