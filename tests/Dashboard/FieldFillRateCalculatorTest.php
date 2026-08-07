<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Dashboard\Service\FieldFillRateCalculator;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class FieldFillRateCalculatorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testRatesRespectConditionalFieldsAndFilledValues(): void
    {
        $withWebsite = new Lieu();
        $withWebsite->changeLabel('Lieu renseigné');
        $withWebsite->changeGeneraleWebsiteUrl('https://exemple.fr');
        $withWebsite->changeChambreHebergement(false);
        $this->entityManager->persist($withWebsite);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $calculator = self::getContainer()->get(FieldFillRateCalculator::class);
        $withoutRooms = $calculator->compute()['perType'][TypeFiche::Lieu->value];

        self::assertSame(1, $withoutRooms['fiches']);
        self::assertGreaterThan(0, $withoutRooms['fieldCount']);
        self::assertNotSame([], $withoutRooms['worstFields']);
        foreach ($withoutRooms['worstFields'] as $field) {
            self::assertGreaterThanOrEqual(0.0, $field['rate']);
            self::assertLessThanOrEqual(100.0, $field['rate']);
            self::assertStringStartsNotWith('CHAMBRE_', $field['code'], 'Les champs chambre ne s’appliquent pas sans hébergement.');
        }

        // Activer l'hébergement rend les champs chambre applicables : le
        // nombre de champs mesurés augmente.
        $lieu = $this->entityManager->find(Lieu::class, $withWebsite->id());
        self::assertInstanceOf(Lieu::class, $lieu);
        $lieu->changeChambreHebergement(true);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $withRooms = $calculator->compute()['perType'][TypeFiche::Lieu->value];
        self::assertGreaterThan($withoutRooms['fieldCount'], $withRooms['fieldCount']);
    }

    private function clearTables(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['pim_fiche_attribute_value', 'pim_ressource_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
