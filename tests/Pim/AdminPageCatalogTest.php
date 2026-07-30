<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\AdminPageCatalog;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminPageCatalogTest extends KernelTestCase
{
    public function testCatalogContainsPimAndApiPlatformPages(): void
    {
        self::bootKernel();
        $catalog = self::getContainer()->get(AdminPageCatalog::class);
        self::assertInstanceOf(AdminPageCatalog::class, $catalog);

        $groups = $catalog->groups();
        self::assertArrayHasKey('PIM', $groups);
        self::assertArrayHasKey('API Platform', $groups);
        $names = array_merge(...array_values(array_map(static fn (array $pages): array => array_column($pages, 'name'), $groups)));
        self::assertContains('app_pim_admin', $names);
        self::assertContains('app_pim_lieu_index', $names);
        self::assertContains('api_doc', $names);
        self::assertContains('api_entrypoint', $names);
        self::assertNotContains('api_graphql_graphiql', $names);
    }
}
