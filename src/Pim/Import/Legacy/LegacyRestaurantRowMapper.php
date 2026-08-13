<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Enum\TypeAccesRestaurant;
use App\Pim\Import\Dto\RawCsvRow;

/**
 * Hydrate un Restaurant depuis une ligne CSV production (Gamme = « Restaurant »).
 * Mêmes principes que les autres mappers legacy : warnings agrégés, seuls un
 * Id syspad ou un nom invalides rejettent la ligne.
 */
final readonly class LegacyRestaurantRowMapper
{
    public const SUPPORTED_GAMME = 'Restaurant';
    /** Colonne CSV portant l'Id syspad (pivot d'idempotence des commandes d'import). */
    public const SYSPAD_COLUMN = 'Id syspad';

    public function __construct(
        private LegacyLovMapper $lovMapper,
        private LegacyRestaurantLovMapper $restaurantLovMapper,
    ) {}

    public function supports(RawCsvRow $row): bool
    {
        return self::SUPPORTED_GAMME === $row->cell('Gamme');
    }

    public function map(RawCsvRow $row): LegacyMappedRestaurant
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

        $restaurant = new Restaurant();
        $restaurant->fiche()->assignImportedCode((int) $syspadId);
        $restaurant->changeLabel(mb_substr($label, 0, 255));
        $restaurant->fiche()->changeTelephone(self::nullable($row->cell('Téléphone')));
        // Règle legacy : colonne « Tag » vide = partenaire BP (icône marketplace).
        $restaurant->fiche()->changePartenaireBp('' === $row->cell('Tag'));

        $themes = $this->restaurantLovMapper->themes($row->cell('Thématique'));
        array_push($warnings, ...$themes['warnings']);
        if ([] !== $themes['typesRestaurant']) {
            $restaurant->changeTypesRestaurant($themes['typesRestaurant']);
        }
        if ([] !== $themes['engagementsRse']) {
            $restaurant->changeEngagementsRse($themes['engagementsRse']);
        }
        if ('' !== $row->cell('Classification')) {
            $warnings[] = 'classification_ignoree';
        }

        $this->descriptions($restaurant, $row, $warnings);
        $this->atouts($restaurant, $row, $warnings);
        $this->equipements($restaurant, $row);
        $this->capacites($restaurant, $row);
        $this->localisation($restaurant, $row, $warnings);
        $this->salles($restaurant, $row, $warnings);
        $this->acces($restaurant, $row);

        if ('true' === $row->cell('Accès PMR')) {
            $restaurant->changeAccesPmr(true);
        }
        $restaurant->changeYoutubeUrl(self::nullable(mb_substr($row->cell('Lien youtube'), 0, 255)));

        $photosJson = $row->cell('Photos');

        return new LegacyMappedRestaurant(
            (int) $syspadId,
            $restaurant,
            'true' === $row->cell('Publié / non publié'),
            $row->cell('Gamme'),
            '' === $photosJson ? null : $photosJson,
            $warnings,
        );
    }

    /** @param list<string> $warnings */
    private function descriptions(Restaurant $restaurant, RawCsvRow $row, array &$warnings): void
    {
        // La description générale et le texte « Restauration / Gastronomie »
        // sont concaténés : le PIM n'a qu'un champ de description restaurant.
        $parts = array_filter([
            $row->cell('Description générale'),
            $row->cell('Restauration / Gastronomie'),
        ], static fn (string $part): bool => '' !== $part);
        $restaurant->changeDescriptionGenerale([] === $parts ? null : implode("\n\n", $parts));
        if ('' !== $row->cell('Salles de séminaires')) {
            $warnings[] = 'desc_salles_non_mappee';
        }
        if ('' !== $row->cell('Hébergement')) {
            $warnings[] = 'hebergement_non_mappe';
        }
    }

    /** @param list<string> $warnings */
    private function atouts(Restaurant $restaurant, RawCsvRow $row, array &$warnings): void
    {
        $atouts = [];
        foreach ([1, 2, 3, 4, 5] as $index) {
            $value = $row->cell('Les plus (sous forme de Bullet point) - '.$index);
            if ('' === $value) {
                continue;
            }
            if (mb_strlen($value) > 255) {
                $value = mb_substr($value, 0, 254).'…';
                $warnings[] = 'atout_tronque';
            }
            $atouts[] = $value;
        }
        if ([] !== $atouts) {
            $restaurant->changeAtouts($atouts);
        }
    }

    private function equipements(Restaurant $restaurant, RawCsvRow $row): void
    {
        $equipements = [];
        if ('true' === $row->cell('Wifi')) {
            $equipements[] = 'WIFI';
        }
        if ('true' === $row->cell('Climatisation en salles de réunion')) {
            $equipements[] = 'CLIMATISATION';
        }
        if ([] !== $equipements) {
            $restaurant->changeEquipements($equipements);
        }
    }

    private function capacites(Restaurant $restaurant, RawCsvRow $row): void
    {
        $restaurant->changeCapaciteCocktail(self::intOrNull($row->cell('Capacité de la plus grande salle en configuration cocktail (nb de pers)')));
    }

    /** @param list<string> $warnings */
    private function localisation(Restaurant $restaurant, RawCsvRow $row, array &$warnings): void
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
        $restaurant->changeLocalisation($localisation);
    }

    /** @param list<string> $warnings */
    private function salles(Restaurant $restaurant, RawCsvRow $row, array &$warnings): void
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
            $salle = new RestaurantSalle();
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
            $restaurant->addSalle($salle);
        }
    }

    private function acces(Restaurant $restaurant, RawCsvRow $row): void
    {
        $position = 0;
        $add = static function (TypeAccesRestaurant $type, string $nom) use ($restaurant, &$position): void {
            $nom = trim($nom);
            if ('' === $nom) {
                return;
            }
            $acces = new RestaurantAcces();
            $acces->changeType($type);
            $acces->changeNom(mb_substr($nom, 0, 255));
            $acces->changePosition($position++);
            $restaurant->addAcces($acces);
        };

        $add(TypeAccesRestaurant::Aeroport, $row->cell('Nom aéroport 1'));
        $add(TypeAccesRestaurant::Aeroport, $row->cell('Nom aéroport 2'));
        $add(TypeAccesRestaurant::Gare, $row->cell('Nom de la gare 1'));
        $add(TypeAccesRestaurant::GrandeVille, $row->cell('Nom de la ville'));

        $rer = trim($row->cell('Ligne RER').' – '.$row->cell('Arret RER'), ' –');
        if ('' !== $rer) {
            $add(TypeAccesRestaurant::Metro, 'RER '.$rer);
        }
        $metro = trim($row->cell('Ligne Metro').' – '.$row->cell('Arret Metro'), ' –');
        if ('' !== $metro) {
            $add(TypeAccesRestaurant::Metro, $metro);
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

    private static function bool(string $value): bool
    {
        return in_array(trim($value), ['1', 'true'], true);
    }
}
