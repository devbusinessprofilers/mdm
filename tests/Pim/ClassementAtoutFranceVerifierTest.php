<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Repository\ClassementAtoutFranceRepository;
use App\Pim\Service\ClassementAtoutFranceVerifier;
use PHPUnit\Framework\TestCase;

final class ClassementAtoutFranceVerifierTest extends TestCase
{
    public function testUnHotelClasseProposeTypologieEtChambres(): void
    {
        $propositions = $this->verifier([
            ['nom' => 'AUBERGE DU JEU DE PAUME', 'typeEtablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 5, 'nombreChambres' => 92],
            ['nom' => 'HOTEL SANS RAPPORT', 'typeEtablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 2, 'nombreChambres' => 10],
        ])->analyser(self::lieuFrancais('Auberge du Jeu de Paume'));

        $parChamp = [];
        foreach ($propositions as $proposition) {
            $parChamp[$proposition->champ] = $proposition;
        }
        // 5 étoiles → Hôtel 5 étoiles (GENERALE_TYPOLOGIE_4), score = similarité du nom.
        self::assertSame(['GENERALE_TYPOLOGIE_4'], $parChamp['lieu_lov_typologie']->payload['codes'] ?? null);
        self::assertGreaterThanOrEqual(0.9, $parChamp['lieu_lov_typologie']->score);
        self::assertSame(['int' => 92], $parChamp['lieu_chambre_nb_total']->payload ?? null);
    }

    public function testUneResidenceDeTourismeProposeSaTypologie(): void
    {
        $propositions = $this->verifier([
            ['nom' => 'RESIDENCE LES JARDINS', 'typeEtablissement' => 'RÉSIDENCE DE TOURISME', 'etoiles' => 3, 'nombreChambres' => null],
        ])->analyser(self::lieuFrancais('Résidence Les Jardins'));

        self::assertCount(1, $propositions);
        // Résidence / Appart'hôtel, quel que soit le nombre d'étoiles.
        self::assertSame(['GENERALE_TYPOLOGIE_42'], $propositions[0]->payload['codes'] ?? null);
    }

    public function testUnNomTropEloigneNeProposeRien(): void
    {
        $propositions = $this->verifier([
            ['nom' => 'CAMPING DES FLOTS BLEUS', 'typeEtablissement' => 'CAMPING', 'etoiles' => 4, 'nombreChambres' => null],
        ])->analyser(self::lieuFrancais('Château de Chantilly'));

        self::assertSame([], $propositions);
    }

    public function testUnPalaceNestJamaisRetrogradeEnEtoiles(): void
    {
        $lieu = self::lieuFrancais('Auberge du Jeu de Paume');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_5']);

        $propositions = $this->verifier([
            ['nom' => 'AUBERGE DU JEU DE PAUME', 'typeEtablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 5, 'nombreChambres' => 92],
        ])->analyser($lieu);

        self::assertCount(1, $propositions);
        self::assertSame('lieu_chambre_nb_total', $propositions[0]->champ);
    }

    public function testUneGammeDEtoilesDivergenteEstProposeeEnRemplacement(): void
    {
        $lieu = self::lieuFrancais('Auberge du Jeu de Paume');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_2']);
        $lieu->changeChambreNbTotal(92);

        $propositions = $this->verifier([
            ['nom' => 'AUBERGE DU JEU DE PAUME', 'typeEtablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 5, 'nombreChambres' => 92],
        ])->analyser($lieu);

        self::assertCount(1, $propositions);
        // Conflit signalé : « Hôtel 3 étoiles » saisi vs 5 étoiles au
        // classement officiel — le code erroné part en retrait.
        self::assertSame('lieu_lov_typologie', $propositions[0]->champ);
        self::assertSame('Hôtel 3 étoiles', $propositions[0]->valeurActuelle);
        self::assertSame(['GENERALE_TYPOLOGIE_4'], $propositions[0]->payload['codes'] ?? null);
        self::assertSame(['GENERALE_TYPOLOGIE_2'], $propositions[0]->payload['retirer'] ?? null);
    }

    public function testUnNombreDeChambresDivergentEstProposeEnCorrection(): void
    {
        $lieu = self::lieuFrancais('Auberge du Jeu de Paume');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_4']);
        $lieu->changeChambreNbTotal(40);

        $propositions = $this->verifier([
            ['nom' => 'AUBERGE DU JEU DE PAUME', 'typeEtablissement' => 'HÔTEL DE TOURISME', 'etoiles' => 5, 'nombreChambres' => 92],
        ])->analyser($lieu);

        self::assertCount(1, $propositions);
        self::assertSame('lieu_chambre_nb_total', $propositions[0]->champ);
        self::assertSame('40', $propositions[0]->valeurActuelle);
        self::assertSame('92', $propositions[0]->valeurProposee);
    }

    public function testUnLieuEtrangerNeConsultePasLeReferentiel(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Grand Hotel Bruxelles');
        $localisation = new Localisation();
        $localisation->changePays('Belgique');
        $localisation->changeCodePostal('1000');
        $lieu->changeLocalisation($localisation);

        $classements = $this->createStub(ClassementAtoutFranceRepository::class);
        $classements->method('parCodePostal')->willReturnCallback(static function (): array {
            self::fail('Le référentiel Atout France ne couvre que la France.');
        });

        self::assertSame([], (new ClassementAtoutFranceVerifier($classements))->analyser($lieu));
    }

    /** @param list<array{nom: string, typeEtablissement: string, etoiles: int, nombreChambres: ?int}> $candidats */
    private function verifier(array $candidats): ClassementAtoutFranceVerifier
    {
        $classements = $this->createStub(ClassementAtoutFranceRepository::class);
        $classements->method('parCodePostal')->willReturn($candidats);

        return new ClassementAtoutFranceVerifier($classements);
    }

    private static function lieuFrancais(string $label): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCodePostal('60500');
        $localisation->changeVille('Chantilly');
        $lieu->changeLocalisation($localisation);

        return $lieu;
    }
}
