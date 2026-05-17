<?php

declare(strict_types=1);

namespace App\Enum;

use App\Exception\DomainException;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case PartiallyReserved = 'partially_reserved';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    /**
     * @param OrderStatus ...$targets
     * @return void
     * @throws DomainException
     */
    public function assertCanTransitionToAny(self ...$targets): void
    {
        if (array_any($targets, fn($target) => $this->canTransitionTo($target))) {
            return;
        }

        throw new DomainException(sprintf(
            'Cannot transition from "%s" to any of [%s]. Allowed transitions: [%s].',
            $this->value,
            implode(', ', array_map(static fn(self $s) => $s->value, $targets)),
            implode(', ', array_map(static fn(self $s) => $s->value, $this->getAllowedTransitions())),
        ));
    }

    /**
     * @param int $orderId
     * @param OrderStatus $next
     * @return void
     * @throws DomainException
     */
    public function assertOrderCanTransition(int $orderId, self $next): void
    {
        if ($this->canTransitionTo($next)) {
            return;
        }

        throw new DomainException(sprintf(
            'Cannot transition order #%s from "%s" to %s. Allowed transitions: [%s].',
            $orderId,
            $this->value,
            $next->value,
            implode(', ', array_map(static fn(self $s) => $s->value, $this->getAllowedTransitions())),
        ));
    }

    private function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->getAllowedTransitions(), true);
    }

    /** @return OrderStatus[] */
    private function getAllowedTransitions(): array
    {
        return match($this) {
            self::Pending            => [self::Reserved, self::PartiallyReserved],
            self::Reserved           => [self::Shipped, self::Cancelled, self::Pending],
            self::PartiallyReserved  => [self::Reserved, self::Cancelled, self::Pending],
            self::Shipped, self::Cancelled => [],
        };
    }
}
