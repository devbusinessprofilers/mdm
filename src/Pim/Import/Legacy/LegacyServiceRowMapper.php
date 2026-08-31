<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Import\Dto\RawCsvRow;

/**
 * Hydrate un ServiceEvenementiel depuis une ligne CSV production
 * (Gamme = « Prestataires de service »). Mêmes principes que les mappers
 * Lieu/Activité : warnings agrégés, seuls un Id syspad ou un nom invalides
 * rejettent la ligne.
 */
final readonly class LegacyServiceRowMapper
{
    public const SUPPORTED_GAMME = 'Prestataires de service';
    /** Colonne CSV portant l'Id syspad (pivot d'idempotence des commandes d'import). */
    public const SYSPAD_COLUMN = 'Id syspad';

    public function __construct(
        private LegacyLovMapper $lovMapper,
        private LegacyServiceLovMapper $serviceLovMapper,
    ) {}

    public function supports(RawCsvRow $row): bool
    {
        return self::SUPPORTED_GAMME === $row->cell('Gamme');
    }

    public function map(RawCsvRow $row): LegacyMappedService
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

        $service = new ServiceEvenementiel();
        $service->fiche()->assignImportedCode((int) $syspadId);
        $service->changeLabel(mb_substr($label, 0, 255));
        // Règle legacy : colonne « Tag » vide = partenaire BP (icône marketplace).
        $service->fiche()->changePartenaireBp('' === $row->cell('Tag'));

        $prestations = $this->serviceLovMapper->prestations($row->cell('Type de prestataire'));
        array_push($warnings, ...$prestations['warnings']);
        if ([] !== $prestations['codes']) {
            $service->changePrestations($prestations['codes']);
        }
        $sousPrestations = $this->serviceLovMapper->sousPrestations($row->cell('Type de prestataire'));
        if ([] !== $sousPrestations) {
            $service->changeSousPrestations($sousPrestations);
        }

        $categorie = $row->cell('Categorie');
        if ('RSE' === $categorie) {
            $service->changeDemarcheRse(true);
        } elseif ('ESAT / STPA' === $categorie) {
            $service->changePrestataireEsat(true);
        } elseif ('' !== $categorie) {
            $warnings[] = 'categorie_non_mappee';
        }

        $description = $row->cell('Description générale');
        $service->changeDescriptionGenerale('' === $description ? null : $description);
        $service->changeYoutubeUrl(self::nullable(mb_substr($row->cell('Lien youtube'), 0, 255)));

        $tarif = str_replace(',', '.', $row->cell('Tarifs activité à partir de'));
        if (is_numeric($tarif) && (float) $tarif > 0) {
            $service->changeTarifParPrestation((float) $tarif);
        }

        if ('' !== $row->cell('Les plus (sous forme de Bullet point) - 1')) {
            $warnings[] = 'atouts_non_mappes';
        }

        $this->intervention($service, $row, $warnings);
        $this->localisation($service, $row, $warnings);

        $photosJson = $row->cell('Photos');

        return new LegacyMappedService(
            (int) $syspadId,
            $service,
            'true' === $row->cell('Publié / non publié'),
            $row->cell('Gamme'),
            '' === $photosJson ? null : $photosJson,
            $warnings,
        );
    }

    /** @param list<string> $warnings */
    private function intervention(ServiceEvenementiel $service, RawCsvRow $row, array &$warnings): void
    {
        $regions = self::lines($row->cell("Rayon d'action (Région)"));
        $departements = self::lines($row->cell("Rayon d'action géographique (département)"));

        if ([] !== $regions || [] !== $departements) {
            // Pas de flag « toute la France » sur les services : la valeur
            // legacy est conservée telle quelle dans les régions mobiles.
            $service->changeModeIntervention(ModeInterventionService::Mobile);
            $service->changeRegionsMobiles($regions);
            $service->changeDepartementsMobiles($departements);

            return;
        }
        if ('' !== $row->cell('Ville')) {
            $service->changeModeIntervention(ModeInterventionService::Fixe);

            return;
        }
        $warnings[] = 'mode_intervention_indetermine';
    }

    /** @param list<string> $warnings */
    private function localisation(ServiceEvenementiel $service, RawCsvRow $row, array &$warnings): void
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
        $service->changeLocalisation($localisation);
    }

    /** @return list<string> */
    private static function lines(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R+/', $value) ?: []),
            static fn (string $line): bool => '' !== $line,
        ));
    }

    private static function nullable(string $value): ?string
    {
        return '' === $value ? null : $value;
    }
}
