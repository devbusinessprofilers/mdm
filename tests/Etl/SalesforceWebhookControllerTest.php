<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Message\RefreshFichesSalesforce;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[Group('database')]
final class SalesforceWebhookControllerTest extends WebTestCase
{
    private const TOKEN = 'test-salesforce-webhook-token';

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        putenv('SALESFORCE_WEBHOOK_TOKEN='.self::TOKEN);
        $_ENV['SALESFORCE_WEBHOOK_TOKEN'] = self::TOKEN;
        $_SERVER['SALESFORCE_WEBHOOK_TOKEN'] = self::TOKEN;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('SALESFORCE_WEBHOOK_TOKEN');
        unset($_ENV['SALESFORCE_WEBHOOK_TOKEN'], $_SERVER['SALESFORCE_WEBHOOK_TOKEN']);

        parent::tearDown();
    }

    public function testWebhookMasqueTantQuAucunJetonNEstConfigure(): void
    {
        putenv('SALESFORCE_WEBHOOK_TOKEN=');
        $_ENV['SALESFORCE_WEBHOOK_TOKEN'] = '';
        $_SERVER['SALESFORCE_WEBHOOK_TOKEN'] = '';

        $client = self::createClient();
        $this->notifier($client, ['codes' => [42]], self::TOKEN);
        self::assertResponseStatusCodeSame(404);
    }

    public function testJetonManquantOuInvalideRefuse(): void
    {
        $client = self::createClient();

        $this->notifier($client, ['codes' => [42]], null);
        self::assertResponseStatusCodeSame(401);

        $this->notifier($client, ['codes' => [42]], 'mauvais-jeton');
        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->transportEtl()->getSent());
    }

    public function testPayloadInvalideRejete(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/salesforce/produits', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], content: '{pas du json');
        self::assertResponseStatusCodeSame(400);

        $this->notifier($client, ['codes' => []], self::TOKEN);
        self::assertResponseStatusCodeSame(400);

        $this->notifier($client, ['autre' => true], self::TOKEN);
        self::assertResponseStatusCodeSame(400);

        $this->notifier($client, ['codes' => [0]], self::TOKEN);
        self::assertResponseStatusCodeSame(400);

        $this->notifier($client, ['codes' => ['abc']], self::TOKEN);
        self::assertResponseStatusCodeSame(400);

        $this->notifier($client, ['codes' => range(1, 201)], self::TOKEN);
        self::assertResponseStatusCodeSame(400);

        self::assertSame([], $this->transportEtl()->getSent());
    }

    public function testNotificationPlanifieUnRefreshCibleParCode(): void
    {
        $client = self::createClient();
        // Codes en entier ou en chaîne (ID__c est un champ texte), doublon ignoré.
        $this->notifier($client, ['codes' => [123, '456', 123]], self::TOKEN);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['acceptes' => 2], json_decode((string) $client->getResponse()->getContent(), true));

        $envelopes = $this->transportEtl()->getSent();
        self::assertCount(2, $envelopes);
        $codes = array_map(static function ($envelope): ?int {
            $message = $envelope->getMessage();
            self::assertInstanceOf(RefreshFichesSalesforce::class, $message);

            return $message->code;
        }, $envelopes);
        self::assertSame([123, 456], $codes);
    }

    /** @param array<string, mixed> $payload */
    private function notifier(KernelBrowser $client, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $client->request('POST', '/api/salesforce/produits', server: $server, content: (string) json_encode($payload));
    }

    private function transportEtl(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.etl');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
