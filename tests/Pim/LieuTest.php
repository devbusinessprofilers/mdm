<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\StatutFiche;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LieuTest extends TestCase
{
    public function testDraftHasTechnicalIdentityAndDictionaryDefaults(): void
    {
        $lieu = new Lieu();

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $lieu->id());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('sera attribué lors de son enregistrement');
        $lieu->code();
    }

    public function testDraftHasDictionaryDefaults(): void
    {
        $lieu = new Lieu();

        self::assertNull($lieu->label());
        self::assertFalse($lieu->generaleEtabRp());
        self::assertFalse($lieu->dispoLieuPrivatisable());
        self::assertFalse($lieu->pmrAcces());
        self::assertTrue($lieu->chambreHebergement());
        self::assertTrue($lieu->salleReunionExist());
        self::assertSame(StatutFiche::EnCours, $lieu->fiche()->status());
        self::assertSame([], $lieu->generaleTypologie());
    }

    public function testBusinessFieldsAndLocalisationCanBeChanged(): void
    {
        $lieu = new Lieu();
        $localisation = new Localisation();
        $previousUpdate = $lieu->updatedAt();
        usleep(1_000);

        $lieu->changeLabel(' Palais des congrès ');
        $lieu->changeLocalisation($localisation);
        $lieu->administratif()->changeInfoLegaleNom(' Business Profilers SAS ');
        $lieu->tarification()->changeSeminaireJourneeJourneeEtude('125.50');

        self::assertSame('Palais des congrès', $lieu->label());
        self::assertSame($localisation, $lieu->localisation());
        self::assertSame('Business Profilers SAS', $lieu->administratif()->infoLegaleNom());
        self::assertSame('125.50', $lieu->tarification()->seminaireJourneeJourneeEtude());
        self::assertGreaterThan($previousUpdate, $lieu->updatedAt());
    }

    public function testLovCodesAreValidatedAndDeduplicated(): void
    {
        $lieu = new Lieu();

        $lieu->changeGeneraleTypologie([
            'GENERALE_TYPOLOGIE_1',
            'GENERALE_TYPOLOGIE_1',
            'GENERALE_TYPOLOGIE_40',
        ]);
        $lieu->changeMiceStatut('MICE_STATUT_4');

        self::assertSame(
            ['GENERALE_TYPOLOGIE_1', 'GENERALE_TYPOLOGIE_40'],
            $lieu->generaleTypologie(),
        );
        self::assertSame('MICE_STATUT_4', $lieu->miceStatut());
    }

    public function testUnknownLovCodeIsRejected(): void
    {
        $lieu = new Lieu();

        $this->expectException(\InvalidArgumentException::class);
        $lieu->changeMiceStatut('UNKNOWN');
    }

    public function testNonNumericTariffIsRejected(): void
    {
        $lieu = new Lieu();

        $this->expectException(\InvalidArgumentException::class);
        $lieu->tarification()->changeSeminaireJourneeJourneeEtude('sur devis');
    }

    /** @return iterable<string, array{string, string}> */
    public static function administrativeStringFields(): iterable
    {
        foreach ([
            'InfoLegaleNom', 'InfoLegaleFormeJuridique', 'InfoLegaleRuePostal', 'InfoLegaleAdresse2',
            'InfoLegaleCodePostal', 'InfoLegaleVille', 'InforLegalePays', 'InfoLegaleSiret',
            'InfoLegaleNumTva', 'InfoLegaleTypeDeProcedureJudiciaire', 'AdresseFacturationNom',
            'AdresseFacturationRuePostal', 'AdresseFacturationCodePostal', 'AdresseFacturationVille',
            'AdresseFacturationPays', 'AdresseFacturationNumTva', 'ContactFacturationNom',
            'ContactFacturationPrenom', 'ContactFacturationEmail', 'ContactFacturationTelephone',
            'ModePaiementBic', 'ModePaiementIban', 'AffacturageBic', 'AffacturageIban',
            'CommissionApplicable', 'ConvPartSigneeLe', 'ConvPartTaux', 'SignataireEmail',
            'SignatairePrenom', 'SignataireNom',
        ] as $suffix) {
            yield $suffix => ['change'.$suffix, lcfirst($suffix)];
        }
    }

    #[DataProvider('administrativeStringFields')]
    public function testAdministrativeStringSettersAreTypedAndNormalizeValues(string $setter, string $getter): void
    {
        $block = (new Lieu())->administratif();

        $block->{$setter}(' value ');
        self::assertSame('value', $block->{$getter}());
        $block->{$setter}('  ');
        self::assertNull($block->{$getter}());
    }

    /** @return iterable<string, array{string, string}> */
    public static function administrativeBooleanFields(): iterable
    {
        foreach (['InfoLegaleAssujettiTva', 'ModePaiementAcceptDeductionCom', 'ModePaiementAffacturage'] as $suffix) {
            yield $suffix => ['change'.$suffix, lcfirst($suffix)];
        }
    }

    #[DataProvider('administrativeBooleanFields')]
    public function testAdministrativeBooleanSettersAreTyped(string $setter, string $getter): void
    {
        $block = (new Lieu())->administratif();

        $block->{$setter}(true);
        self::assertTrue($block->{$getter}());
        $block->{$setter}(null);
        self::assertNull($block->{$getter}());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function administrativeLovFields(): iterable
    {
        yield 'VAT' => ['changeInfoLegaleTva', 'infoLegaleTva', 'INFO_LEGALE_TVA_1'];
        yield 'deposit' => ['changeCondPaieAccSignature', 'condPaieAccSignature', 'COND_PAIE_ACC_SIGNATURE_1'];
        yield 'cancellation' => ['changeCondPaieAnnSignature', 'condPaieAnnSignature', 'COND_PAIE_ANN_SIGNATURE_1'];
        yield 'balance date' => ['changeDatePaiementSold', 'datePaiementSold', 'DATE_PAIEMENT_SOLD_1'];
    }

    #[DataProvider('administrativeLovFields')]
    public function testAdministrativeLovSettersValidateAndNormalize(string $setter, string $getter, string $validValue): void
    {
        $block = (new Lieu())->administratif();

        $block->{$setter}($validValue);
        self::assertSame($validValue, $block->{$getter}());
        $block->{$setter}(null);
        self::assertNull($block->{$getter}());
    }

    #[DataProvider('administrativeLovFields')]
    public function testAdministrativeLovSettersRejectInvalidValues(string $setter, string $_getter, string $_validValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Lieu())->administratif()->{$setter}('UNKNOWN');
    }

    /** @return iterable<string, array{string, string}> */
    public static function tariffFields(): iterable
    {
        foreach ([
            'SeminaireJourneeDemiJourneeEtude', 'SeminaireJourneeJourneeEtude',
            'SeminaireJourneeDemiJourneeEtudeCocktail', 'SeminaireJourneeJourneeEtudeCocktail',
            'SeminaireNuiteeSemiResidentiel', 'SeminaireNuiteeResidentiel',
            'SeminaireNuiteeResidentielAllInclusive', 'LocSalleSeulDemiJournee', 'LocSalleSeulJournee',
            'LocSalleSeulSoiree', 'CsCocktailDejeunatoire10Pers', 'CsCocktailDinatoire',
            'CsSoireeDansante', 'CsSoireeDinerAssis', 'TarifRestDejeunerAssis', 'TarifRestDinerAssis',
            'TarifRestOptVin', 'TarifRestOptAlcool', 'TarifRestForfaitPersonalise',
            'HebergGroupTarifChambreSingle', 'HebergGroupTarifChambreTwin', 'HebergGroupTarifChambreDouble',
        ] as $suffix) {
            yield $suffix => ['change'.$suffix, lcfirst($suffix)];
        }
    }

    #[DataProvider('tariffFields')]
    public function testTariffSettersNormalizeDecimals(string $setter, string $getter): void
    {
        $block = (new Lieu())->tarification();

        $block->{$setter}(' 125.50 ');
        self::assertSame('125.50', $block->{$getter}());
        $block->{$setter}(' ');
        self::assertNull($block->{$getter}());
    }

    #[DataProvider('tariffFields')]
    public function testTariffSettersRejectInvalidDecimals(string $setter, string $_getter): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Lieu())->tarification()->{$setter}('sur devis');
    }

    public function testAdministrativeAndTariffChangesPropagateTimestamps(): void
    {
        $lieu = new Lieu();
        $lieuUpdatedAt = $lieu->updatedAt();
        $ficheUpdatedAt = $lieu->fiche()->updatedAt();
        usleep(1_000);

        // Le bloc administratif est porté par la fiche (toutes gammes) : c'est
        // l'horodatage de la fiche qui bouge — celui que lisent la synchro et
        // la fraîcheur des scans.
        $lieu->administratif()->changeInfoLegaleNom('Business Profilers');
        self::assertSame($lieu->fiche(), $lieu->administratif()->fiche());
        self::assertGreaterThan($ficheUpdatedAt, $lieu->fiche()->updatedAt());

        $lieuUpdatedAt = $lieu->updatedAt();
        $ficheUpdatedAt = $lieu->fiche()->updatedAt();
        usleep(1_000);
        $lieu->tarification()->changeSeminaireJourneeJourneeEtude('125.50');
        self::assertGreaterThan($lieuUpdatedAt, $lieu->updatedAt());
        self::assertGreaterThan($ficheUpdatedAt, $lieu->fiche()->updatedAt());
    }

    public function testRepeatedBusinessDataBelongsToLieuAggregate(): void
    {
        $lieu = new Lieu();
        $salle = new Salle();
        $periode = new PeriodeFermeture();
        $acces = new AccesLieu();
        $ressource = new RessourceLieu();

        $lieu->addSalle($salle);
        $lieu->addPeriodeFermeture($periode);
        $lieu->addAcces($acces);
        $lieu->addRessource($ressource);

        self::assertSame($lieu, $salle->lieu());
        self::assertSame($lieu, $periode->lieu());
        self::assertSame($lieu, $acces->lieu());
        self::assertSame($lieu, $ressource->lieu());

        $lieu->removeSalle($salle);
        self::assertNull($salle->lieu());
        self::assertCount(0, $lieu->salles());
    }
}
