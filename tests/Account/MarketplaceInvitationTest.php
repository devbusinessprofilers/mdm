<?php

declare(strict_types=1);

namespace App\Tests\Account;

use App\Account\Service\MarketplaceHttpGateway;
use App\Pim\Entity\FicheCollaborateur;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MarketplaceInvitationTest extends TestCase
{
    public function testLaPasserelleTransmetLeCollaborateurALaMarketplace(): void
    {
        $requetes = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requetes): MockResponse {
            $requetes[] = ['method' => $method, 'url' => $url, 'body' => json_decode((string) $options['body'], true)];

            return new MockResponse('', ['http_code' => 201]);
        });
        $gateway = new MarketplaceHttpGateway($client, new NullLogger(), 'https://marketplace.invalid/api', 'jeton');
        $collaborateur = new FicheCollaborateur('camille@exemple.test', 'Camille', 'Berthier');
        $collaborateur->changePhone('+33 3 44 62 37 37');

        $gateway->envoyerInvitation($collaborateur, '01JEXEMPLE0000000000000000', 'Bienvenue');

        self::assertCount(1, $requetes);
        self::assertSame('POST', $requetes[0]['method']);
        self::assertSame('https://marketplace.invalid/api/collaborateurs', $requetes[0]['url']);
        self::assertSame([
            'email' => 'camille@exemple.test',
            'firstName' => 'Camille',
            'lastName' => 'Berthier',
            'phone' => '+33 3 44 62 37 37',
            'language' => 'fr',
            'ficheId' => '01JEXEMPLE0000000000000000',
            'message' => 'Bienvenue',
        ], $requetes[0]['body']);
    }

    public function testUnRefusDeLaMarketplaceFaitRejouerLeMessage(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $gateway = new MarketplaceHttpGateway($client, new NullLogger(), 'https://marketplace.invalid/api', '');

        $this->expectException(\RuntimeException::class);
        $gateway->envoyerInvitation(new FicheCollaborateur('camille@exemple.test'), '01JEXEMPLE0000000000000000');
    }

    public function testSansUrlConfigureeLEnvoiEstIgnore(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('Aucune requête ne doit partir quand l\'URL n\'est pas configurée.');
        });
        $gateway = new MarketplaceHttpGateway($client, new NullLogger(), '', '');

        $gateway->envoyerInvitation(new FicheCollaborateur('camille@exemple.test'), '01JEXEMPLE0000000000000000');

        self::assertSame(0, $client->getRequestsCount());
    }
}
