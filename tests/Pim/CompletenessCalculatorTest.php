<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Completeness\CompletenessCalculator;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Completeness\CompletenessValueAccessor;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\CompletenessFormula;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;

final class CompletenessCalculatorTest extends TestCase
{
    public function testLengthRatioAwardsEightyPercentOfTheWeight(): void
    {
        $lieu = new Lieu();
        $lieu->changeDescGenerale(str_repeat('a', 80));
        $configuration = new CompletenessFieldConfiguration(TypeFiche::Lieu, 'DESC_GENERALE', 'Description');
        $configuration->configure(CompletenessFormula::LengthRatio, 1, 100, true, true, true, true, true);

        $scores = $this->calculator()->calculate($lieu, TypeFiche::Lieu, [$configuration]);

        self::assertSame(80, $scores->global);
        self::assertSame(80, $scores->marketplace);
        self::assertSame(80, $scores->providerPortal);
    }

    public function testFalseAndZeroAreFilledValues(): void
    {
        $service = new ServiceEvenementiel();
        $service->changePrestataireEsat(false);
        $configuration = new CompletenessFieldConfiguration(TypeFiche::ServiceEvenementiel, 'TYPE_PRESTATAIRE_ESAT', 'ESAT');

        self::assertSame(100, $this->calculator()->calculate($service, TypeFiche::ServiceEvenementiel, [$configuration])->global);
    }

    public function testRepeatedFieldUsesTheAverageOfOccurrences(): void
    {
        $lieu = new Lieu();
        for ($i = 0; $i < 5; ++$i) {
            $room = new Salle();
            if ($i < 4) { $room->changeNom('Salle '.($i + 1)); }
            $lieu->addSalle($room);
        }
        $configuration = new CompletenessFieldConfiguration(TypeFiche::Lieu, 'CONFIG_NOM', 'Nom de salle');

        self::assertSame(80, $this->calculator()->calculate($lieu, TypeFiche::Lieu, [$configuration])->global);
    }

    public function testAFieldExcludedByItsBusinessConditionDoesNotEnterTheDenominator(): void
    {
        $lieu = new Lieu();
        $lieu->changeChambreHebergement(false);
        $configuration = new CompletenessFieldConfiguration(TypeFiche::Lieu, 'CHAMBRE_NB_TOTAL', 'Chambres');

        self::assertSame(0, $this->calculator()->calculate($lieu, TypeFiche::Lieu, [$configuration])->global);
    }

    public function testLengthFormulaWithoutAnyTargetIsRejected(): void
    {
        $service = new ServiceEvenementiel();
        $configuration = new CompletenessFieldConfiguration(TypeFiche::ServiceEvenementiel, 'GENERAL_DESC', 'Description');
        $configuration->configure(CompletenessFormula::LengthRatio, 1, null, true, true, true, true, true);

        $this->expectException(\DomainException::class);
        $this->calculator()->calculate($service, TypeFiche::ServiceEvenementiel, [$configuration]);
    }

    public function testPhotoOverrideUsesOnlyTheEligibilityResult(): void
    {
        $lieu = new Lieu();
        $configuration = new CompletenessFieldConfiguration(TypeFiche::Lieu, 'PHOTO', 'Photos');

        self::assertSame(0, $this->calculator()->calculate($lieu, TypeFiche::Lieu, [$configuration], ['PHOTO' => null])->global);
        self::assertSame(100, $this->calculator()->calculate($lieu, TypeFiche::Lieu, [$configuration], ['PHOTO' => true])->global);
    }

    public function testUnknownLovValueIsTreatedAsMissingInsteadOfStoppingTheCalculation(): void
    {
        $accessor = new CompletenessValueAccessor();
        $source = new class {
            public function invalidValue(): string
            {
                throw new \UnexpectedValueException('Identifiant LOV inconnu.');
            }
        };

        self::assertNull($accessor->read($source, 'invalidValue'));
    }

    private function calculator(): CompletenessCalculator
    {
        return new CompletenessCalculator(new CompletenessFieldCatalog(), new CompletenessValueAccessor());
    }
}
