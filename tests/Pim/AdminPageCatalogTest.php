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
        self::assertContains('app_pim_global_search', $names);
        self::assertContains('app_pim_lieu_index', $names);
        self::assertContains('app_pim_restaurant_index', $names);
        self::assertContains('app_pim_restaurant_history', $names);
        self::assertContains('app_pim_service_index', $names);
        self::assertContains('app_pim_service_history', $names);
        self::assertContains('app_dam_dashboard', $names);
        self::assertContains('_api_/v1/services_get_collection', $names);
        self::assertContains('_api_/v1/restaurants_get_collection', $names);
        self::assertContains('api_doc', $names);
        self::assertContains('api_entrypoint', $names);
        self::assertNotContains('api_graphql_graphiql', $names);
    }
}
