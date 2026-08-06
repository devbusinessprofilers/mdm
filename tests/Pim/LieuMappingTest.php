<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\LieuAdministratif;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LieuMappingTest extends KernelTestCase
{
    public function testBibleFieldsAndLocalisationAreMappedSeparately(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieuMetadata = $entityManager->getClassMetadata(Lieu::class);
        $ficheMetadata = $entityManager->getClassMetadata(Fiche::class);
        $localisationMetadata = $entityManager->getClassMetadata(Localisation::class);
        $mediaMetadata = $entityManager->getClassMetadata(MediaAsset::class);

        // Les champs communs, les LOV, les champs répétables et les fichiers ne sont plus dans la table large.
        self::assertCount(62, $lieuMetadata->getFieldNames());
        foreach (['completenessGlobal', 'completenessMarketplace', 'completenessThematicSites', 'completenessSalesforce', 'completenessProviderPortal', 'completenessCalculatedAt', 'completenessRevision'] as $field) {
            self::assertTrue($lieuMetadata->hasField($field));
        }
        self::assertSame(100, $lieuMetadata->getFieldMapping('generaleWebsiteUrl')->length);
        self::assertSame(150, $lieuMetadata->getFieldMapping('pmrDetails')->length);
        self::assertSame(1000, $lieuMetadata->getFieldMapping('descGenerale')->length);
        self::assertSame(35, $lieuMetadata->getFieldMapping('atout1')->length);
        foreach (['fiche', 'administratif', 'tarification', 'salles', 'periodesFermeture', 'acces', 'ressources'] as $association) {
            self::assertTrue($lieuMetadata->hasAssociation($association));
        }
        self::assertSame(Fiche::class, $lieuMetadata->getAssociationTargetClass('fiche'));
        self::assertSame(LieuAdministratif::class, $lieuMetadata->getAssociationTargetClass('administratif'));
        self::assertSame(LieuTarification::class, $lieuMetadata->getAssociationTargetClass('tarification'));
        self::assertFalse($lieuMetadata->hasField('pays'));
        self::assertFalse($lieuMetadata->hasField('latitude'));
        self::assertFalse($lieuMetadata->hasField('code'));
        self::assertFalse($lieuMetadata->hasField('generaleTypologie'));
        self::assertFalse($lieuMetadata->hasField('infoLegaleNom'));
        self::assertFalse($lieuMetadata->hasField('seminaireJourneeJourneeEtude'));
        foreach (['descGeneralePointInteret', 'rseDescGenerale'] as $bibleField) {
            self::assertTrue($lieuMetadata->hasField($bibleField));
        }
        foreach (['configNomSalle', 'dispoNomPeriode', 'accessAeroport', 'photo'] as $legacyField) {
            self::assertFalse($lieuMetadata->hasField($legacyField));
        }

        self::assertSame(Lieu::class, $entityManager->getClassMetadata(Salle::class)->getAssociationTargetClass('lieu'));
        self::assertSame(Lieu::class, $entityManager->getClassMetadata(PeriodeFermeture::class)->getAssociationTargetClass('lieu'));
        self::assertSame(Lieu::class, $entityManager->getClassMetadata(AccesLieu::class)->getAssociationTargetClass('lieu'));
        self::assertSame(Lieu::class, $entityManager->getClassMetadata(RessourceLieu::class)->getAssociationTargetClass('lieu'));

        self::assertCount(15, $ficheMetadata->getFieldNames());
        foreach (['type', 'code', 'label', 'status', 'version', 'publishedAt', 'archivedAt', 'validationRequestedAt', 'validationRequestedBy', 'validationReviewedAt', 'validationReviewedBy', 'validationFeedback'] as $field) {
            self::assertTrue($ficheMetadata->hasField($field));
        }
        self::assertFalse($ficheMetadata->hasField('completeness'));
        $codeMapping = $ficheMetadata->getFieldMapping('code');
        // Insérable depuis la reprise legacy (code = Id syspad fourni à l'import),
        // le trigger SQL n'attribue le compteur que si aucun code n'est fourni.
        self::assertFalse($codeMapping->notInsertable ?? false);
        self::assertTrue($codeMapping->notUpdatable ?? false);
        self::assertSame(ClassMetadata::GENERATED_INSERT, $codeMapping->generated);
        self::assertSame(
            ['code'],
            $ficheMetadata->table['uniqueConstraints']['UNIQ_PIM_FICHE_CODE']['columns'] ?? null,
        );
        foreach (['localisation', 'attributValues'] as $association) {
            self::assertTrue($ficheMetadata->hasAssociation($association));
        }

        self::assertCount(37, $entityManager->getClassMetadata(LieuAdministratif::class)->getFieldNames());
        self::assertCount(22, $entityManager->getClassMetadata(LieuTarification::class)->getFieldNames());

        // Nine source fields, three normalized/indexed fields, id and timestamps.
        self::assertCount(15, $localisationMetadata->getFieldNames());
        foreach (['pays', 'countryCode', 'region', 'departement', 'ruePostale', 'codePostal', 'ville', 'villeNormalisee', 'addressFingerprint', 'arrondissement', 'latitude', 'longitude'] as $field) {
            self::assertTrue($localisationMetadata->hasField($field));
        }

        self::assertSame('dam_media_asset', $mediaMetadata->getTableName());
        foreach (['originalStorageKey', 'originalFilename', 'mimeType', 'sizeBytes', 'checksum', 'kind', 'status'] as $field) {
            self::assertTrue($mediaMetadata->hasField($field));
        }
    }
}
