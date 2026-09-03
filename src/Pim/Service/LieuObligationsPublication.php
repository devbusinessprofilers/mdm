<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\LieuRepository;

/**
 * Champs « Obligatoire » de la bible « VERSION BP » (gamme Lieu, 2026-09) :
 * une fiche Lieu ne peut être ni soumise à validation ni publiée tant qu'un
 * de ces champs est vide. Les brouillons restent libres, les fiches déjà
 * publiées ne sont pas dépubliées rétroactivement (aucune garde dans
 * IndexFicheHandler) et l'import legacy publie hors workflow
 * (Fiche::publishForImport) — il contourne donc la règle, comme demandé.
 *
 * Le nom (validation de soumission) et les photos (PhotoPublicationGuard)
 * sont couverts ailleurs : ce service ne porte que les champs métier.
 */
final readonly class LieuObligationsPublication
{
    /**
     * Chemins de formulaire des champs obligatoires, dans l'ordre de
     * manquants() — source unique pour l'astérisque permanent de l'éditeur
     * (LieuType::finishView) ; un test garantit l'alignement avec manquants().
     */
    public const CHEMINS = [
        'generaleTypologie',
        'accessibiliteDescription.descGenerale',
        'acces.aeroport',
        'acces.gare',
        'hebergement.chambreNbTotal',
        'hebergement.chambreCapaciteTotale',
        'hebergement.chambreDescGenerale',
        'syntheseSalles.salleReunionNbTotal',
        'syntheseSalles.salleReunionCapaciteMaxCocktail',
        'syntheseSalles.salleReunionCapaciteMaxTheatre',
        'syntheseSalles.salleReunionCapaciteMinTheatre',
        'syntheseSalles.salleReunionSurfaceMinReunion',
        'syntheseSalles.salleReunionSurfaceMaxReunion',
        'syntheseSalles.salleReunionDescSalleSeminaire',
        'restauration.restaurantTotal',
        'restauration.restaurantCapaciteAssis',
    ];

    /**
     * Obligations portées par la collection « acces » (au moins une ligne
     * d'un type donné) : elles n'ont pas de champ propre dans le formulaire.
     */
    public const PSEUDO_CHEMINS = ['acces.aeroport', 'acces.gare'];

    public function __construct(private LieuRepository $lieux)
    {
    }

    /**
     * Chemins réellement rendus par l'éditeur : les pseudo-chemins sont
     * ramenés à leur collection (« acces »), qui porte alors la mention.
     *
     * @return list<string>
     */
    public static function cheminsFormulaire(): array
    {
        $chemins = [];
        foreach (self::CHEMINS as $chemin) {
            $chemins[] = in_array($chemin, self::PSEUDO_CHEMINS, true) ? explode('.', $chemin, 2)[0] : $chemin;
        }

        return array_values(array_unique($chemins));
    }

    /**
     * Champs obligatoires vides, indexés par le chemin du champ dans le
     * formulaire de la fiche (valeur = libellé affiché à l'utilisateur).
     *
     * @return array<string, string>
     */
    public function manquants(Lieu $lieu): array
    {
        $manquants = [];
        if ([] === $lieu->generaleTypologie()) {
            $manquants['generaleTypologie'] = 'Typologie';
        }
        if (self::vide($lieu->descGenerale())) {
            $manquants['accessibiliteDescription.descGenerale'] = 'Texte de description';
        }
        if ([] === $lieu->accesAeroport()) {
            $manquants['acces.aeroport'] = 'Au moins un accès de type aéroport';
        }
        if ([] === $lieu->accesGare()) {
            $manquants['acces.gare'] = 'Au moins un accès de type gare';
        }
        if ($lieu->chambreHebergement()) {
            if (null === $lieu->chambreNbTotal()) {
                $manquants['hebergement.chambreNbTotal'] = 'Nombre total de chambres';
            }
            if (null === $lieu->chambreCapaciteTotale()) {
                $manquants['hebergement.chambreCapaciteTotale'] = "Capacité d'accueil totale des personnes hébergées";
            }
            if (self::vide($lieu->chambreDescGenerale())) {
                $manquants['hebergement.chambreDescGenerale'] = 'Texte de description générale (hébergement)';
            }
        }
        if ($lieu->salleReunionExist()) {
            foreach ([
                'salleReunionNbTotal' => 'Nombre de salles de réunion',
                'salleReunionCapaciteMaxCocktail' => 'Capacité de la plus grande salle en configuration cocktail',
                'salleReunionCapaciteMaxTheatre' => 'Capacité de la plus grande salle en configuration théâtre',
                'salleReunionCapaciteMinTheatre' => 'Capacité de la plus petite salle en configuration théâtre',
                'salleReunionSurfaceMinReunion' => 'Surface de la plus petite salle de réunion (en m²)',
                'salleReunionSurfaceMaxReunion' => 'Surface de la plus grande salle de réunion (en m²)',
            ] as $champ => $libelle) {
                if (null === $lieu->{$champ}()) {
                    $manquants['syntheseSalles.'.$champ] = $libelle;
                }
            }
            if (self::vide($lieu->salleReunionDescSalleSeminaire())) {
                $manquants['syntheseSalles.salleReunionDescSalleSeminaire'] = 'Texte de description (salles de réunion)';
            }
        }
        if (null === $lieu->restaurantTotal()) {
            $manquants['restauration.restaurantTotal'] = 'Nombre de restaurants sur place';
        }
        if (null === $lieu->restaurantCapaciteAssis()) {
            $manquants['restauration.restaurantCapaciteAssis'] = 'Capacité maximale en configuration assise';
        }

        return $manquants;
    }

    /**
     * Libellés des champs obligatoires vides d'une fiche ; vide pour les
     * autres gammes (seule la gamme Lieu est concernée par la bible).
     *
     * @return list<string>
     */
    public function manquantsPourFiche(Fiche $fiche): array
    {
        if (TypeFiche::Lieu !== $fiche->type()) {
            return [];
        }
        $lieu = $this->lieux->findOneByFiche($fiche);

        return $lieu instanceof Lieu ? array_values($this->manquants($lieu)) : [];
    }

    /**
     * Message d'un refus de publication, prêt pour un flash.
     *
     * @param list<string> $manquants
     */
    public static function motif(array $manquants): string
    {
        return 'Champs obligatoires manquants : '.implode(', ', $manquants).'.';
    }

    private static function vide(?string $valeur): bool
    {
        if (null === $valeur) {
            return true;
        }
        // Un texte enrichi vide peut ne contenir que des balises et des
        // espaces insécables (<p>&nbsp;</p>).
        $texte = html_entity_decode(strip_tags($valeur), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '' === trim($texte, " \t\n\r\0\x0B\u{A0}");
    }
}
