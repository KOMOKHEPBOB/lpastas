# Warehouse Stock Reservation API

A REST API built with **Symfony 8 / PHP 8.4 / MySQL / Doctrine ORM** that simulates reserving stock across multiple warehouses, each containing multiple physical locations.

---

## Setup

### Requirements

- Docker

### Installation


#### Update hosts file

```
127.0.0.1 local.order-api.com
```


# Project setup

#### Clone the repository and build docker containers
```shell
git clone https://github.com/KOMOKHEPBOB/lpastas.git \
    && cd lpastas/docker \
    && docker compose up -d --build \
    && docker container exec -it o_php /bin/bash
```

#### Switch to www-data user
```
su -s /bin/bash www-data
```

#### Prepare database
```
bin/console doctrine:schema:drop --force \
    && bin/console doctrine:schema:update --force \
    && bin/console doctrine:fixtures:load -n \
    && bin/console messenger:setup-transports
```

#### optional - install phpMyAdmin
```shell
docker run --name o_phpmyadmin -d -p 8080:80 --link o_db:db --network lpastas_jan_naruskevic_o_network -e PMA_HOST=db -e PMA_PORT=3306 -e PMA_USER=root -e PMA_PASSWORD=root phpmyadmin/phpmyadmin
```

# Run tests

#### Prepare test database
```
bin/console doctrine:database:create --env=test \
    && bin/console doctrine:schema:drop --env=test --force \
    && bin/console doctrine:schema:update --env=test --force
```

```
bin/phpunit --testdox --testsuite=All --group=OrderApi
```

---

## Table of Contents

- [Domain Overview](#domain-overview)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [API Endpoints](#api-endpoints)
- [Allocation Algorithm](#allocation-algorithm)
- [Concurrency Strategy](#concurrency-strategy)
- [Async Processing](#async-processing)
- [Setup](#setup)
- [Running Tests](#running-tests)
- [Known Issues & Design Decisions](#known-issues--design-decisions)

---

## Domain Overview

The system manages product stock across a network of warehouses. Each warehouse contains multiple **physical locations** (bins, shelves, aisles). A single product can occupy multiple locations within the same warehouse.

When an order is placed, the system attempts to reserve the requested quantities using the **fewest possible warehouses**. If stock is insufficient, the order is marked as partially reserved. Cancellations release reserved stock and trigger asynchronous reallocation for other affected orders.

### Order Lifecycle

```
POST /orders
     │
     ▼
  reserved ──────────────────────────── PATCH /orders/{id}/shipment    ──► shipped
     │
     │ (insufficient stock)
     ▼
partially_reserved ──────────────────── PATCH /orders/{id}/cancelation ──► cancelled
     │                                                                         │
     │ (stock freed by another cancellation,                                   │
     │  reallocation via async message)                                        │
     ▼                                                               async recalculation
  reserved                                                           of affected orders
```

---

## Architecture

### Layered structure

```
Controller        → thin HTTP layer, delegates immediately to services
Service           → all business logic, organised by operation
Repository        → all database queries, including locking strategy
Entity            → domain objects with invariants enforced via exceptions
DTO               → request/response shapes crossing the API boundary
ParametersObject  → internal method parameter grouping (not DTOs)
Message           → async message definitions
MessageHandler    → async message consumers
```

### Key design decisions

**Anemic vs rich entities** — `WarehouseLocation` is intentionally rich: `reserve()`, `releaseQuantityReserved()`, and `consumeQuantityReserved()` enforce invariants directly on the entity, keeping mutation logic co-located with the data it protects. Other entities are anemic by design.

**ParameterObject vs DTO** — DTOs (`CreateOrderRequest`, `OrderItemRequest`) carry data across the HTTP boundary and are shaped by API contracts. POs (`AllocationLinePo` and `AllocationResultPo`) group related arguments passed between internal service methods (`StockAllocator` → `OrderAllocator`). 

**Denormalised `quantity_reserved`** — `WarehouseLocation` stores a `quantity_reserved` counter alongside `quantity`. This avoids aggregating `order_item_reservations` at query time. Availability is a single expression: `quantity - quantity_reserved`. The invariant `0 ≤ quantity_reserved ≤ quantity` is enforced by both application-level guards and MySQL `CHECK` constraints.

**Denormalised `warehouseId` and `productId` columns** on `WarehouseLocation` — scalar copies stored alongside the FK relationships. This avoids join traversal when building the `warehouseId → productId → locations[]` structure the allocator needs inside `LockedProductLocationsProvider`.

---

## Project Structure

```
src/
├── Controller/Order/
│   ├── CreateOrderController.php       # POST /api/v1/orders
│   ├── ShipOrderController.php         # PATCH /api/v1/orders/{order}/shipment
│   └── CancelOrderController.php       # PATCH /api/v1/orders/{order}/cancelation
│
├── DTO/
│   ├── CreateOrderRequest.php          # Mapped from request payload via #[MapRequestPayload]
│   └── OrderItemRequest.php
│
├── Entity/
│   ├── Order.php
│   ├── OrderItem.php
│   ├── OrderItemReservation.php        # Links OrderItem to WarehouseLocation + quantityReserved
│   ├── Product.php
│   ├── Warehouse.php
│   └── WarehouseLocation.php           # Rich entity: reserve / releaseQuantityReserved / consumeQuantityReserved
│
├── Enum/
│   ├── AllocationStrategy.php          # fewest_warehouses | empty_locations_first
│   └── OrderStatus.php                 # pending | reserved | partially_reserved | shipped | cancelled
│
├── Exception/
│   ├── ApiException.php                  # Base — caught by global exception handler
│   ├── DomainException.php               # Business rule violations (→ 422)
│   ├── DatabaseException.php             # Infrastructure failures (→ 500)
│   ├── InternalException.php             # Unexpected internal errors (→ 500)
│   └── ProductDoesNotExistException.php  # Missing entity (→ 422)
│
├── Message/Cancel/
│   └── RecalculateOrderAllocationMessage.php   # Carries orderId for async reallocation
│
├── MessageHandler/Cancel/
│   └── RecalculateOrderAllocationMessageHandler.php  # Unallocates then reallocates
│
├── ParametersObject/
│   ├── AllocationLinePo.php            # productId + locationId + quantity — one allocation decision
│   └── AllocationResultPo.php          # Full order allocation plan, queryable by productId
│
├── Repository/
│   ├── OrderRepository.php             # findAndLock, findOrdersToRecalculate
│   ├── OrderItemReservationRepository.php
│   ├── ProductRepository.php
│   ├── WarehouseLocationRepository.php # findProductIdsInStock, findAndLock (FOR UPDATE)
│   └── WarehouseRepository.php
│
└── Service/Order/
    ├── Allocate/
    │   ├── LockedProductLocationsProvider.php  # Two-step lock: find IDs → FOR UPDATE
    │   ├── OrderAllocator.php                  # Orchestrates allocation for an order
    │   ├── OrderUnAllocator.php                # Releases reservations (cancel / recalculate)
    │   └── StockAllocator.php                  # Pure allocation algorithm, no infrastructure
    ├── Cancel/
    │   └── OrderCanceler.php                   # Validates, releases stock, queues recalculation
    ├── Create/
    │   ├── CreateOrderRequestHandler.php       # Entry point: validate → transaction → allocate
    │   ├── CreateOrderRequestValidator.php     # Checks all requested products exist upfront
    │   └── OrderCreator.php                    # Persists Order + OrderItems
    ├── Factory/
    │   ├── OrderFactory.php
    │   ├── OrderItemFactory.php
    │   └── OrderItemReservationFactory.php
    └── Ship/
        └── OrderShipper.php                     # Validates, consumes reserved stock
```

---

## API Endpoints

All responses follow the envelope `{ "success": true, ... }`.

### `POST /api/v1/orders`

Creates an order and reserves stock.

**Request:**
```json
{
  "orderItemRequests": [
    { "productId": 1, "quantity": 50 },
    { "productId": 2, "quantity": 20 }
  ]
}
```

**Response `200 OK`** (fully reserved):
```json
{
  "success": true,
  "order": 42,
  "missing_items": {}
}
```

**Response `200 OK`** (partially reserved):
```json
{
  "success": true,
  "order": 43,
  "missing_items": { "1": 10 }
}
```

---

### `PATCH /api/v1/orders/{order}/ship`

Ships a fully-reserved order. Decrements both `quantity` and `quantity_reserved` on each warehouse location.

```json
{ "success": true, "order": 42, "message": "Shipped" }
```

---

### `PATCH /api/v1/orders/{order}/cancel`

Cancels a reserved or partially-reserved order. Releases `quantity_reserved` on affected locations and asynchronously triggers reallocation for orders containing the same products.

```json
{ "success": true, "order": 42, "message": "Canceled" }
```

---

## Allocation Algorithm

`StockAllocator` implements a **whole-order iterative greedy algorithm** that optimises warehouse selection across all products simultaneously — unlike a naive per-item greedy approach which can fail to find the minimum warehouse count.

### Why whole-order matters

A per-item greedy approach allocates each product independently. After committing warehouse A for product 1, it has no visibility that warehouse B stocks both products and could have covered the whole order alone. The whole-order approach re-scores all candidates against the **remaining unfulfilled quantities** on every iteration.

**Example failure case for per-item greedy:**
```
Order: 60x Laptop, 60x Mouse

W1: Laptop=50, Mouse=0    W2: Laptop=0, Mouse=50    W3: Laptop=10, Mouse=10
W4: Laptop=50, Mouse=50

Per-item greedy:  Laptop → W4(50) + W1(10)   Mouse → W4(50) + W2(10)  = 3 warehouses ✗
Whole-order:      W4(50+50) → remaining: Laptop=10, Mouse=10
                  W3 fully covers both remaining → 2 warehouses ✓
```

### Scoring (per candidate warehouse, per iteration)

| Priority | Key | Description |
|---|---|---|
| 1st | `fullyCovers` | Number of remaining products this warehouse can fully satisfy (DESC) |
| 2nd | `contribution` | `Σ min(available, remaining)` across all remaining products (DESC) |
| 3rd | `tiebreaker` | Configurable — see Allocation Strategy below |

### Allocation Strategy

Set via `ALLOCATION_STRATEGY` in `.env`:

| Value | Tiebreaker | Best for |
|---|---|---|
| `fewest_warehouses` | More total stock wins | High order diversity, preserving optionality |
| `empty_locations_first` | Less total stock wins | Draining sparse locations, consolidating stock |

### Within a warehouse

Locations are consumed **largest-available-first**, minimising the number of location rows used per warehouse.

### Termination guarantee

The loop terminates when either all products are satisfied or no remaining warehouse can contribute anything to any remaining product. Availability is tracked in an internal PHP index — never read from entity state during the loop — ensuring that partially-consumed warehouses are scored correctly across iterations without requiring entity mutations during planning.

---

## Concurrency Strategy

### Pessimistic locking (SELECT FOR UPDATE)

All allocation, shipping, and cancellation run inside explicit database transactions with row-level locks acquired before any reads used for decision-making.

**Lock acquisition order** — always `warehouse_id ASC, product_id ASC` — ensures concurrent transactions touching overlapping products lock rows in the same sequence, preventing circular waits (deadlocks).

**Two-step locking in `LockedProductLocationsProvider`:**

1. `findProductIdsInStock` — identifies eligible location IDs sorted by the deterministic lock order (no lock; MySQL rejects `GROUP BY`/`HAVING` inside `FOR UPDATE`)
2. `findAndLock` — re-fetches the same IDs with `SELECT ... FOR UPDATE` using `ORDER BY id ASC`

**Order-level locking** — `OrderRepository::findAndLock` acquires a lock on the order row before ship or cancel modifies it, preventing two concurrent requests (e.g. simultaneous ship and cancel) from both passing status validation.

### Database-level safety net

MySQL `CHECK` constraints enforce:
```sql
quantity          >= 0
quantity_reserved >= 0
quantity_reserved <= quantity
```

Application guards in `WarehouseLocation` catch violations first with descriptive exceptions. The DB constraints are the hard backstop.

**Shipment ordering** — `consumeQuantityReserved` decrements `quantity_reserved` before `quantity`. MySQL evaluates `CHECK` constraints per-statement, so decrementing `quantity` first would momentarily violate `quantity_reserved <= quantity`.

---

## Async Processing

Cancellation triggers reallocation of affected orders via **Symfony Messenger**.

### Why async?

Running recalculation synchronously inside the cancel transaction would hold locks proportional to the number of affected orders, increasing contention and making cancel response time unbounded. Dispatching messages decouples the HTTP response from the recalculation work.

### Message flow

```
PATCH /orders/{id}/cancel
        │
        ▼
  OrderCanceler
        ├── releases quantity_reserved on affected locations
        ├── sets order status = cancelled
        ├── queries findOrdersToRecalculate(releasedProductIds)
        └── dispatches RecalculateOrderAllocationMessage per affected order
                                │
                                ▼  (consumed asynchronously by worker)
              RecalculateOrderAllocationMessageHandler
                                ├── locks the order row
                                ├── OrderUnAllocator — releases old reservations
                                └── OrderAllocator   — re-runs full allocation
```

### Which orders are recalculated?

`findOrdersToRecalculate(releasedProductIds)` returns orders that are:

- **Partially reserved** and contain at least one released product — freed stock may now fully satisfy them
- **Fully reserved, spanning more than one warehouse**, and contain at least one released product — freed stock may allow consolidation into fewer warehouses

Orders containing none of the released products are excluded — the cancellation cannot affect their allocation.


