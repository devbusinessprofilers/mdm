<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceOpenApiTest extends KernelTestCase
{
    public function testServiceReadPatchMediaAndDocumentOperationsAreDocumented(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(OpenApiFactoryInterface::class);
        self::assertInstanceOf(OpenApiFactoryInterface::class, $factory);
        $openApi = $factory([]);
        $paths = $openApi->getPaths()->getPaths();
        $schemas = $openApi->getComponents()->getSchemas();
        self::assertInstanceOf(\ArrayObject::class, $schemas);
        $patchSchema = $schemas['ServiceEvenementiel.ServiceEvenementielPatchInput.jsonMergePatch'] ?? null;
        self::assertInstanceOf(\ArrayObject::class, $patchSchema);
        self::assertArrayNotHasKey('code', (array) ($patchSchema['properties'] ?? []));

        foreach (
            [
                '/api/v1/services',
                '/api/v1/services/{id}',
                '/api/v1/services/{serviceId}/medias',
                '/api/v1/services/{serviceId}/medias/ordre',
                '/api/v1/services/{serviceId}/medias/{resourceId}',
                '/api/v1/services/{serviceId}/medias/{resourceId}/fichier',
                '/api/v1/services/{serviceId}/documents',
                '/api/v1/services/{serviceId}/documents/{documentId}',
                '/api/v1/services/{serviceId}/documents/{documentId}/fichier',
                '/api/v1/services/{serviceId}/documents/{documentId}/publication',
                '/api/v1/services/{serviceId}/documents/{documentId}/download',
            ] as $path
        ) {
            self::assertArrayHasKey($path, $paths);
        }

        $patch = $paths['/api/v1/services/{id}']->getPatch();
        self::assertNotNull($patch);
        self::assertTrue(
            (bool) array_filter(
                $patch->getParameters(),
                static fn ($parameter): bool => 'If-Match' ===
                    $parameter->getName() && true === $parameter->getRequired(),
            ),
        );
        self::assertNull($paths['/api/v1/services']->getPost());
    }
}
