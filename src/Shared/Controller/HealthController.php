<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Service\HealthReporter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(HealthReporter $reporter): JsonResponse
    {
        $report = $reporter->report();

        return new JsonResponse($report['payload'], $report['httpStatus']);
    }
}
