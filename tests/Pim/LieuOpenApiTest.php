<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LieuOpenApiTest extends KernelTestCase
{
    public function testLieuV1ContractAndBearerAuthenticationAreDocumented(): void
    {
        self::bootKernel();
        $openApi = self::getContainer()->get(OpenApiFactoryInterface::class)([]);
        $paths = $openApi->getPaths()->getPaths();

        foreach (['/api/v1/lieux', '/api/v1/lieux/{id}', '/api/v1/lieux/{lieuId}/medias', '/api/v1/lieux/{lieuId}/medias/ordre', '/api/v1/lieux/{lieuId}/medias/{resourceId}', '/api/v1/lieux/{lieuId}/medias/{resourceId}/fichier'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
        foreach (['/api/v1/lieux/{lieuId}/documents', '/api/v1/lieux/{lieuId}/documents/{documentId}', '/api/v1/lieux/{lieuId}/documents/{documentId}/fichier', '/api/v1/lieux/{lieuId}/documents/{documentId}/publication', '/api/v1/lieux/{lieuId}/documents/{documentId}/download'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
        self::assertNull($paths['/api/v1/lieux']->getPost());
        self::assertArrayNotHasKey('/api/v1/lieux/{id}/soumission', $paths);
        $schemes = $openApi->getComponents()->getSecuritySchemes();
        self::assertInstanceOf(\ArrayObject::class, $schemes);
        self::assertArrayHasKey('bearerAuth', $schemes);
        $bearer = $schemes['bearerAuth'];
        self::assertInstanceOf(SecurityScheme::class, $bearer);
        self::assertSame('bearer', $bearer->getScheme());
    }
}
