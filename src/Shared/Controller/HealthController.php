<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'application' => 'business-profilers-pim-dam',
            'status' => 'ok',
        ]);
    }
}
