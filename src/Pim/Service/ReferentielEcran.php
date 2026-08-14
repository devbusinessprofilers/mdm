<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\SavedView;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\Form\ReferentielFiltresType;
use App\Pim\Form\ReferentielSelectionType;
use App\Pim\Form\SavedViewType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\ReferentielVue;
use App\Account\Repository\UserRepository;
use App\Pim\Repository\SavedViewRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Assemble tout ce que l'écran de la liste du référentiel affiche : la vue
 * (lignes, facettes), les formulaires (filtres, sélection, vues) et les liens
 * des lignes. Le contrôleur ne fait que déléguer et rendre.
 */
final readonly class ReferentielEcran
{
    public function __construct(
        private ReferentielListeProvider $provider,
        private SavedViewRepository $vues,
        private UserRepository $utilisateurs,
        private SiteDiffusionRepository $sites,
        private FicheRouteResolver $routes,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array<string, mixed> Variables du gabarit mdm/referentiel.html.twig. */
    public function variables(
        ReferentielFiltres $filtres,
        ?FicheCursor $cursor,
        ?TypeFiche $gammeImposee,
        string $userId,
        int $parPage,
    ): array {
        $vue = $this->provider->vue($filtres, $cursor, $parPage);
        $parametresFiltre = ['f' => $filtres->toArray()];
        $formFiltres = $this->forms->create(ReferentielFiltresType::class, $filtres, [
            'action' => $this->urls->generate('app_mdm_referentiel_general'),
            'pays_choices' => $vue->paysChoices,
            'valeurs_choices' => $vue->valeursChoices,
            'contributeurs_choices' => $vue->contributeursChoices,
        ]);
        $idsPage = [];
        $urls = [];
        foreach ($vue->lignes as $ligne) {
            $idsPage[$ligne->id] = $ligne->id;
            $urls[$ligne->id] = [
                'voir' => TypeFiche::Traiteur === $ligne->type ? null : $this->routes->showUrl($ligne->type, $ligne->id),
                'modifier' => TypeFiche::Traiteur === $ligne->type ? null : $this->routes->editUrl($ligne->type, $ligne->id),
                'editeur' => match ($ligne->type) {
                    TypeFiche::Lieu => $this->urls->generate('app_mdm_fiche_lieu', ['id' => $ligne->id]),
                    TypeFiche::Traiteur => null,
                    default => $this->urls->generate('app_mdm_fiche_gamme', [
                        'gamme' => FicheEditeurEcran::slug($ligne->type),
                        'id' => $ligne->id,
                    ]),
                },
                'edition_rapide' => $this->urls->generate(
                    'app_mdm_edition_rapide',
                    ['id' => $ligne->id] + $parametresFiltre,
                ),
            ];
        }
        $formSelection = $this->forms->createNamed('selection', ReferentielSelectionType::class, null, [
            'action' => $this->urls->generate('app_mdm_referentiel_actions', $parametresFiltre),
            'ids_choices' => $idsPage,
            'contributeurs' => $this->utilisateurs->findActifs(),
            'sites_choices' => $this->sites->choicesGroupees(),
        ]);
        $formVue = $this->forms->createNamed('vue', SavedViewType::class, null, [
            'action' => $this->urls->generate('app_mdm_referentiel_vue_enregistrer', $parametresFiltre),
        ]);
        $vuesEnregistrees = [];
        foreach ($this->vues->findVisiblesPour($userId) as $enregistree) {
            $vuesEnregistrees[] = [
                'vue' => $enregistree,
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => $enregistree->filters()]),
                'suppression' => $enregistree->belongsTo($userId)
                    ? $this->formSuppressionVue($enregistree)->createView()
                    : null,
            ];
        }

        return [
            'vue' => $vue,
            'filtres' => $filtres,
            'filtres_actifs' => $this->filtresActifs($filtres, $vue, $gammeImposee),
            'form_filtres' => $formFiltres->createView(),
            'form_selection' => $formSelection->createView(),
            'form_vue' => $formVue->createView(),
            'vues_enregistrees' => $vuesEnregistrees,
            'gamme_imposee' => $gammeImposee,
            'parametres_filtre' => $parametresFiltre,
            'urls' => $urls,
            'url_mes_fiches' => $this->urls->generate('app_mdm_referentiel_general', [
                'f' => ['contributeurs' => [$userId]],
            ]),
        ];
    }

    /**
     * Libellés des facettes à paliers fixes, miroir de ReferentielFiltresType —
     * utilisés pour nommer les badges de filtres actifs.
     */
    private const LIBELLES = [
        'gammes' => ['cle' => 'Gamme', 'valeurs' => [
            'lieu' => 'Lieux', 'restaurant' => 'Restaurants', 'activite' => 'Activités', 'service_evenementiel' => 'Prestataires de services',
        ]],
        'statuts' => ['cle' => 'Statut', 'valeurs' => [
            'en_cours' => 'En cours', 'en_attente_validation' => 'En attente de validation',
            'validee' => 'Validée', 'publiee' => 'Publiée', 'archivee' => 'Archivée',
        ]],
        'completudes' => ['cle' => 'Complétude', 'valeurs' => [
            'complet' => 'Complet (≥ 75 %)', 'publiable' => 'Publiable (60 – 74 %)', 'insuffisant' => 'Insuffisant (< 60 %)',
        ]],
        'canaux' => ['cle' => 'Diffusion', 'valeurs' => [
            'c20' => 'Sur 20 canaux et plus', 'c5' => 'De 5 à 19 canaux', 'c1' => 'De 1 à 4 canaux', 'c0' => 'Aucun canal',
        ]],
        'dates' => ['cle' => 'Dates', 'valeurs' => [
            'creees_semaine' => 'Créées cette semaine', 'modifiees_jour' => "Modifiées aujourd'hui", 'sans_modif_6m' => 'Sans modification depuis 6 mois',
        ]],
    ];

    /** Facettes booléennes : libellé porté par le badge, la clé étant retirée d'un bloc. */
    private const LIBELLES_BOOLEENS = [
        'ia' => 'Valeurs IA à arbitrer',
        'repli' => 'Contact de repli',
        'premium' => 'Adhérent Business Premium',
    ];

    /**
     * Un badge par valeur de filtre active. `filtre` est le jeu de filtres
     * restant une fois cette seule valeur retirée : le gabarit en dérive l'URL
     * sur la route courante, ce qui préserve la gamme imposée. Nourrit le
     * bandeau « filtres actifs ».
     *
     * @return list<array{cle: string, valeur: string, filtre: array<string, mixed>}>
     */
    private function filtresActifs(ReferentielFiltres $filtres, ReferentielVue $vue, ?TypeFiche $gammeImposee): array
    {
        $base = $filtres->toArray();
        // Sur une page de gamme, la gamme est forcée par la route, pas par `f` :
        // ni badge ni valeur `f` à son sujet.
        if (null !== $gammeImposee) {
            unset($base['gammes']);
        }
        $badges = [];

        if (isset($base['q'])) {
            $badges[] = $this->badge('Recherche', (string) $base['q'], $this->sansCle($base, 'q'));
        }

        // Facettes multi-valeurs : libellés statiques (enums, paliers) puis dynamiques (BDD).
        $mapsDynamiques = [
            'pays' => ['cle' => 'Pays', 'valeurs' => array_flip($vue->paysChoices)],
            'contributeurs' => ['cle' => 'Contributeur', 'valeurs' => array_flip($vue->contributeursChoices)],
            'valeurs' => ['cle' => 'Catégorie', 'valeurs' => array_flip($vue->valeursChoices)],
        ];
        foreach (self::LIBELLES + $mapsDynamiques as $cle => $definition) {
            foreach ($base[$cle] ?? [] as $valeur) {
                $badges[] = $this->badge(
                    $definition['cle'],
                    (string) ($definition['valeurs'][$valeur] ?? $valeur),
                    $this->sansValeur($base, $cle, $valeur),
                );
            }
        }

        foreach (self::LIBELLES_BOOLEENS as $cle => $libelle) {
            if (isset($base[$cle])) {
                $badges[] = $this->badge('Filtre', $libelle, $this->sansCle($base, $cle));
            }
        }

        return $badges;
    }

    /**
     * @param array<string, mixed> $filtreReduit
     *
     * @return array{cle: string, valeur: string, filtre: array<string, mixed>}
     */
    private function badge(string $cle, string $valeur, array $filtreReduit): array
    {
        return ['cle' => $cle, 'valeur' => $valeur, 'filtre' => $filtreReduit];
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function sansCle(array $base, string $cle): array
    {
        unset($base[$cle]);

        return $base;
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function sansValeur(array $base, string $cle, mixed $valeur): array
    {
        $restant = array_values(array_filter(
            is_array($base[$cle] ?? null) ? $base[$cle] : [],
            static fn (mixed $v): bool => $v !== $valeur,
        ));
        if ([] === $restant) {
            unset($base[$cle]);
        } else {
            $base[$cle] = $restant;
        }

        return $base;
    }

    /** @return FormInterface<mixed> */
    public function formSuppressionVue(SavedView $vue): FormInterface
    {
        return $this->forms->createNamed('vue_suppression_'.$vue->id(), ActionType::class, null, [
            'action' => $this->urls->generate('app_mdm_referentiel_vue_supprimer', ['id' => $vue->id()]),
            'button_label' => 'Supprimer',
            'csrf_token_id' => 'vue-suppression-'.$vue->id(),
        ]);
    }

    /** @return FormInterface<mixed> Formulaire de sélection reconstruit pour la soumission des actions groupées. */
    public function formSelectionSoumise(mixed $soumis): FormInterface
    {
        $ids = is_array($soumis) && is_array($soumis['ids'] ?? null) ? $soumis['ids'] : [];
        $choices = [];
        foreach ($ids as $id) {
            if (is_string($id) && 1 === preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id)) {
                $choices[$id] = $id;
            }
        }

        // Les identifiants soumis sont revalidés fiche par fiche (voter + état).
        return $this->forms->createNamed('selection', ReferentielSelectionType::class, null, [
            'ids_choices' => $choices,
            'contributeurs' => $this->utilisateurs->findActifs(),
            'sites_choices' => $this->sites->choicesGroupees(),
        ]);
    }
}
