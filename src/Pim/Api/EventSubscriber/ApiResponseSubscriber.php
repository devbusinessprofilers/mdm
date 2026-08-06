<?php

declare(strict_types=1);

namespace App\Pim\Api\EventSubscriber;

use App\Pim\Api\Exception\ApiProblemException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class ApiResponseSubscriber
{
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
    public function exception(ExceptionEvent $event): void
    {
        if (
            !str_starts_with(
                $event->getRequest()->getPathInfo(),
                '/api/v1/',
            )
        ) {
            return;
        }
        $exception = $event->getThrowable();
        if ($exception instanceof ExtraAttributesException) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'type' => 'invalid_fields',
                        'message' => 'Le corps contient des champs inconnus ou non modifiables.',
                        'fields' => $exception->getExtraAttributes(),
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                ),
            );

            return;
        }
        if ($exception instanceof NotNormalizableValueException) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'type' => 'validation_failed',
                        'message' => 'Le type d’une valeur est invalide.',
                        'violations' => [
                            [
                                'propertyPath' => $exception->getPath() ?? '',
                                'message' => $exception->getMessage(),
                            ],
                        ],
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                ),
            );

            return;
        }
        if ($exception instanceof NotEncodableValueException) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'type' => 'invalid_json',
                        'message' => 'Corps JSON invalide.',
                    ],
                    Response::HTTP_BAD_REQUEST,
                ),
            );

            return;
        }
        if (!($exception instanceof ApiProblemException)) {
            return;
        }
        $event->setResponse(
            new JsonResponse(
                [
                    'type' => $exception->type,
                    'message' => $exception->getMessage(),
                    ...$exception->details,
                ],
                $exception->status,
            ),
        );
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function response(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1/')) {
            return;
        }
        $response = $event->getResponse();
        if (
            $response->isSuccessful()
            && null !== ($cursor = $request->attributes->get('_api_next_cursor'))
        ) {
            $response->headers->set('X-Next-Cursor', (string) $cursor);
        }
        if (
            !$response->isSuccessful()
            || '' === (string) $response->getContent()
        ) {
            return;
        }
        $data = json_decode((string) $response->getContent(), true);
        $version = is_array($data) ? $data['version'] ?? null : null;
        if (is_int($version)) {
            $response->setEtag((string) $version);
        }
    }
}
