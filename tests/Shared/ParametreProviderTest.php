<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Entity\Parametre;
use App\Shared\Enum\TypeParametre;
use App\Shared\Repository\ParametreRepository;
use App\Shared\Service\ParametreProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class ParametreProviderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ParametreProvider $provider;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->provider = self::getContainer()->get(ParametreProvider::class);
        $this->resetSurcharges();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->resetSurcharges();
        }
        parent::tearDown();
    }

    public function testSansSurchargeLeDefautEnvSApplique(): void
    {
        // Valeurs de .env : COMPLETENESS_REMINDER_THRESHOLD=60, BOX_OCR_ENABLED=0.
        self::assertSame(60, $this->provider->int('completude.seuil_rappel'));
        self::assertFalse($this->provider->bool('box.ocr_active'));
    }

    public function testLaSurchargeEnBasePrimeApresInvalidation(): void
    {
        // Première lecture : amorce le cache des surcharges (vide).
        self::assertSame(60, $this->provider->int('completude.seuil_rappel'));

        $this->parametre('completude.seuil_rappel')->surcharger('42');
        $this->entityManager->flush();

        // Le cache sert encore l'ancien état tant qu'il n'est pas invalidé.
        self::assertSame(60, $this->provider->int('completude.seuil_rappel'));
        $this->provider->invalider();
        self::assertSame(42, $this->provider->int('completude.seuil_rappel'));
    }

    public function testLaSurchargeBooleenneEstCastee(): void
    {
        $this->parametre('box.ocr_active')->surcharger('1');
        $this->entityManager->flush();
        $this->provider->invalider();

        self::assertTrue($this->provider->bool('box.ocr_active'));
    }

    public function testRevenirAuDefautRestaureLaValeurEnv(): void
    {
        $parametre = $this->parametre('completude.seuil_rappel');
        $parametre->surcharger('42');
        $this->entityManager->flush();
        $this->provider->invalider();
        self::assertSame(42, $this->provider->int('completude.seuil_rappel'));

        $parametre->revenirAuDefaut();
        $this->entityManager->flush();
        $this->provider->invalider();
        self::assertSame(60, $this->provider->int('completude.seuil_rappel'));
    }

    public function testUnParametreInconnuLeveUneException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provider->string('inconnu.nexiste_pas');
    }

    /** Charge le paramètre du catalogue, en le créant si les seeds n'ont pas été joués. */
    private function parametre(string $nom): Parametre
    {
        $repository = self::getContainer()->get(ParametreRepository::class);
        $parametre = $repository->parNom($nom);
        if (!$parametre instanceof Parametre) {
            $parametre = new Parametre($nom, 'Paramètre de test.', str_contains($nom, 'ocr') ? TypeParametre::Booleen : TypeParametre::Entier);
            $this->entityManager->persist($parametre);
            $this->entityManager->flush();
        }

        return $parametre;
    }

    private function resetSurcharges(): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE parametre SET valeur = NULL, updated_at = NULL');
        $this->provider->invalider();
    }
}
