<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiException;
use App\Exception\DatabaseException;
use App\Exception\DomainException;
use App\Exception\InternalException;
use App\Exception\ProductDoesNotExistException;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        if ($exception instanceof ApiException) {
            $statusCode = $this->getStatusCode($exception);
            $title = new ReflectionClass($exception)->getShortName();
            $detail = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $title = Response::$statusTexts[$statusCode];
            $detail = $this->getHttpExceptionDetail($statusCode);
        } else {
            return;
        }

        $response = new JsonResponse([
            'type'   => 'https://tools.ietf.org/html/rfc7807',
            'title'  => $title,
            'status' => $statusCode,
            'detail' => $detail,
            'errors' => [$detail],
        ], $statusCode);

        $response->headers->set('Content-Type', 'application/problem+json');

        $event->setResponse($response);
    }

    public function getHttpExceptionDetail(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_NOT_FOUND => 'The requested resource was not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed.',
            Response::HTTP_FORBIDDEN => 'Access denied.',
            Response::HTTP_UNAUTHORIZED => 'Authentication required.',
            default => 'An unexpected error occurred.',
        };
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
