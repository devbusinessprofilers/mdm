<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Form\SuggestionSelectionType;
use App\Dashboard\Repository\QualiteRepository;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Assemble le tableau de suggestions de l'onglet Conflits : onglets par source
 * (avec effectifs), page triée de la source active et formulaire de sélection
 * groupée. La décision (accepter / ignorer) est portée par l'URL du bouton.
 */
final readonly class SuggestionsEcran
{
    /** Onglets du tableau (clé de source → libellé descriptif : ce que la suggestion apporte). */
    public const SOURCES = [
        'adresses' => 'Adresses & GPS',
        'sirene' => 'Statut & SIRET',
        'geoapify' => 'Attributs restaurants',
        'datatourisme' => 'Descriptions & équipements',
        'wikidata' => 'Chaîne hôtelière',
    ];

    /** Nom de la source affiché comme provenance sous le nom de la fiche (BAN/Geoapify côté adresses = par ligne). */
    private const PROVENANCES = [
        'adresses' => 'BAN / Geoapify',
        'sirene' => 'Sirene',
        'geoapify' => 'Geoapify',
        'datatourisme' => 'DATAtourisme',
        'wikidata' => 'Wikidata',
    ];

    public const PAR_PAGE = 20;

    public function __construct(
        private QualiteRepository $qualite,
        private FormFactoryInterface $forms,
    ) {
    }

    /**
     * Charge les CINQ onglets (le bandeau bascule côté client, sans reload) : un
     * seul formulaire de sélection couvre toutes les lignes ; chaque onglet
     * connaît l'offset de ses cases dans ce formulaire. Seul l'onglet actif est
     * paginé/trié via le serveur (les autres à la page 1).
     *
     * @return array<string, mixed>
     */
    public function assembler(string $src, int $page, string $tri, string $ordre): array
    {
        $comptes = $this->qualite->comptesSuggestionsParSource();
        $actif = array_key_exists($src, self::SOURCES)
            ? $src
            : (array_key_first(array_filter(self::SOURCES, static fn (string $cle): bool => ($comptes[$cle] ?? 0) > 0, ARRAY_FILTER_USE_KEY)) ?? 'adresses');
        $triActif = 'code' === $tri ? 'code' : 'score';
        $ordreActif = 'asc' === $ordre ? 'asc' : 'desc';
        $pageActive = max(1, $page);

        $onglets = [];
        $choices = [];
        foreach (self::SOURCES as $cle => $label) {
            $estActif = $cle === $actif;
            $p = $estActif ? $pageActive : 1;
            $t = $estActif ? $triActif : 'score';
            $o = $estActif ? $ordreActif : 'desc';
            $data = $this->qualite->pageSuggestions($cle, $p, self::PAR_PAGE, $t, $o);
            $lignes = [];
            foreach ($data['lignes'] as $ligne) {
                // Les lignes sans proposition applicable (« Aucun résultat
                // fiable ») ne sont pas cochables : les inclure enverrait la
                // sélection vers un échec garanti.
                if ($ligne['acceptable'] ?? true) {
                    $ligne['case_index'] = count($choices);
                    $choices[(string) $ligne['select_id']] = (string) $ligne['select_id'];
                } else {
                    $ligne['case_index'] = null;
                }
                $lignes[] = $ligne;
            }
            $onglets[] = [
                'cle' => $cle,
                'label' => $label,
                'provenance' => self::PROVENANCES[$cle],
                'compte' => $comptes[$cle] ?? 0,
                'lignes' => $lignes,
                'total' => $data['total'],
                'page' => $p,
                'pages' => max(1, (int) ceil($data['total'] / self::PAR_PAGE)),
                'tri' => $t,
                'ordre' => $o,
            ];
        }
        $form = $this->forms->create(SuggestionSelectionType::class, null, ['ids_choices' => $choices]);

        return [
            'sug_actif' => $actif,
            'sug_onglets' => $onglets,
            'sug_form' => $form->createView(),
        ];
    }
}
