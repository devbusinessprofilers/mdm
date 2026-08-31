<?php

declare(strict_types=1);

namespace App\Tests\Pim\Fusion;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Fusion\FicheFusionneur;
use App\Pim\Service\PhotoUsageCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class FicheFusionneurTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }
        parent::tearDown();
    }

    public function testFusionneChampsChoisisEtUnionsEtArchiveLAbsorbee(): void
    {
        $acteur = new User('fusion@example.test', ['ROLE_BP_VALIDATOR']);
        $acteur->setPassword('not-used');
        $this->entityManager->persist($acteur);

        $siteA = new SiteDiffusion('FUSA_TEST', 'Site A', 'Généralistes');
        $siteB = new SiteDiffusion('FUSB_TEST', 'Site B', 'Généralistes');
        $this->entityManager->persist($siteA);
        $this->entityManager->persist($siteB);

        // Survivante : publiée, avec sa photo principale, une salle, un
        // collaborateur, un site et un site internet.
        $lieuA = new Lieu();
        $lieuA->changeLabel('Château des Deux');
        $lieuA->changeGeneraleWebsiteUrl('https://ancien.example.test');
        $lieuA->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $this->photo($lieuA, 'asset-commun', 'PHOTO_FACADE');
        $this->salle($lieuA, 'Salle Bleue', 80);
        $lieuA->fiche()->publishForImport();
        $this->entityManager->persist($lieuA);

        // Absorbée : Business Premium actif, site internet plus récent, photo
        // commune (dédoublonnée) + photo propre (clonée en fin de position :
        // la principale de la survivante — sa première photo — prime),
        // salle commune + salle propre, collaborateur commun + propre, site propre.
        $lieuB = new Lieu();
        $lieuB->changeLabel('Château des Deux (doublon)');
        $lieuB->changeGeneraleWebsiteUrl('https://nouveau.example.test');
        $lieuB->fiche()->changeBusinessPremium(true);
        $lieuB->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_5']);
        $this->photo($lieuB, 'asset-commun', PhotoUsageCatalog::DEFAUT);
        $this->photo($lieuB, 'asset-propre', 'PHOTO_CHAMBRE');
        $this->salle($lieuB, 'Salle Bleue', 80);
        $this->salle($lieuB, 'Salle Rouge', 40);
        $this->entityManager->persist($lieuB);
        $this->entityManager->flush();

        $collabCommun = new FicheCollaborateur('commun@example.test');
        $collabPropre = new FicheCollaborateur('propre@example.test');
        $this->entityManager->persist($collabCommun);
        $this->entityManager->persist($collabPropre);
        $this->entityManager->persist(new FicheAffiliation($collabCommun, $lieuA->fiche(), FicheAffiliationRole::Manager, $acteur, receivesRequests: true));
        $this->entityManager->persist(new FicheAffiliation($collabCommun, $lieuB->fiche(), FicheAffiliationRole::Utilisateur, $acteur));
        $affiliationPropre = new FicheAffiliation($collabPropre, $lieuB->fiche(), FicheAffiliationRole::Administrateur, $acteur);
        $affiliationPropre->changeTraiteContenus(true);
        $this->entityManager->persist($affiliationPropre);
        $lieuA->fiche()->preserveWorkflowDuring(fn (): int => $lieuA->fiche()->ajouterSitesDiffusion([$siteA]));
        $lieuB->fiche()->ajouterSitesDiffusion([$siteB]);
        $this->entityManager->flush();

        $survivante = $lieuA->fiche();
        $absorbee = $lieuB->fiche();
        self::assertSame(StatutFiche::Publiee, $survivante->status());

        self::getContainer()->get(FicheFusionneur::class)->fusionner(
            $survivante,
            $absorbee,
            ['generale_website_url', 'business_premium'],
            $acteur,
        );
        $this->entityManager->clear();

        /** @var Fiche $survivante */
        $survivante = $this->entityManager->find(Fiche::class, (string) $survivante->id());
        /** @var Fiche $absorbee */
        $absorbee = $this->entityManager->find(Fiche::class, (string) $absorbee->id());

        // Champs choisis copiés, le reste conservé ; la survivante repart en cours.
        self::assertSame('Château des Deux', $survivante->label());
        self::assertTrue($survivante->businessPremium());
        self::assertSame(StatutFiche::EnCours, $survivante->status());
        $lieuSurvivant = self::getContainer()->get(\App\Pim\Repository\LieuRepository::class)->findOneBy(['fiche' => $survivante]);
        self::assertInstanceOf(Lieu::class, $lieuSurvivant);
        self::assertSame('https://nouveau.example.test', $lieuSurvivant->generaleWebsiteUrl());

        // Absorbée : archivée avec la trace de la survivante, contenu intact.
        self::assertSame(StatutFiche::Archivee, $absorbee->status());
        self::assertNotNull($absorbee->archivedAt());
        self::assertNotNull($absorbee->mergedIntoId());
        self::assertTrue($survivante->id()->equals($absorbee->mergedIntoId()));
        self::assertSame('Château des Deux (doublon)', $absorbee->label());

        // Photos : la commune n'est pas dupliquée, la propre est clonée avec
        // sa catégorie, en fin de position — la première photo de la
        // survivante reste la principale.
        $photos = [];
        $positions = [];
        foreach ($survivante->resources() as $resource) {
            $photos[$resource->damAssetId()] = $resource->usage();
            $positions[$resource->damAssetId()] = $resource->position();
        }
        self::assertSame(
            ['asset-commun' => 'PHOTO_FACADE', 'asset-propre' => 'PHOTO_CHAMBRE'],
            $photos,
        );
        self::assertLessThan($positions['asset-propre'], $positions['asset-commun']);
        self::assertCount(2, $absorbee->resources());

        // Salles : union par signature, la commune n'est pas dupliquée.
        $salles = array_map(static fn (Salle $salle): string => $salle->nom(), $lieuSurvivant->salles()->toArray());
        sort($salles);
        self::assertSame(['Salle Bleue', 'Salle Rouge'], $salles);

        // Collaborateurs : le commun garde son affiliation d'origine, le
        // propre est rattaché avec ses droits.
        $affiliations = self::getContainer()->get(\App\Pim\Repository\FicheAffiliationRepository::class)->findBy(['fiche' => $survivante]);
        $parEmail = [];
        foreach ($affiliations as $affiliation) {
            $parEmail[$affiliation->collaborateur()->email()] = $affiliation;
        }
        self::assertCount(2, $parEmail);
        self::assertSame(FicheAffiliationRole::Manager, $parEmail['commun@example.test']->role());
        self::assertSame(FicheAffiliationRole::Administrateur, $parEmail['propre@example.test']->role());
        self::assertTrue($parEmail['propre@example.test']->traiteContenus());

        // Sites de diffusion et LOV multi : unions dédoublonnées.
        self::assertCount(2, $survivante->siteDiffusionIds());
        $typologie = $lieuSurvivant->generaleTypologie();
        sort($typologie);
        self::assertSame(['GENERALE_TYPOLOGIE_1', 'GENERALE_TYPOLOGIE_5'], $typologie);

        // Les deux fiches partent en réindexation (retrait marketplace de
        // l'absorbée décidé en aval par IndexFicheHandler).
        $indexes = $this->connection->fetchFirstColumn("SELECT body FROM outbox_message WHERE message_type LIKE '%IndexFiche'");
        $payloads = implode(' ', array_map(strval(...), $indexes));
        self::assertStringContainsString($survivante->idString(), $payloads);
        self::assertStringContainsString($absorbee->idString(), $payloads);
    }

    public function testRefuseLaFusionDeGammesDifferentesEtDUneFicheDejaArchivee(): void
    {
        $acteur = new User('fusion2@example.test', ['ROLE_BP_VALIDATOR']);
        $acteur->setPassword('not-used');
        $this->entityManager->persist($acteur);
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu seul');
        $this->entityManager->persist($lieu);
        $restaurant = new \App\Pim\Entity\Restaurant\Restaurant();
        $restaurant->changeLabel('Restaurant seul');
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        $fusionneur = self::getContainer()->get(FicheFusionneur::class);
        $this->expectException(\DomainException::class);
        $fusionneur->fusionner($lieu->fiche(), $restaurant->fiche(), [], $acteur);
    }

    private function photo(Lieu $lieu, string $assetId, string $usage): void
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($assetId);
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($usage);
        $lieu->addRessource($resource);
        $this->entityManager->persist($resource);
    }

    private function salle(Lieu $lieu, string $nom, int $superficie): void
    {
        $salle = new Salle();
        $salle->changeNom($nom);
        $salle->changeSuperficie($superficie);
        $lieu->addSalle($salle);
        $this->entityManager->persist($salle);
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM pim_fiche_affiliation');
        $this->connection->executeStatement('DELETE FROM pim_fiche_collaborateur');
        $this->connection->executeStatement('DELETE FROM pim_fiche_site_diffusion');
        $this->connection->executeStatement("DELETE FROM pim_site_diffusion WHERE code LIKE '%_TEST'");
        $this->connection->executeStatement('DELETE FROM pim_ressource_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_salle');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement("DELETE FROM account_user WHERE email LIKE 'fusion%'");
    }
}
