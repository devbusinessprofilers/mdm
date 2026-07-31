<?php

declare(strict_types=1);

namespace App\Pim\Api;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $inner)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->inner)($context);
        $schemes =
            $openApi->getComponents()->getSecuritySchemes() ??
            new \ArrayObject();
        $schemes['bearerAuth'] = new SecurityScheme(
            type: 'http',
            description: 'JWT de service RS256 émis par le site externe.',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        return $openApi->withComponents(
            $openApi->getComponents()->withSecuritySchemes($schemes),
        );
    }
}
