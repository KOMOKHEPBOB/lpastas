<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Order;

use App\DTO\CreateOrderRequest;
use App\DTO\OrderItemRequest;
use App\Exception\ProductDoesNotExistException;
use App\Repository\ProductRepository;
use App\Service\Order\Create\CreateOrderRequestValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        $this->mockExistingProductIds(
            requestedProductIds: $productIds,
            existingProductIds: $productIds
        );

        $this->createOrderRequestValidator->validate($createOrderRequest);
    }

    #[DataProvider('missingProductsProvider')]
    public function testShouldThrowExceptionWithMissingProductIds(
        array $requestedIds,
        array $existingIds,
        string $expectedMessage,
    ): void {
        $this->mockExistingProductIds($requestedIds, $existingIds);

        $this->expectException(ProductDoesNotExistException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->createOrderRequestValidator->validate(
            $this->createOrderRequest($requestedIds)
        );
    }

    public static function missingProductsProvider(): array
    {
        return [
            'Single missing product' => [
                'requestedIds' => [5],
                'existingIds' => [],
                'expectedMessage' => 'Products do not exist: 5',
            ],

            'First product missing' => [
                'requestedIds' => [5, 40],
                'existingIds' => [40],
                'expectedMessage' => 'Products do not exist: 5',
            ],

            'Second product missing' => [
                'requestedIds' => [5, 40],
                'existingIds' => [5],
                'expectedMessage' => 'Products do not exist: 40',
            ],

            'Two products missing' => [
                'requestedIds' => [5, 40],
                'existingIds' => [],
                'expectedMessage' => 'Products do not exist: 5, 40',
            ],
        ];
    }

    private function createOrderRequest(array $productIds): CreateOrderRequest
    {
        $orderItemsRequests = array_map(
            static fn (int $id) => new OrderItemRequest($id, self::QUANTITY),
            $productIds
        );

        return new CreateOrderRequest($orderItemsRequests);
    }

    /**
     * @param int[] $requestedProductIds
     * @param int[] $existingProductIds
     * @return void
     */
    private function mockExistingProductIds(array $requestedProductIds, array $existingProductIds): void
    {
        $this->productRepositoryMock
            ->expects($this->once())
            ->method('findExistingIds')
            ->with($requestedProductIds)
            ->willReturn($existingProductIds);
    }
}
