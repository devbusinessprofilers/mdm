<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RestaurantOpenApiTest extends KernelTestCase
{
    public function testRestaurantReadPatchMediaAndDocumentOperationsAreDocumented(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(OpenApiFactoryInterface::class);
        self::assertInstanceOf(OpenApiFactoryInterface::class, $factory);
        $paths = $factory([])->getPaths()->getPaths();

        foreach (
            [
                '/api/v1/restaurants',
                '/api/v1/restaurants/{id}',
                '/api/v1/restaurants/{restaurantId}/medias',
                '/api/v1/restaurants/{restaurantId}/medias/ordre',
                '/api/v1/restaurants/{restaurantId}/medias/{resourceId}',
                '/api/v1/restaurants/{restaurantId}/medias/{resourceId}/fichier',
                '/api/v1/restaurants/{restaurantId}/documents',
                '/api/v1/restaurants/{restaurantId}/documents/{documentId}',
                '/api/v1/restaurants/{restaurantId}/documents/{documentId}/fichier',
                '/api/v1/restaurants/{restaurantId}/documents/{documentId}/publication',
                '/api/v1/restaurants/{restaurantId}/documents/{documentId}/download',
            ] as $path
        ) {
            self::assertArrayHasKey($path, $paths);
        }

        $patch = $paths['/api/v1/restaurants/{id}']->getPatch();
        self::assertNotNull($patch);
        self::assertTrue((bool) array_filter(
            $patch->getParameters(),
            static fn ($parameter): bool =>
                'If-Match' === $parameter->getName()
                && true === $parameter->getRequired(),
        ));
        self::assertNull($paths['/api/v1/restaurants']->getPost());
    }
}
