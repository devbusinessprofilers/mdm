<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Lov\LieuLovCatalog;

/**
 * Hydrate un Lieu complet depuis une ligne du CSV d'export production.
 * Les valeurs non mappables produisent des warnings (codes agrégés par la
 * commande), jamais des échecs de ligne — seuls un Id syspad ou un nom
 * invalides rejettent la ligne.
 */
final readonly class LegacyLieuRowMapper
{
    public const SUPPORTED_GAMMES = ['Hôtel', 'Lieu', 'Centre de congrès'];
    /** Colonne CSV portant l'Id syspad (pivot d'idempotence des commandes d'import). */
    public const SYSPAD_COLUMN = 'Id syspad';

    private const BIEN_ETRE_COLUMNS = [
        'Piscine Intérieure' => 'BIEN_ETRE_2',
        'Piscine Extérieure' => 'BIEN_ETRE_3',
        'Spa et centre de bien être' => 'BIEN_ETRE_4',
        'Sauna' => 'BIEN_ETRE_5',
        'Hammam' => 'BIEN_ETRE_6',
        'Jacuzzi / Bain à remous' => 'BIEN_ETRE_7',
        'Centre de remise en forme' => 'BIEN_ETRE_8',
        'Fitness' => 'BIEN_ETRE_9',
    ];

    public function __construct(private LegacyLovMapper $lovMapper) {}

    public function supports(RawCsvRow $row): bool
    {
        return in_array($row->cell('Gamme'), self::SUPPORTED_GAMMES, true);
    }

    public function map(RawCsvRow $row): LegacyMappedLieu
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
        $gamme = $row->cell('Gamme');

        $lieu = new Lieu();
        // Le code fiche reprend l'Id syspad legacy (pivot des reprises i18n/photos).
        $lieu->fiche()->assignImportedCode((int) $syspadId);
        $lieu->changeLabel(mb_substr($label, 0, 255));
        $lieu->fiche()->changeTelephone(self::nullable($row->cell('Téléphone')));
        // Règle legacy : colonne « Tag » vide = partenaire BP (icône marketplace).
        $lieu->fiche()->changePartenaireBp('' === $row->cell('Tag'));
        $lieu->changeGeneraleGammeLibelle($gamme);

        $typologies = $this->lovMapper->typologies($row->cell('Classification'), $gamme, $row->cell('Type de lieux'));
        array_push($warnings, ...$typologies['warnings']);
        if ([] !== $typologies['codes']) {
            $lieu->changeGeneraleTypologie($typologies['codes']);
        }

        $themes = $this->lovMapper->themes($row->cell('Thématique'));
        array_push($warnings, ...$themes['warnings']);
        if ([] !== $themes['cadreEnv']) {
            $lieu->changeTaCadreEnv($themes['cadreEnv']);
        }
        if ([] !== $themes['thematiques']) {
            $lieu->changeTaThematique($themes['thematiques']);
        }

        $this->chambres($lieu, $row, $typologies['hebergement']);
        $this->sallesAgregees($lieu, $row);
        $this->descriptions($lieu, $row, $warnings);
        $this->atouts($lieu, $row, $warnings);
        $this->loisirs($lieu, $row);
        $this->equipements($lieu, $row);
        $this->tarification($lieu, $row, $warnings);
        $this->localisation($lieu, $row, $warnings);
        $this->salles($lieu, $row, $warnings);
        $this->acces($lieu, $row);

        if ('true' === $row->cell('Accès PMR')) {
            $lieu->changePmrAcces(true);
        }
        if ('' !== $row->cell('Restauration / Gastronomie')) {
            $warnings[] = 'restauration_non_mappee';
        }

        $photosJson = $row->cell('Photos');

        return new LegacyMappedLieu(
            (int) $syspadId,
            $lieu,
            'true' === $row->cell('Publié / non publié'),
            $gamme,
            '' === $photosJson ? null : $photosJson,
            $warnings,
        );
    }

    private function chambres(Lieu $lieu, RawCsvRow $row, bool $hebergementType): void
    {
        $total = self::intOrNull($row->cell('Nombre de chambres'));
        $lieu->changeChambreNbTotal($total);
        $lieu->changeChambreNbTotalTwin(self::intOrNull($row->cell('dont nombre de chambres twin')));
        $lieu->changeChambreNbTotalSingle(self::intOrNull($row->cell('Nombre de chambres single')));
        $lieu->changeChambreCapaciteTotale(self::intOrNull($row->cell("Capacité d'accueil totale des personnes hebergés")));
        $lieu->changeChambreHebergement($hebergementType || ($total ?? 0) > 0);
    }

    private function sallesAgregees(Lieu $lieu, RawCsvRow $row): void
    {
        $nbSalles = $row->cell('Nombre de salle(s) de réunion(s)');
        if ('' !== $nbSalles) {
            $total = self::intOrNull($nbSalles);
            $lieu->changeSalleReunionNbTotal($total);
            $lieu->changeSalleReunionExist(($total ?? 0) > 0);
        }
        $lieu->changeSalleReunionCapaciteMaxCocktail(self::intOrNull($row->cell('Capacité de la plus grande salle en configuration cocktail (nb de pers)')));
        $lieu->changeSalleReunionCapaciteMaxTheatre(self::intOrNull($row->cell("Capacité d'accueil de la plus grande en configuration théâtre/conférence (nb pers)")));
        $lieu->changeSalleReunionSurfaceMaxReunion(self::intOrNull($row->cell('Surface de la plus grande salle de réunion  (en m²)')));
    }

    /** @param list<string> $warnings */
    private function descriptions(Lieu $lieu, RawCsvRow $row, array &$warnings): void
    {
        $lieu->changeDescGenerale(self::truncate($row->cell('Description générale'), 1000, $warnings, 'desc_generale_tronquee'));
        $lieu->changeChambreDescGenerale(self::truncate($row->cell('Hébergement'), 1000, $warnings, 'desc_hebergement_tronquee'));
        $seminaires = $row->cell('Salles de séminaires');
        $lieu->changeSalleReunionDescSalleSeminaire('' === $seminaires ? null : $seminaires);
    }

    /** @param list<string> $warnings */
    private function atouts(Lieu $lieu, RawCsvRow $row, array &$warnings): void
    {
        foreach ([1, 2, 3, 4, 5] as $index) {
            $value = $row->cell('Les plus (sous forme de Bullet point) - '.$index);
            $value = self::truncate($value, 35, $warnings, 'atout_tronque');
            $lieu->{'changeAtout'.$index}($value);
        }
    }

    private function loisirs(Lieu $lieu, RawCsvRow $row): void
    {
        $values = [];
        foreach (range(1, 8) as $index) {
            $value = $row->cell('Loisirs '.$index);
            if ('' !== $value) {
                $values[] = $value;
            }
        }
        if ('true' === $row->cell('Court de tennis')) {
            $values[] = 'Court de tennis';
        }
        if ('true' === $row->cell('Parcours de golf')) {
            $values[] = 'Parcours de golf';
        }
        if ([] !== $values) {
            $lieu->changeLoisirInterne(array_values(array_unique($values)));
        }
    }

    private function equipements(Lieu $lieu, RawCsvRow $row): void
    {
        $bienEtre = [];
        foreach (self::BIEN_ETRE_COLUMNS as $column => $code) {
            if ('true' === $row->cell($column)) {
                $bienEtre[] = $code;
            }
        }
        if ([] !== $bienEtre) {
            LieuLovCatalog::assertValidMany('BIEN_ETRE', $bienEtre);
            $lieu->changeBienEtre($bienEtre);
        }
        $technique = [];
        if ('true' === $row->cell('Wifi')) {
            $technique[] = 'TECHNIQUE_REUNION_1';
        }
        if ('true' === $row->cell('Fibre Optique')) {
            $technique[] = 'TECHNIQUE_REUNION_2';
        }
        if ('true' === $row->cell('Climatisation en salles de réunion')) {
            $technique[] = 'TECHNIQUE_REUNION_10';
        }
        if ([] !== $technique) {
            LieuLovCatalog::assertValidMany('TECHNIQUE_REUNION', $technique);
            $lieu->changeTechniqueReunion($technique);
        }
        if ('true' === $row->cell('Climatisation en chambre')) {
            LieuLovCatalog::assertValidMany('EQUIPEMENTS', ['EQUIPEMENTS_5']);
            $lieu->changeEquipements(['EQUIPEMENTS_5']);
        }
    }

    /**
     * @param list<string> $warnings
     * @param-out list<string> $warnings
     */
    private function tarification(Lieu $lieu, RawCsvRow $row, array &$warnings): void
    {
        $journee = self::priceOrNull($row->cell("Journée d'étude à partir de xx € /pers"));
        if (null !== $journee) {
            $lieu->tarification()->changeSeminaireJourneeJourneeEtude($journee);
        }
        $residentiel = self::priceOrNull($row->cell('Séminaire résidentiel à partir de xx €/pers'));
        if (null !== $residentiel) {
            $lieu->tarification()->changeSeminaireNuiteeResidentiel($residentiel);
        }
        // Bloc promo : les intitulés exacts des colonnes varient selon
        // l'export, résolution par motif (cf. docs/import-legacy.md).
        $offreSpeciale = self::cellParMotif($row, '/^offre\s*sp[eé]ciale/iu');
        if ('' !== $offreSpeciale) {
            $lieu->tarification()->changeOffreSpeciale($offreSpeciale);
        }
        $lieu->tarification()->changePromotionDebut(self::promoDate(self::cellParMotif($row, '/d[ée]but.*promo|promo.*d[ée]but/iu'), $warnings));
        $lieu->tarification()->changePromotionFin(self::promoDate(self::cellParMotif($row, '/fin.*promo|promo.*fin/iu'), $warnings));
    }

    /** Première cellule dont l'en-tête correspond au motif. */
    private static function cellParMotif(RawCsvRow $row, string $pattern): string
    {
        foreach ($row->cells as $header => $value) {
            if (1 === preg_match($pattern, (string) $header)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * Timestamp Unix (format marketplace) ou date, sinon warning.
     *
     * @param list<string> $warnings
     * @param-out list<string> $warnings
     */
    private static function promoDate(string $value, array &$warnings): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        if (1 === preg_match('/^\d{9,}$/', $value)) {
            return (new \DateTimeImmutable('@'.$value))->setTime(0, 0);
        }
        foreach (['!Y-m-d', '!d/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if (false !== $date) {
                return $date;
            }
        }
        $warnings[] = 'promotion_date_invalide';

        return null;
    }

    /** @param list<string> $warnings */
    private function localisation(Lieu $lieu, RawCsvRow $row, array &$warnings): void
    {
        $localisation = new Localisation();
        $pays = $row->cell('Pays');
        $localisation->changePays('' === $pays ? null : $pays);
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
        $lieu->changeLocalisation($localisation);
    }

    /** @param list<string> $warnings */
    private function salles(Lieu $lieu, RawCsvRow $row, array &$warnings): void
    {
        $json = $row->cell('Salle');
        if ('' === $json) {
            return;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $warnings[] = 'salles_json_invalide';

            return;
        }
        $position = 0;
        foreach ($decoded as $data) {
            if (!is_array($data)) {
                continue;
            }
            $nom = trim((string) ($data['Nom'] ?? ''));
            if ('' === $nom) {
                continue;
            }
            $salle = new Salle();
            $salle->changeNom(mb_substr($nom, 0, 255));
            $salle->changeSuperficie(self::intOrNull((string) ($data['Superficie Salle en m2'] ?? '')));
            $salle->changeCapaciteReunion(self::intOrNull((string) ($data['Capacité en réunion'] ?? '')));
            $salle->changeCapaciteU(self::intOrNull((string) ($data['Capacité en U'] ?? '')));
            $salle->changeCapaciteClasse(self::intOrNull((string) ($data['Capacité en Grande Ecole'] ?? '')));
            $salle->changeCapaciteTheatre(self::intOrNull((string) ($data['Capacité en Théâtre / Conférence'] ?? '')));
            $salle->changeCapaciteCabaret(self::intOrNull((string) ($data['Capacité en Cabaret'] ?? '')));
            $salle->changeCapaciteBanquet(self::intOrNull((string) ($data['Capacité en Banquet'] ?? '')));
            $salle->changeCapaciteCocktail(self::intOrNull((string) ($data['Capacité en Réception / Cocktail'] ?? '')));
            $salle->changeCapaciteAuditorium(self::intOrNull((string) ($data['Capacité en Auditorium'] ?? '')));
            $salle->changeLumiereJour(self::bool((string) ($data['Lumière du jour'] ?? '')));
            $salle->changeAccesPmr(self::bool((string) ($data['Accès PMR'] ?? '')));
            $salle->changeEspaceDansant(self::bool((string) ($data['Dansant'] ?? '')));
            $salle->changePosition($position++);
            $lieu->addSalle($salle);
        }
    }

    private function acces(Lieu $lieu, RawCsvRow $row): void
    {
        $position = 0;
        $add = static function (TypeAccesLieu $type, string $nom, string $distance, ?string $mode) use ($lieu, &$position): void {
            $nom = trim($nom);
            if ('' === $nom) {
                return;
            }
            $acces = new AccesLieu();
            $acces->changeType($type);
            $acces->changeNom(mb_substr($nom, 0, 255));
            if (is_numeric($distance) && (float) $distance > 0) {
                $acces->changeDistanceKilometres($distance);
            }
            $acces->changeModeTransport($mode);
            $acces->changePosition($position++);
            $lieu->addAcces($acces);
        };

        $add(TypeAccesLieu::Aeroport, $row->cell('Nom aéroport 1'), $row->cell('Distance (km) aéroport 1'), null);
        $add(TypeAccesLieu::Aeroport, $row->cell('Nom aéroport 2'), $row->cell('Distance (km) aéroport 2'), null);
        $add(TypeAccesLieu::Gare, $row->cell('Nom de la gare 1'), $row->cell('Distance (km) gare 1'), null);
        $add(TypeAccesLieu::GrandeVille, $row->cell('Nom de la ville'), $row->cell('Distance (km)'), null);

        $rer = trim($row->cell('Ligne RER').' – '.$row->cell('Arret RER'), ' –');
        if ('' !== $rer) {
            $add(TypeAccesLieu::Metro, 'RER '.$rer, '', 'RER');
        }
        $metro = trim($row->cell('Ligne Metro').' – '.$row->cell('Arret Metro'), ' –');
        if ('' !== $metro) {
            $add(TypeAccesLieu::Metro, $metro, '', 'Métro');
        }
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

    private static function priceOrNull(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if ('' === $value || !is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return $value;
    }

    private static function bool(string $value): bool
    {
        return in_array(trim($value), ['1', 'true'], true);
    }

    /** @param list<string> $warnings */
    private static function truncate(string $value, int $max, array &$warnings, string $warningCode): ?string
    {
        if ('' === $value) {
            return null;
        }
        if (mb_strlen($value) <= $max) {
            return $value;
        }
        $warnings[] = $warningCode;

        return mb_substr($value, 0, $max - 1).'…';
    }
}
