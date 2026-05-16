<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiException;
use App\Exception\DatabaseException;
use App\Exception\DomainException;
use App\Exception\InternalException;
use App\Exception\ProductDoesNotExistException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onException',
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ApiException) {
            return;
        }

        $statusCode = $this->getStatusCode($exception);
        $response = new JsonResponse([
            'type' => 'api/exception',
            'title' => new \ReflectionClass($exception)->getShortName(),
            'status' => $statusCode,
            'detail' => $exception->getMessage(),
            'errors' => [$exception->getMessage()],
        ], $statusCode);

        $response->headers->set('Content-Type', 'application/problem+json');

        $event->setResponse($response);
    }

    private function getStatusCode(ApiException $exception): int
    {
        if (
            $exception instanceof DatabaseException
            || $exception instanceof InternalException
        ) {
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        if (
            $exception instanceof DomainException
            || $exception instanceof ProductDoesNotExistException
        ) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
