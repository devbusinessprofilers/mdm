<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Service\DataTourisme\DataTourismeIndex;
use App\Pim\Service\DataTourisme\DataTourismePoi;
use App\Pim\Service\DataTourismeVerifier;
use PHPUnit\Framework\TestCase;

final class DataTourismeVerifierTest extends TestCase
{
    public function testProposeDescriptionEtEquipementsPourUnLieu(): void
    {
        $lieu = self::lieu('Château du Test', '37000');
        $index = DataTourismeIndex::depuis([new DataTourismePoi(
            nom: 'Château du Test',
            codePostal: '37000',
            description: 'Un château d\'exception au cœur de la Touraine.',
            features: ['spa', 'piscine extérieure', 'parking'],
        )]);

        $parChamp = self::parChamp((new DataTourismeVerifier())->analyserLieu($lieu, $index));

        self::assertArrayHasKey('lieu_desc_generale', $parChamp);
        self::assertSame('Un château d\'exception au cœur de la Touraine.', $parChamp['lieu_desc_generale']->payload['text']);
        self::assertArrayHasKey('lieu_lov_BIEN_ETRE', $parChamp);
        self::assertContains('BIEN_ETRE_4', $parChamp['lieu_lov_BIEN_ETRE']->payload['codes']);
        self::assertContains('BIEN_ETRE_3', $parChamp['lieu_lov_BIEN_ETRE']->payload['codes']);
        self::assertArrayHasKey('lieu_lov_INSTALLATION', $parChamp);
        self::assertSame(['INSTALLATION_10'], $parChamp['lieu_lov_INSTALLATION']->payload['codes']);
    }

    public function testProposeLaDescriptionDUneActivite(): void
    {
        $activite = new Activite();
        $activite->changeLabel('Descente en kayak');
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCodePostal('37000');
        $activite->changeLocalisation($localisation);

        $index = DataTourismeIndex::depuis([new DataTourismePoi(
            nom: 'Descente en kayak',
            codePostal: '37000',
            description: 'Une descente sportive sur le Cher.',
        )]);

        $propositions = (new DataTourismeVerifier())->analyserActivite($activite, $index);

        self::assertCount(1, $propositions);
        self::assertSame('activite_desc_generale', $propositions[0]->champ);
        self::assertSame('Une descente sportive sur le Cher.', $propositions[0]->payload['text']);
    }

    public function testAucunRapprochementSurCodePostalDifferent(): void
    {
        $lieu = self::lieu('Château du Test', '75000');
        $index = DataTourismeIndex::depuis([new DataTourismePoi(nom: 'Château du Test', codePostal: '37000', description: 'x')]);

        self::assertSame([], (new DataTourismeVerifier())->analyserLieu($lieu, $index));
    }

    /**
     * @param list<\App\Pim\Service\SuggestionProposee> $propositions
     *
     * @return array<string, \App\Pim\Service\SuggestionProposee>
     */
    private static function parChamp(array $propositions): array
    {
        $parChamp = [];
        foreach ($propositions as $proposition) {
            $parChamp[$proposition->champ] = $proposition;
        }

        return $parChamp;
    }

    private static function lieu(string $label, string $codePostal): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCodePostal($codePostal);
        $lieu->changeLocalisation($localisation);

        return $lieu;
    }
}
