<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Message\EnrichirFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\MessageHandler\EnrichirFicheHandler;
use App\Shared\Service\ParametreProvider;
use App\Tests\Support\CommeUnWorker;
use App\Tests\Support\ParametreEnBase;
use App\Vision\Service\OpenAiProviderException;
use App\Vision\Service\TextSuggestionProviderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class EnrichirFicheHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
        // Les gates sont insérées ACTIVES par la migration : neutralisées ici
        // pour des tests déterministes (aucun appel API réel), restaurées en
        // tearDown pour laisser la base de test dans l'état de la migration.
        $this->reglerGates('0');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            ParametreEnBase::fixer($this->connection, 'openai.actif', null);
            $this->reglerGates('1');
            $this->clear();
        }
        parent::tearDown();
    }

    private function reglerGates(string $valeur): void
    {
        foreach (['sirene.verif_statut_actif', 'geoapify.enrichissement_places', 'datatourisme.import_actif', 'wikidata.detection_chaine', 'atout_france.classement_actif'] as $nom) {
            ParametreEnBase::fixer($this->connection, $nom, $valeur);
        }
        self::getContainer()->get(ParametreProvider::class)->invalider();
    }

    public function testLesGatesInactifsNeProduisentRienMaisLAdresseRepartEnVerification(): void
    {
        $lieu = $this->lieu();

        $handler = self::getContainer()->get(EnrichirFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $handler, new EnrichirFiche($lieu->fiche()->idString()));

        self::assertSame(1, $this->outboxCount(VerifierAdresseFiche::class), 'La vérification BAN est toujours enfilée.');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_suggestion'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_enrichment_scan'), 'Une source inactive ne marque pas la fiche scannée.');

        // La demande est journalisée (créée par le handler faute de run au
        // message) et le résultat dit pourquoi rien n'est sorti.
        $run = $this->connection->fetchAssociative('SELECT finished_at, resultat FROM pim_fiche_enrichment_run');
        self::assertIsArray($run);
        self::assertNotNull($run['finished_at']);
        self::assertStringContainsString('"sirene":"inactif"', (string) $run['resultat']);
        self::assertStringContainsString('"ia":"inactif"', (string) $run['resultat']);
    }

    public function testLaSourceIaProposeLaDescriptionManquanteEtMarqueLeScan(): void
    {
        self::getContainer()->set(TextSuggestionProviderInterface::class, new FakeTextSuggestionProvider('Un domaine au bord de Loire, idéal pour vos séminaires.'));
        $this->activerIa();
        $sansDescription = $this->lieu();
        $avecDescription = $this->lieu('Déjà décrite à la main.');

        $handler = self::getContainer()->get(EnrichirFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $handler, new EnrichirFiche($sansDescription->fiche()->idString()));
        CommeUnWorker::traiter($this->entityManager, $handler, new EnrichirFiche($avecDescription->fiche()->idString()));

        $suggestions = $this->connection->fetchAllAssociative('SELECT source, champ, statut, valeur_proposee, payload FROM pim_fiche_suggestion');
        self::assertCount(1, $suggestions, 'Un champ déjà rempli n\'est pas re-proposé.');
        self::assertSame('ia', $suggestions[0]['source']);
        self::assertSame('lieu_desc_generale', $suggestions[0]['champ']);
        self::assertSame('en_attente', $suggestions[0]['statut']);
        self::assertStringContainsString('bord de Loire', (string) $suggestions[0]['valeur_proposee']);
        self::assertStringContainsString('bord de Loire', (string) $suggestions[0]['payload']);

        // Les deux fiches sont tracées scannées (le « rien à proposer » compte).
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pim_fiche_enrichment_scan WHERE source = 'ia'"));
    }

    public function testUneSourceIndisponibleNeMarquePasLaFicheScannee(): void
    {
        self::getContainer()->set(TextSuggestionProviderInterface::class, new FakeTextSuggestionProvider(null, new OpenAiProviderException('Trop de requêtes.', true)));
        $this->activerIa();
        $lieu = $this->lieu();

        $handler = self::getContainer()->get(EnrichirFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $handler, new EnrichirFiche($lieu->fiche()->idString()));

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_suggestion'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_enrichment_scan'), 'Une panne d\'API laisse la fiche à re-scanner.');
    }

    public function testUneSourceActiveeMaisSansFluxEstDiteNonConfiguree(): void
    {
        // Gate DATAtourisme ouverte, mais DATATOURISME_FLUX_DIR vide en test :
        // le journal doit distinguer « non configurée » de « désactivée ».
        ParametreEnBase::fixer($this->connection, 'datatourisme.import_actif', '1');
        self::getContainer()->get(ParametreProvider::class)->invalider();
        $lieu = $this->lieu();

        $handler = self::getContainer()->get(EnrichirFicheHandler::class);
        CommeUnWorker::traiter($this->entityManager, $handler, new EnrichirFiche($lieu->fiche()->idString()));

        $run = $this->connection->fetchAssociative('SELECT resultat FROM pim_fiche_enrichment_run');
        self::assertIsArray($run);
        self::assertStringContainsString('"datatourisme":"non_configuree"', (string) $run['resultat']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM pim_fiche_enrichment_scan'), 'Une source non configurée ne marque pas la fiche scannée.');
    }

    private function activerIa(): void
    {
        ParametreEnBase::fixer($this->connection, 'openai.actif', '1');
        self::getContainer()->get(ParametreProvider::class)->invalider();
    }

    private function lieu(?string $description = null): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Domaine des enrichissements');
        $lieu->changeDescGenerale($description);
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeRuePostale('24 Rue des Tests');
        $localisation->changeCodePostal('49590');
        $localisation->changeVille("Fontevraud-l'Abbaye");
        $lieu->changeLocalisation($localisation);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu;
    }

    private function outboxCount(string $messageType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [$messageType],
        );
    }

    private function clear(): void
    {
        foreach ([
            'outbox_message',
            'pim_fiche_suggestion',
            'pim_fiche_enrichment_scan',
            'pim_fiche_enrichment_run',
            'pim_fiche_search',
            'pim_fiche_attribute_value',
            'pim_lieu_administratif',
            'pim_lieu_tarification',
            'pim_lieu',
            'pim_fiche',
            'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}

final readonly class FakeTextSuggestionProvider implements TextSuggestionProviderInterface
{
    public function __construct(private ?string $reponse, private ?OpenAiProviderException $erreur = null)
    {
    }

    public function suggerer(string $prompt, string $model): string
    {
        if (null !== $this->erreur) {
            throw $this->erreur;
        }

        return (string) $this->reponse;
    }
}
