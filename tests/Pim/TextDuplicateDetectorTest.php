<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\TextDuplicateAlert;
use App\Pim\Enum\DuplicateReviewStatus;
use App\Pim\Enum\TextDuplicateKind;
use App\Pim\Repository\TextDuplicateAlertRepository;
use App\Pim\Service\TextDuplicateDetector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class TextDuplicateDetectorTest extends KernelTestCase
{
    private const BASE = 'Ce domaine viticole d exception propose des seminaires et du team building en pleine nature avec un hebergement de charme et une restauration gastronomique sur place.';

    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private TextDuplicateDetector $detector;
    private TextDuplicateAlertRepository $alerts;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        parent::setUp();
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->detector = self::getContainer()->get(TextDuplicateDetector::class);
        $this->alerts = self::getContainer()->get(TextDuplicateAlertRepository::class);
        $this->cleanDatabase();
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
            'pim_text_duplicate_alert', 'pim_text_simhash_band', 'pim_text_fingerprint',
            'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche_administratif',
            'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation',
        ] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }

    private function createLieu(string $label, string $description): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $lieu->changeDescGenerale($description);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();

        return $lieu;
    }

    public function testFlagsAnExactCopyOnAnotherFiche(): void
    {
        $reference = $this->createLieu('Domaine A', self::BASE);
        $copy = $this->createLieu('Domaine B', mb_strtoupper(self::BASE));

        $this->detector->analyze($reference->fiche());
        $this->detector->analyze($copy->fiche());

        $alert = $this->pendingAlertFor($copy);
        self::assertNotNull($alert);
        self::assertSame(TextDuplicateKind::Exact, $alert->kind());
        self::assertSame(0, $alert->distance());
        self::assertSame($reference->fiche()->idString(), $alert->duplicateOf()->ficheId());
    }

    public function testFlagsANearDuplicateWithinThreshold(): void
    {
        $reference = $this->createLieu('Domaine A', self::BASE);
        $tweaked = str_replace('gastronomique', 'traditionnelle', self::BASE);
        $near = $this->createLieu('Domaine B', $tweaked);

        $this->detector->analyze($reference->fiche());
        $this->detector->analyze($near->fiche());

        $alert = $this->pendingAlertFor($near);
        self::assertNotNull($alert);
        self::assertSame(TextDuplicateKind::Near, $alert->kind());
        self::assertNotNull($alert->distance());
        self::assertGreaterThan(0, $alert->distance());
    }

    public function testIgnoresUnrelatedTexts(): void
    {
        $this->createLieu('Domaine A', self::BASE);
        $other = $this->createLieu('Bistrot B', 'Un bistrot de quartier chaleureux qui sert une cuisine du marche simple et savoureuse le midi en semaine.');

        $this->detector->analyze($other->fiche());

        self::assertNull($this->pendingAlertFor($other));
    }

    public function testResolvesTheAlertWhenTheDuplicateTextIsRewritten(): void
    {
        $reference = $this->createLieu('Domaine A', self::BASE);
        $copy = $this->createLieu('Domaine B', self::BASE);
        $this->detector->analyze($reference->fiche());
        $this->detector->analyze($copy->fiche());
        self::assertNotNull($this->pendingAlertFor($copy));

        // pendingAlertFor() a vidé l'EntityManager : on recharge une instance
        // gérée avant de muter, sinon le flush ne persisterait rien.
        $copyId = $copy->id();
        $managed = $this->entityManager->getRepository(Lieu::class)->find($copyId);
        self::assertNotNull($managed);
        $managed->changeDescGenerale('Un tout autre texte, entierement reecrit, decrivant un lieu completement different et sans lien avec le precedent.');
        $this->entityManager->flush();
        $this->detector->analyze($managed->fiche());

        self::assertNull($this->pendingAlertFor($managed));
    }

    private function pendingAlertFor(Lieu $lieu): ?TextDuplicateAlert
    {
        $this->entityManager->clear();
        foreach ($this->alerts->findBy(['ficheId' => $lieu->fiche()->idString()]) as $alert) {
            if (DuplicateReviewStatus::Pending === $alert->status()) {
                return $alert;
            }
        }

        return null;
    }
}
