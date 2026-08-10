<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class FicheImportControllerTest extends WebTestCase
{
    private Connection $connection;
    private ?string $uploadedStoragePath = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->uploadedStoragePath && is_file($this->uploadedStoragePath)) {
            unlink($this->uploadedStoragePath);
        }
        if (isset($this->connection)) {
            $this->connection->executeStatement('DELETE FROM etl_import_job_error');
            $this->connection->executeStatement('DELETE FROM etl_import_job');
            $this->connection->executeStatement('DELETE FROM outbox_message');
            $this->connection->executeStatement('DELETE FROM account_user');
        }

        parent::tearDown();
    }

    public function testAccessIsRestrictedToValidators(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/import-fiches');
        self::assertResponseRedirects('http://localhost/connexion');

        $client->loginUser($this->persistUser('editor-import@example.test', ['ROLE_BP_EDITOR']));
        $client->request('GET', '/admin/import-fiches');
        self::assertResponseStatusCodeSame(403);
    }

    public function testValidatorCanDownloadTemplate(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('validator-template@example.test', ['ROLE_BP_VALIDATOR']));

        $client->request('GET', '/admin/import-fiches/modele/lieu');
        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        self::assertStringContainsString('modele-import-lieu.xlsx', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('PK', $client->getInternalResponse()->getContent());
    }

    public function testValidatorCanUploadACsvAndSeeThePendingJob(): void
    {
        $client = self::createClient();
        $client->loginUser($this->persistUser('validator-upload@example.test', ['ROLE_BP_VALIDATOR']));

        $csv = tempnam(sys_get_temp_dir(), 'import');
        self::assertIsString($csv);
        file_put_contents($csv, "code;label\n;Lieu importé\n");

        $client->request('GET', '/admin/import-fiches');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Import de fiches');

        $client->submitForm('Lancer l’import', [
            'fiche_import_upload[type]' => 'lieu',
            'fiche_import_upload[file]' => $csv,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'import');

        $job = $this->connection->fetchAssociative('SELECT type, status, storage_path, original_filename FROM etl_import_job');
        self::assertIsArray($job);
        self::assertSame('lieu', $job['type']);
        self::assertSame('en_attente', $job['status']);

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $this->uploadedStoragePath = $projectDir.'/var/import/'.$job['storage_path'];
        self::assertFileExists($this->uploadedStoragePath);
    }

    /** @param list<string> $roles */
    private function persistUser(string $email, array $roles = []): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM account_user');

        $user = new User($email, $roles);
        $user->setPassword('not-used-by-login-user');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
