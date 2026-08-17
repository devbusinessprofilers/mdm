<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Legacy\LegacyActiviteLovMapper;
use App\Pim\Import\Legacy\LegacyActiviteRowMapper;
use App\Pim\Import\Legacy\LegacyLovMapper;
use PHPUnit\Framework\TestCase;

final class LegacyActiviteRowMapperTest extends TestCase
{
    private LegacyActiviteRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LegacyActiviteRowMapper(new LegacyLovMapper(), new LegacyActiviteLovMapper());
    }

    public function testMobileActivityIsFullyMapped(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '4200',
            'Publié / non publié' => 'true',
            'Nom Français' => 'Olympiades en équipe',
            'Gamme' => 'Idée',
            "Type d'activités" => '["Sportives","Nature"]',
            "Objectifs de l'activité" => "Cohésion d'équipe\nMotiver\nChallenger\nInconnu",
            'Description générale' => 'Grand jeu par équipes.',
            'Nombre participants minimum activité' => '10',
            'Nombre participants maximum activité' => '200',
            'Temps minimum' => '1:30',
            'Temps maximum' => '3:00',
            'Tarifs activité à partir de' => '45',
            'Les plus (sous forme de Bullet point) - 1' => 'Clé en main',
            'Les plus (sous forme de Bullet point) - 2' => 'Animateur dédié',
            "Rayon d'action (Région)" => "Toute la France\nÎle-de-France",
            "Rayon d'action géographique (département)" => "Paris\nGironde",
            'Lien youtube' => 'https://youtube.com/watch?v=x',
            'Pays' => 'France',
            'Ville' => 'Paris',
            'Photos' => '{"master":["x/master/1.jpg"]}',
        ]));

        self::assertSame(4200, $mapped->syspadId);
        self::assertTrue($mapped->publish);
        self::assertSame('Idée', $mapped->gamme);

        $activite = $mapped->activite;
        self::assertSame(4200, $activite->fiche()->code());
        self::assertSame('Olympiades en équipe', $activite->fiche()->label());
        self::assertSame(['TA_SPORTIVE_LUDIQUE', 'TA_NATURE_RSE'], $activite->thematiques());
        self::assertSame(['OBJECTIF_SEMINAIRE_1', 'OBJECTIF_SEMINAIRE_3', 'OBJECTIF_SEMINAIRE_8'], $activite->objectifs());
        self::assertContains('objectif_non_mappe', $mapped->warnings);
        self::assertSame('Grand jeu par équipes.', $activite->descriptionGenerale());
        self::assertSame(10, $activite->participantsMin());
        self::assertSame(200, $activite->participantsMax());
        self::assertSame(90, $activite->dureeMinMinutes());
        self::assertSame(180, $activite->dureeMaxMinutes());
        self::assertSame('45', $activite->tarifParPersonne());
        self::assertSame(['Clé en main', 'Animateur dédié'], $activite->plus());
        self::assertSame(ModeInterventionActivite::Mobile, $activite->modeIntervention());
        self::assertTrue($activite->touteFrance());
        self::assertSame(['Île-de-France'], $activite->regionsMobiles());
        self::assertSame(['Paris', 'Gironde'], $activite->departementsMobiles());
        self::assertSame('FR', $activite->localisation()?->countryCode());
        self::assertNotNull($mapped->photosJson);
    }

    public function testInsolitesActivityTypeIsMapped(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '4250',
            'Nom Français' => 'Expérience insolite',
            'Gamme' => 'Idée',
            "Type d'activités" => '["Insolites"]',
            'Ville' => 'Paris',
        ]));

        self::assertSame(['TA_INSOLITE'], $mapped->activite->thematiques());
        self::assertNotContains('type_activite_non_mappe', $mapped->warnings);
    }

    public function testFixedActivityAndZeroValues(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '4300',
            'Publié / non publié' => 'false',
            'Nom Français' => 'Atelier cuisine',
            'Gamme' => 'Idée',
            "Type d'activités" => '["Type d\'activité inconnu du catalogue"]',
            'Temps minimum' => '0:00',
            'Tarifs activité à partir de' => '0',
            'Nombre participants minimum activité' => '0',
            'Ville' => 'Lyon',
        ]));

        $activite = $mapped->activite;
        self::assertFalse($mapped->publish);
        self::assertSame([], $activite->thematiques());
        self::assertContains('type_activite_non_mappe', $mapped->warnings);
        self::assertNull($activite->dureeMinMinutes());
        self::assertNull($activite->tarifParPersonne());
        self::assertNull($activite->participantsMin());
        self::assertSame(ModeInterventionActivite::Fixe, $activite->modeIntervention());
        self::assertFalse($activite->touteFrance());
    }

    public function testMissingLocationAndRadiusYieldsWarning(): void
    {
        $mapped = $this->mapper->map($this->row([
            'Id syspad' => '4400',
            'Nom Français' => 'Sans localisation',
            'Gamme' => 'Idée',
        ]));
        self::assertNull($mapped->activite->modeIntervention());
        self::assertContains('mode_intervention_indetermine', $mapped->warnings);
    }

    public function testSupportsOnlyIdee(): void
    {
        self::assertTrue($this->mapper->supports($this->row(['Gamme' => 'Idée'])));
        self::assertFalse($this->mapper->supports($this->row(['Gamme' => 'Hôtel'])));
    }

    /** @param array<string, string> $cells */
    private function row(array $cells): RawCsvRow
    {
        return new RawCsvRow(1, $cells);
    }
}
