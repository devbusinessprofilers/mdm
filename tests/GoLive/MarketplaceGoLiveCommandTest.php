<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\GoLive\EtapeEtat;
use App\GoLive\EtapeStatut;
use App\GoLive\MarketplaceProbeInterface;
use App\GoLive\SousCommandeRunnerFactoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('database')]
final class MarketplaceGoLiveCommandTest extends KernelTestCase
{
    private RecordingSousCommandeRunner $runner;

    /** @var array<string, array{env: ?string, server: ?string}> */
    private array $envSauvegarde = [];

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->runner = new RecordingSousCommandeRunner();
        self::getContainer()->set(SousCommandeRunnerFactoryInterface::class, new RecordingSousCommandeRunnerFactory($this->runner));
    }

    protected function tearDown(): void
    {
        foreach ($this->envSauvegarde as $nom => $origine) {
            if (null === $origine['env']) {
                unset($_ENV[$nom]);
            } else {
                $_ENV[$nom] = $origine['env'];
            }
            if (null === $origine['server']) {
                unset($_SERVER[$nom]);
            } else {
                $_SERVER[$nom] = $origine['server'];
            }
        }
        $this->envSauvegarde = [];
        parent::tearDown();
    }

    public function testLeRapportSeulNEnfileRien(): void
    {
        $this->probe(new EtapeEtat(EtapeStatut::NonConfiguree, 'MARKETPLACE_SYNC_API_URL vide'));

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
        self::assertStringContainsString('purge-referentiels-legacy', $tester->getDisplay());
        self::assertStringContainsString('Phase 1', $tester->getDisplay());
    }

    public function testExecuterSansPhaseEstInvalide(): void
    {
        $this->probe(new EtapeEtat(EtapeStatut::Fait));

        $tester = $this->tester();
        $tester->execute(['--executer' => true]);

        self::assertSame(2, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
    }

    public function testUrlNonConfigureeBloqueLExecution(): void
    {
        $this->surchargerEnv('MARKETPLACE_SYNC_API_URL', '');
        $this->probe(new EtapeEtat(EtapeStatut::NonConfiguree, 'MARKETPLACE_SYNC_API_URL vide'));

        $tester = $this->tester();
        $tester->execute(['--phase' => 'dictionnaire', '--executer' => true]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
    }

    public function testAuthentificationRefuseeBloqueLExecution(): void
    {
        $this->surchargerEnv('MARKETPLACE_SYNC_API_URL', 'http://marketplace.test');
        $this->probe(new EtapeEtat(EtapeStatut::Bloquee, 'authentification refusée (HTTP 401)'));

        $tester = $this->tester();
        $tester->execute(['--phase' => 'dictionnaire', '--executer' => true]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([], $this->runner->appels);
        self::assertStringContainsString('HTTP 401', $tester->getDisplay());
    }

    public function testPhaseDictionnaireEnfileLeLovEtRappelleLaPurge(): void
    {
        $this->surchargerEnv('MARKETPLACE_SYNC_API_URL', 'http://marketplace.test');
        $this->probe(new EtapeEtat(EtapeStatut::Fait, 'authentification OK'));

        $tester = $this->tester();
        $tester->execute(['--phase' => 'dictionnaire', '--executer' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([['commande' => 'app:marketplace:sync', 'parametres' => ['--lov' => true]]], $this->runner->appels);
        self::assertStringContainsString('purge-referentiels-legacy', $tester->getDisplay());
        self::assertStringContainsString('CÔTÉ MARKETPLACE', $tester->getDisplay());
    }

    public function testPhaseFichesEnfileLaRepriseEtPointeLaSurveillance(): void
    {
        $this->surchargerEnv('MARKETPLACE_SYNC_API_URL', 'http://marketplace.test');
        $this->probe(new EtapeEtat(EtapeStatut::Fait, 'authentification OK'));

        $tester = $this->tester();
        $tester->execute(['--phase' => 'fiches', '--executer' => true, '--batch' => '50']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([['commande' => 'app:marketplace:sync', 'parametres' => ['--all' => true, '--batch' => '50']]], $this->runner->appels);
        self::assertStringContainsString('/outils', $tester->getDisplay());
        self::assertStringContainsString('--failed', $tester->getDisplay());
    }

    private function probe(EtapeEtat $etat): void
    {
        self::getContainer()->set(MarketplaceProbeInterface::class, new FakeMarketplaceProbe($etat));
    }

    private function surchargerEnv(string $nom, string $valeur): void
    {
        $this->envSauvegarde[$nom] ??= [
            'env' => isset($_ENV[$nom]) ? (string) $_ENV[$nom] : null,
            'server' => isset($_SERVER[$nom]) ? (string) $_SERVER[$nom] : null,
        ];
        $_ENV[$nom] = $valeur;
        $_SERVER[$nom] = $valeur;
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:marketplace:go-live'));
    }
}
