<?php

declare(strict_types=1);

namespace App\Tests\Enrichment;

use App\Account\Entity\User;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheTranslationControllerTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->entityManager->clear();
            $this->connection->executeStatement('DELETE FROM audit_change');
            $this->connection->executeStatement('DELETE FROM audit_revision');
            $this->connection->executeStatement('DELETE FROM enrichment_fiche_translation');
            $this->connection->executeStatement('DELETE FROM pim_lieu');
            $this->connection->executeStatement('DELETE FROM pim_fiche');
            $this->connection->executeStatement('DELETE FROM account_user');
        }
        parent::tearDown();
    }

    public function testValidatorSeesAllFieldsAndCanCreateAManualTranslation(): void
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $validator = new User('translation-validator@example.test', ['ROLE_BP_VALIDATOR']);
        $validator->setPassword('not-used-by-login-user');
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu à traduire');
        $lieu->changeDescGenerale('Description française');
        $this->entityManager->persist($validator);
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $client->loginUser($validator);

        $crawler = $client->request('GET', '/referentiel/fiche/'.$lieu->id().'/traductions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Détails des accès PMR');
        self::assertSelectorTextContains('main', 'Source non renseignée');
        self::assertSelectorTextContains('main', 'Description française');
        self::assertSelectorTextContains('main', 'Allemand');
        $formName = 'translation_'.substr(hash('sha256', 'nom|en'), 0, 20);
        $form = $crawler->filter('form[name="'.$formName.'"]')->form();
        $client->submit($form, [$formName.'[value]' => 'Venue to translate']);

        self::assertResponseRedirects('/referentiel/fiche/'.$lieu->id().'/traductions');
        $this->entityManager->clear();
        $translation = $this->entityManager->getRepository(FicheTranslation::class)->findOneBy([
            'fiche' => $lieu->fiche()->id(),
            'fieldPath' => 'nom',
            'locale' => SupportedLocale::En,
        ]);
        self::assertInstanceOf(FicheTranslation::class, $translation);
        self::assertSame('Venue to translate', $translation->translatedText());
    }
}
