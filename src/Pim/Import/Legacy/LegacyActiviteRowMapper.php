<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Import\Dto\RawCsvRow;

/**
 * Hydrate une Activite depuis une ligne CSV production (Gamme = « Idée »).
 * Mêmes principes que LegacyLieuRowMapper : warnings agrégés, seuls un Id
 * syspad ou un nom invalides rejettent la ligne.
 */
final readonly class LegacyActiviteRowMapper
{
    public const SUPPORTED_GAMME = 'Idée';
    /** Colonne CSV portant l'Id syspad (pivot d'idempotence des commandes d'import). */
    public const SYSPAD_COLUMN = 'Id syspad';

    private const TOUTE_LA_FRANCE = 'toute la france';

    public function __construct(
        private LegacyLovMapper $lovMapper,
        private LegacyActiviteLovMapper $activiteLovMapper,
    ) {}

    public function supports(RawCsvRow $row): bool
    {
        return self::SUPPORTED_GAMME === $row->cell('Gamme');
    }

    public function map(RawCsvRow $row): LegacyMappedActivite
    {
        $warnings = [];
        $syspadId = $row->cell(self::SYSPAD_COLUMN);
        if (1 !== preg_match('/^\d+$/', $syspadId)) {
            throw new \DomainException('Id syspad manquant ou non numérique.');
        }
        $label = $row->cell('Nom Français');
        if ('' === $label) {
            throw new \DomainException('Nom Français vide.');
        }

        $activite = new Activite();
        $activite->fiche()->assignImportedCode((int) $syspadId);
        $activite->changeLabel(mb_substr($label, 0, 255));
        $activite->fiche()->changeTelephone(self::nullable($row->cell('Téléphone')));
        // Règle legacy : colonne « Tag » vide = partenaire BP (icône marketplace).
        $activite->fiche()->changePartenaireBp('' === $row->cell('Tag'));

        $thematiques = $this->activiteLovMapper->thematiques($row->cell("Type d'activités"));
        array_push($warnings, ...$thematiques['warnings']);
        $activite->changeThematiques($thematiques['thematiques']);

        $objectifs = $this->activiteLovMapper->objectifs($row->cell("Objectifs de l'activité"));
        array_push($warnings, ...$objectifs['warnings']);
        if ([] !== $objectifs['codes']) {
            $activite->changeObjectifs($objectifs['codes']);
        }

        $description = $row->cell('Description générale');
        $activite->changeDescriptionGenerale('' === $description ? null : $description);
        $activite->changeYoutubeUrl(self::nullable(mb_substr($row->cell('Lien youtube'), 0, 255)));

        $activite->changeParticipantsMin(self::intOrNull($row->cell('Nombre participants minimum activité')));
        $activite->changeParticipantsMax(self::intOrNull($row->cell('Nombre participants maximum activité')));
        $activite->changeDureeMinMinutes(self::durationMinutes($row->cell('Temps minimum')));
        $activite->changeDureeMaxMinutes(self::durationMinutes($row->cell('Temps maximum')));

        $tarif = str_replace(',', '.', $row->cell('Tarifs activité à partir de'));
        if (is_numeric($tarif) && (float) $tarif > 0) {
            $activite->changeTarifParPersonne($tarif);
        }

        $plus = [];
        foreach ([1, 2, 3, 4, 5] as $index) {
            $value = $row->cell('Les plus (sous forme de Bullet point) - '.$index);
            if ('' !== $value) {
                $plus[] = mb_substr($value, 0, 255);
            }
        }
        if (count($plus) > 4) {
            // L'entité ne conserve que 4 atouts.
            $warnings[] = 'plus_excedentaires';
        }
        if ([] !== $plus) {
            $activite->changePlus($plus);
        }

        $this->intervention($activite, $row, $warnings);
        $this->localisation($activite, $row, $warnings);

        $photosJson = $row->cell('Photos');

        return new LegacyMappedActivite(
            (int) $syspadId,
            $activite,
            'true' === $row->cell('Publié / non publié'),
            $row->cell('Gamme'),
            '' === $photosJson ? null : $photosJson,
            $warnings,
        );
    }

    /** @param list<string> $warnings */
    private function intervention(Activite $activite, RawCsvRow $row, array &$warnings): void
    {
        $regions = self::lines($row->cell("Rayon d'action (Région)"));
        $departements = self::lines($row->cell("Rayon d'action géographique (département)"));
        $touteFrance = [] !== array_filter($regions, static fn (string $region): bool => self::TOUTE_LA_FRANCE === mb_strtolower($region));
        $regions = array_values(array_filter($regions, static fn (string $region): bool => self::TOUTE_LA_FRANCE !== mb_strtolower($region)));

        if ($touteFrance || [] !== $regions || [] !== $departements) {
            $activite->changeModeIntervention(ModeInterventionActivite::Mobile);
            $activite->changeTouteFrance($touteFrance);
            $activite->changeRegionsMobiles($regions);
            $activite->changeDepartementsMobiles($departements);

            return;
        }
        if ('' !== $row->cell('Ville')) {
            $activite->changeModeIntervention(ModeInterventionActivite::Fixe);

            return;
        }
        $warnings[] = 'mode_intervention_indetermine';
    }

    /** @param list<string> $warnings */
    private function localisation(Activite $activite, RawCsvRow $row, array &$warnings): void
    {
        $cells = ['Pays', 'Région', 'Département', 'Adresse postale', 'Code postal', 'Ville', 'Latitude - GPS', 'Longitude - GPS'];
        if ([] === array_filter($cells, static fn (string $cell): bool => '' !== $row->cell($cell))) {
            return;
        }
        $localisation = new Localisation();
        $pays = $row->cell('Pays');
        $localisation->changePays(self::nullable($pays));
        if ('' !== $pays) {
            $code = $this->lovMapper->countryCode($pays);
            if (null === $code) {
                $warnings[] = 'pays_code_inconnu';
            } else {
                $localisation->changeCountryCode($code);
            }
        }
        $localisation->changeRegion(self::nullable($row->cell('Région')));
        $localisation->changeDepartement(self::nullable($row->cell('Département')));
        $localisation->changeRuePostale(self::nullable($row->cell('Adresse postale')));
        $localisation->changeCodePostal(self::nullable($row->cell('Code postal')));
        $localisation->changeVille(self::nullable($row->cell('Ville')));
        foreach ([['Latitude - GPS', 'changeLatitude'], ['Longitude - GPS', 'changeLongitude']] as [$column, $setter]) {
            $value = $row->cell($column);
            if ('' === $value || !is_numeric($value)) {
                if ('' !== $value) {
                    $warnings[] = 'gps_invalide';
                }
                continue;
            }
            try {
                $localisation->{$setter}(sprintf('%.7F', (float) $value));
            } catch (\InvalidArgumentException) {
                $warnings[] = 'gps_invalide';
            }
        }
        $activite->changeLocalisation($localisation);
    }

    /** @return list<string> */
    private static function lines(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R+/', $value) ?: []),
            static fn (string $line): bool => '' !== $line,
        ));
    }

    /** Convertit une durée « H:MM » en minutes (0:00 et valeurs vides → null). */
    private static function durationMinutes(string $value): ?int
    {
        if (1 !== preg_match('/^(\d{1,3}):([0-5]\d)$/', trim($value), $matches)) {
            return null;
        }
        $minutes = ((int) $matches[1]) * 60 + (int) $matches[2];

        return $minutes > 0 ? $minutes : null;
    }

    private static function nullable(string $value): ?string
    {
        return '' === $value ? null : $value;
    }

    private static function intOrNull(string $value): ?int
    {
        $value = trim($value);
        if ('' === $value || !is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
