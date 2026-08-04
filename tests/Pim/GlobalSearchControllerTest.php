<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class GlobalSearchControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    public function testAnonymousAndBasicUsersCannotAccessSearch(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/recherche');
        self::assertResponseRedirects('http://localhost/login');

        $client->loginUser(new User('basic-search@example.test'));
        $client->request('GET', '/admin/recherche');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorSeesEmptyPageAndHeaderSearch(): void
    {
        $client = self::createClient();
        $client->loginUser(new User('editor-search@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/recherche');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Recherche globale');
        self::assertSelectorExists('header form.header-search[action="/admin/recherche"] input[name="q"]');
        self::assertSelectorTextContains('main', 'Saisissez un code');
        self::assertSelectorNotExists('main table');
    }

    public function testEmptyLimitIsAccepted(): void
    {
        $client = self::createClient();
        $client->loginUser(new User('empty-limit-search@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/recherche?limit=&q=&status=&submit=&type=lieu');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="limit"]');
    }

    public function testInvalidPublicParametersReturnBadRequest(): void
    {
        $client = self::createClient();
        $client->loginUser(new User('invalid-search@example.test', ['ROLE_BP_EDITOR']));

        foreach (['type=traiteur', 'status=inconnu', 'limit=0', 'limit=101', 'cursor=invalide'] as $query) {
            $client->request('GET', '/admin/recherche?'.$query);
            self::assertResponseStatusCodeSame(400, $query);
        }
    }
}
