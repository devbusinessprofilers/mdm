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
use App\Account\Repository\UserRepository;
use App\Pim\Repository\SavedViewRepository;
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
        ]);
    }
}
