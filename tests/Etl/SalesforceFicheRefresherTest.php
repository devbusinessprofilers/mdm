<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheSalesforceRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceFichePayloadBuilder;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Etl\Service\SalesforceClientInterface;
use App\Etl\Service\SalesforceFicheRefresher;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class SalesforceFicheRefresherTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FakeSalesforceClient $salesforce;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->salesforce = new FakeSalesforceClient();
        self::getContainer()->set(SalesforceClientInterface::class, $this->salesforce);
        self::getContainer()->set(MarketplaceClientInterface::class, new RecordingMarketplaceClient());
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
        $this->marketplaceSite();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testRefreshEnregistreLesDonneesEtEcrasePartenaire(): void
    {
        $fiche = $this->publishedFiche();
        $this->salesforce->records = [[
            'Id' => '01tXX',
            'ID__c' => (string) $fiche->code(),
            'RSE_Note_globale__c' => 4.2,
            'RSE_Achats_responsables__c' => '3.5',
            'Evaluation_client__c' => 0.0,
            'Type_de_procedure_judiciaire__c' => 'Redressement judiciaire',
            'Contrat_avec_comptes_Achat_normal__c' => 'Compte A;Compte B',
            'Contrat_avec_comptes_Mise_en_relation__c' => 'Compte B',
            'commission_standard__c' => '0.0',
        ]];

        $stats = $this->refresher()->refresh();

        self::assertSame(['recus' => 1, 'matches' => 1, 'modifies' => 1, 'inconnus' => 0], $stats);
        $donnees = $this->donneesSalesforce()->forFiche($fiche->id());
        self::assertNotNull($donnees);
        self::assertSame(4.2, $donnees->noteGlobale());
        self::assertSame(3.5, $donnees->noteAchatsResponsables());
        self::assertNull($donnees->noteMobilite());
        // Évaluation client à zéro = pas de note (règle legacy).
        self::assertNull($donnees->evaluationClient());
        self::assertSame('Redressement judiciaire', $donnees->procedureJudiciaire());
        self::assertSame(['Compte A', 'Compte B'], $donnees->contratsComptes());
        $fiche = $this->fiches()->find($fiche->id());
        self::assertInstanceOf(Fiche::class, $fiche);
        // Commission nulle : Salesforce retire le statut partenaire, sans
        // transition de workflow.
        self::assertFalse($fiche->partenaireBp());
        self::assertSame(StatutFiche::Publiee, $fiche->status());
        self::assertSame(1, $this->outboxCount());
    }

    public function testRefreshIdempotentSansChangement(): void
    {
        $fiche = $this->publishedFiche();
        $this->salesforce->records = [[
            'ID__c' => (string) $fiche->code(),
            'RSE_Note_globale__c' => 4.2,
            'commission_standard__c' => '12.0',
        ]];
        $this->refresher()->refresh();
        $premierOutbox = $this->outboxCount();

        $stats = $this->refresher()->refresh();

        self::assertSame(0, $stats['modifies']);
        self::assertSame($premierOutbox, $this->outboxCount());
    }

    public function testProduitSansFichePimEstIgnore(): void
    {
        $this->salesforce->records = [[
            'ID__c' => '999999999',
            'RSE_Note_globale__c' => 1.0,
        ]];

        $stats = $this->refresher()->refresh();

        self::assertSame(['recus' => 1, 'matches' => 0, 'modifies' => 0, 'inconnus' => 1], $stats);
    }

    public function testPayloadContientLeBlocSalesforceUneFoisRafraichi(): void
    {
        $fiche = $this->publishedFiche();
        /** @var MarketplaceFichePayloadBuilder $builder */
        $builder = self::getContainer()->get(MarketplaceFichePayloadBuilder::class);
        // Fiche inconnue de Salesforce : clé absente, la marketplace ne
        // touche pas à ses valeurs.
        self::assertArrayNotHasKey('salesforce', $builder->build($fiche)['data']);

        $this->salesforce->records = [[
            'ID__c' => (string) $fiche->code(),
            'RSE_Note_globale__c' => 4.2,
            'Evaluation_client__c' => '4.8',
            'Contrat_avec_comptes_Achat_normal__c' => 'Compte A',
            'commission_standard__c' => 10.0,
        ]];
        $this->refresher()->refresh();

        $fiche = $this->fiches()->find($fiche->id());
        self::assertInstanceOf(Fiche::class, $fiche);
        self::assertTrue($fiche->partenaireBp());
        $data = $builder->build($fiche)['data'];
        self::assertSame(4.2, $data['salesforce']['rse']['globale']);
        self::assertNull($data['salesforce']['rse']['impactSocial']);
        self::assertSame(4.8, $data['salesforce']['evaluationClient']);
        self::assertNull($data['salesforce']['procedureJudiciaire']);
        self::assertSame(['Compte A'], $data['salesforce']['contratsComptes']);
        self::assertTrue($data['partenaireBp']);
    }

    private function refresher(): SalesforceFicheRefresher
    {
        return self::getContainer()->get(SalesforceFicheRefresher::class);
    }

    private function fiches(): FicheRepository
    {
        return self::getContainer()->get(FicheRepository::class);
    }

    private function donneesSalesforce(): FicheSalesforceRepository
    {
        return self::getContainer()->get(FicheSalesforceRepository::class);
    }

    private function publishedFiche(): Fiche
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Château des tests');
        $fiche = $lieu->fiche();
        $fiche->replaceSiteDiffusion([$this->marketplaceSite()]);
        $fiche->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $fiche;
    }

    private function marketplaceSite(): SiteDiffusion
    {
        /** @var SiteDiffusionRepository $sites */
        $sites = self::getContainer()->get(SiteDiffusionRepository::class);
        $site = $sites->findOneByCode(MarketplaceSyncScheduler::SITE_CODE);
        if (null === $site) {
            $site = new SiteDiffusion(MarketplaceSyncScheduler::SITE_CODE, 'Marketplace Business Profilers', 'Business Profilers');
            $this->entityManager->persist($site);
            $this->entityManager->flush();
        }

        return $site;
    }

    private function outboxCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [SyncFicheMarketplace::class],
        );
    }

    private function clear(): void
    {
        foreach (
            [
                'outbox_message',
                'etl_fiche_salesforce',
                'etl_fiche_marketplace',
                'pim_fiche_site_diffusion',
                'pim_fiche_attribute_value',
                'pim_ressource_lieu',
                'pim_acces_lieu',
                'pim_periode_fermeture',
                'pim_lieu_administratif',
                'pim_lieu_tarification',
                'pim_lieu',
                'pim_fiche',
                'pim_localisation',
            ] as $table
        ) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
