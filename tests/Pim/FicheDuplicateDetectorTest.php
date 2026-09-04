<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Form\FicheCreation;
use App\Pim\Service\EntrepriseInfo;
use App\Pim\Service\FicheDuplicateDetector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class FicheDuplicateDetectorTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private FicheDuplicateDetector $detector;
    private Lieu $existing;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        parent::setUp();
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->detector = self::getContainer()->get(FicheDuplicateDetector::class);
        $this->cleanDatabase();

        $this->existing = new Lieu();
        $this->existing->changeLabel('Château Duplicata');
        $this->existing->administratif()->changeInfoLegaleSiret('480 674 100 00031');
        $localisation = new Localisation();
        $localisation->changeCountryCode('FR');
        $localisation->changeRuePostale('1 avenue du Général de Gaulle');
        $localisation->changeCodePostal('60500');
        $localisation->changeVille('Chantilly');
        $this->existing->fiche()->changeLocalisation($localisation);
        $this->entityManager->persist($this->existing);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->entityManager->clear();
            $this->cleanDatabase();
        }
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        foreach ([
            'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche_administratif',
            'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }

    public function testMatchesLabelCaseInsensitively(): void
    {
        $creation = new FicheCreation();
        $creation->label = 'château DUPLICATA';

        $candidates = $this->detector->detect($creation, null);

        self::assertCount(1, $candidates);
        self::assertSame(['nom'], $candidates[0]->reasons);
        self::assertSame('Château Duplicata', $candidates[0]->label);
        self::assertNotNull($candidates[0]->url);
    }

    public function testMatchesUserAddressFingerprint(): void
    {
        $creation = new FicheCreation();
        $creation->label = 'Un tout autre nom';
        $localisation = new Localisation();
        $localisation->changeCountryCode('FR');
        $localisation->changeRuePostale('1 AVENUE DU GENERAL DE GAULLE');
        $localisation->changeCodePostal('60500');
        $localisation->changeVille('CHANTILLY');
        $creation->localisation = $localisation;

        $candidates = $this->detector->detect($creation, null);

        self::assertCount(1, $candidates);
        self::assertSame(['adresse'], $candidates[0]->reasons);
    }

    public function testMergesApiSiretAndAddressReasonsOnASingleCandidate(): void
    {
        $creation = new FicheCreation();
        $creation->label = 'Un tout autre nom';

        $candidates = $this->detector->detect($creation, new EntrepriseInfo(
            denomination: 'BUSINESS PROFILERS',
            siren: '480674100',
            siret: '48067410000031',
            rue: '1 AVENUE DU GENERAL DE GAULLE',
            codePostal: '60500',
            ville: 'CHANTILLY',
        ));

        self::assertCount(1, $candidates);
        self::assertSame($this->existing->fiche()->idString(), $candidates[0]->ficheId);
        self::assertEqualsCanonicalizing(['adresse', 'siret'], $candidates[0]->reasons);
    }

    public function testReturnsNothingWhenNoSignalMatches(): void
    {
        $creation = new FicheCreation();
        $creation->label = 'Fiche totalement inédite';

        self::assertSame([], $this->detector->detect($creation, null));
    }
}
