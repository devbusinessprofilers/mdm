<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActiviteOpenApiTest extends KernelTestCase
{
    public function testActivityReadAndPatchOperationsAreDocumented(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(OpenApiFactoryInterface::class);
        self::assertInstanceOf(OpenApiFactoryInterface::class, $factory);
        $paths = $factory([])->getPaths()->getPaths();
        foreach (
            [
                '/api/v1/activites',
                '/api/v1/activites/{id}',
                '/api/v1/activites/{activiteId}/medias',
                '/api/v1/activites/{activiteId}/medias/ordre',
                '/api/v1/activites/{activiteId}/medias/{resourceId}',
                '/api/v1/activites/{activiteId}/medias/{resourceId}/fichier',
                '/api/v1/activites/{activiteId}/documents',
                '/api/v1/activites/{activiteId}/documents/{documentId}',
                '/api/v1/activites/{activiteId}/documents/{documentId}/fichier',
                '/api/v1/activites/{activiteId}/documents/{documentId}/publication',
                '/api/v1/activites/{activiteId}/documents/{documentId}/download',
            ] as $path
        ) {
            self::assertArrayHasKey($path, $paths);
        }
        $patch = $paths['/api/v1/activites/{id}']->getPatch();
        self::assertNotNull($patch);
        $parameters = $patch->getParameters();
        self::assertTrue(
            (bool) array_filter(
                $parameters,
                static fn ($p): bool => 'If-Match' === $p->getName()
                    && true === $p->getRequired(),
            ),
        );
        self::assertNull($paths['/api/v1/activites']->getPost());
    }
}
