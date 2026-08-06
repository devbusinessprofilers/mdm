<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Metrics\MetricsExporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController
{
    /** Répond 404 sans jeton valide pour ne pas révéler l'endpoint. */
    #[Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function __invoke(Request $request, MetricsExporter $exporter): Response
    {
        if (!$exporter->isAuthorized($request)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            $exporter->render(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
