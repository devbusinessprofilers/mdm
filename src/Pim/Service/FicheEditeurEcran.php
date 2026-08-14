<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Service\CurrentActorProvider;
use App\Audit\Repository\AuditRevisionRepository;
use App\Etl\Repository\FicheSalesforceRepository;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Pim\Entity\Fiche;
use App\Pim\Completeness\CompletenessCalculator;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ActiviteType;
use App\Pim\Form\AdresseSuggestionFormFactory;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\LieuType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Service\ParametreProviderInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Écran d'édition de fiche par sections (maquette front), pour les quatre
 * gammes de cette version. Chaque section soumet le formulaire complet
 * existant de la gamme en mode partiel — les champs absents de la section ne
 * sont pas touchés (submit avec clearMissing = false). Les mécanismes restent
 * ceux de l'édition classique : AdminManagers, politique de mutation interne,
 * complétude, audit.
 */
final readonly class FicheEditeurEcran
{
    private const PREFIXES = [
        'lieu' => 'LIE',
        'restaurant' => 'RES',
        'activite' => 'ACT',
        'service_evenementiel' => 'SER',
    ];

    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private LieuAdminManager $lieux,
        private RestaurantAdminManager $restaurants,
        private ActiviteAdminManager $activites,
        private ServiceEvenementielAdminManager $services,
        private LieuAdminViewBuilder $lieuVue,
        private RestaurantAdminViewBuilder $restaurantVue,
        private ActiviteAdminViewBuilder $activiteVue,
        private ServiceEvenementielAdminViewBuilder $serviceVue,
        private CurrentActorProvider $actor,
        private InternalFicheMutationPolicy $policy,
        private CompletenessFieldConfigurationRepository $configurations,
        private CompletenessFieldCatalog $catalog,
        private CompletenessCalculator $calculator,
        private FicheActionFormFactory $actions,
        private FicheRouteResolver $routes,
        private SiteDiffusionRepository $sites,
        private FicheAffiliationRepository $affiliations,
        private FicheCollaborateursEcran $collaborateurs,
        private DocumentExtractionRepository $extractions,
        private AuditRevisionRepository $revisions,
        private FicheWorkflowManager $workflow,
        private OcrCategoryPolicy $ocrCategories,
        private OcrReviewFormFactory $ocrRevues,
        private ParametreProviderInterface $parametres,
        private FicheSalesforceRepository $salesforce,
        private AdresseSuggestionFormFactory $adresseSuggestions,
    ) {
    }

    /** @return FormInterface<mixed> Le formulaire complet de la gamme ; la section n'en rend qu'une partie. */
    public function formSection(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): FormInterface
    {
        return $this->forms->create(match (true) {
            $entite instanceof Lieu => LieuType::class,
            $entite instanceof Restaurant => RestaurantType::class,
            $entite instanceof Activite => ActiviteType::class,
            default => ServiceEvenementielType::class,
        }, $entite);
    }

    /**
     * Soumission partielle : seuls les champs présents dans la requête sont
     * appliqués, le reste de la fiche est laissé intact.
     *
     * @param FormInterface<mixed> $form
     */
    public function soumettreSection(Request $request, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form): bool
    {
        $data = $request->request->all($form->getName());
        if ([] === $data) {
            return false;
        }

        return (bool) $this->policy->execute($entite->fiche(), function () use ($form, $entite, $data): bool {
            $existing = $this->photoAssetIds($entite);
            $form->submit($data, false);
            if (!$form->isValid()) {
                return false;
            }
            try {
                $this->enregistrer($entite, $form, $existing);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return false;
            } catch (FilesystemException) {
                $form->addError(new FormError('Le stockage des médias est temporairement indisponible.'));

                return false;
            }

            return true;
        });
    }

    /** @return FormInterface<mixed> Sélection des sites de diffusion (section Visibilité). */
    public function formSites(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): FormInterface
    {
        $fiche = $entite->fiche();
        $obligatoires = [];
        $choices = [];
        foreach ($this->sites->findActifsOrdonnes() as $site) {
            $mention = $site->obligatoire() ? ' (obligatoire)' : ($site->payant() ? ' (payant)' : '');
            $choices[$site->groupe()][$site->label().$mention] = $site->id();
            if ($site->obligatoire()) {
                $obligatoires[] = $site->id();
            }
        }
        $selection = array_values(array_unique([...$fiche->siteDiffusionIds(), ...$obligatoires]));

        return $this->forms->createNamedBuilder('sites_diffusion', options: ['csrf_token_id' => 'sites-'.$fiche->idString()])
            ->add('sites', ChoiceType::class, [
                'label' => 'Sites de diffusion',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'data' => $selection,
                'choice_attr' => static fn (int $id): array => in_array($id, $obligatoires, true)
                    ? ['disabled' => 'disabled']
                    : [],
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer la diffusion'])
            ->getForm();
    }

    /** @param FormInterface<mixed> $form */
    public function soumettreSites(Request $request, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form): bool
    {
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }
        /** @var array{sites: list<int>} $data */
        $data = $form->getData();
        $this->policy->execute($entite->fiche(), function () use ($entite, $data): void {
            $fiche = $entite->fiche();
            $retenus = array_values(array_filter(
                $this->sites->findActifsOrdonnes(),
                static fn (SiteDiffusion $site): bool => $site->obligatoire() || in_array($site->id(), $data['sites'], true),
            ));
            $retenusIds = array_map(static fn (SiteDiffusion $site): ?int => $site->id(), $retenus);
            // Ajout pur de canaux : mise à jour technique sans transition de
            // workflow, comme l'action de masse. Un retrait reste une
            // modification métier (remplacement complet).
            if ([] === array_diff($fiche->siteDiffusionIds(), $retenusIds)) {
                $fiche->ajouterSitesDiffusion($retenus);

                return;
            }
            $fiche->replaceSiteDiffusion($retenus);
        });
        $this->workflow->indexAndFlush($entite->fiche());

        return true;
    }

    /**
     * @param FormInterface<mixed> $form Le formulaire complet de la gamme (pour les variables médias)
     *
     * @return array<string, mixed> Variables du gabarit hors formulaires de la section
     */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, int $index, FormInterface $form): array
    {
        $fiche = $entite->fiche();
        $type = $fiche->type();
        $sections = FicheSectionsCatalogue::pour($type);
        $parSection = $this->completudesParSection($entite);
        $lienSection = fn (int $i): string => TypeFiche::Lieu === $type
            ? $this->urls->generate('app_mdm_fiche_lieu', ['id' => $fiche->idString(), 'section' => $i])
            : $this->urls->generate('app_mdm_fiche_gamme', ['gamme' => self::slug($type), 'id' => $fiche->idString(), 'section' => $i]);
        $onglets = [];
        foreach ($sections as $i => $section) {
            $onglets[] = [
                'index' => $i,
                'titre' => $section['titre'],
                'completude' => $parSection[$i],
                'actif' => $i === $index,
                'url' => $lienSection($i),
                'icone' => self::iconeSection($section['titre']),
                'groupe' => self::groupeSection($section['blocs']),
            ];
        }
        $section = $sections[$index];
        $id = $fiche->idString();
        $statut = $fiche->status()->value;
        $domaine = self::domaine($type);

        return [
            'onglets' => $onglets,
            'section' => $section,
            'section_index' => $index,
            'entete' => [
                'reference' => sprintf('%s-%06d', self::PREFIXES[$type->value] ?? 'FIC', $fiche->code()),
                'version' => $fiche->version(),
                'statut' => $statut,
                'completude' => $entite->completeness(),
                'completude_canaux' => $entite->completenessByChannel(),
            ],
            'liens' => [
                // Le dépôt et la revue vivent dans la section extraction de l'éditeur.
                'ocr' => $this->urlExtraction($type, $id),
                'ocr_admin' => $this->urls->generate('app_ocr_index', ['id' => $id]),
                'traductions' => $this->urls->generate('app_enrichment_fiche_translation_show', ['id' => $id]),
                'historique' => $this->routes->historyUrl($type, $id),
            ],
            'actions' => array_filter([
                'submit' => 'en_cours' === $statut ? $this->actions->action($domaine, $id, 'submit', 'Soumettre à validation')->createView() : null,
                'validate' => 'en_attente_validation' === $statut ? $this->actions->action($domaine, $id, 'validate', 'Valider')->createView() : null,
                'reject' => 'en_attente_validation' === $statut ? $this->actions->reject($domaine, $id)->createView() : null,
                'publish' => 'validee' === $statut ? $this->actions->action($domaine, $id, 'publish', 'Publier')->createView() : null,
                'archive' => 'publiee' === $statut ? $this->actions->action($domaine, $id, 'archive', 'Archiver')->createView() : null,
            ]),
            'action_suppression' => $this->actions->action($domaine, $id, 'delete', 'Supprimer', true, match ($type) {
                TypeFiche::Lieu => 'Supprimer ce lieu ?',
                TypeFiche::Activite => 'Supprimer cette activité ?',
                TypeFiche::Restaurant => 'Supprimer ce restaurant ?',
                default => 'Supprimer ce service ?',
            })->createView(),
            'medias' => in_array('medias', $section['blocs'], true) ? $this->medias($entite, $form) : null,
            'affiliations' => in_array('collaborateurs', $section['blocs'], true)
                ? array_map(
                    static fn (array $ligne): array => [
                        'affiliation' => $ligne['affiliation'],
                        'edition' => $ligne['edition']->createView(),
                        'suppression' => $ligne['suppression']->createView(),
                    ],
                    $this->collaborateurs->formsAffiliations($fiche, $this->affiliations->findBy(['fiche' => $fiche->id()])),
                )
                : [],
            'form_invitation' => in_array('collaborateurs', $section['blocs'], true)
                ? $this->collaborateurs->formInvitation($fiche)->createView()
                : null,
            'extractions' => in_array('suggestions', $section['blocs'], true)
                ? $this->extractions->history($fiche)
                : [],
            'extraction' => in_array('suggestions', $section['blocs'], true)
                ? $this->extractionVars($fiche)
                : null,
            'historique' => in_array('historique', $section['blocs'], true)
                ? $this->revisions->history($id, null, 10)
                : [],
            // Données Salesforce en lecture seule (refresh quotidien) ; null =
            // bloc hors section, false = fiche inconnue de Salesforce.
            'salesforce' => in_array('salesforce', $section['blocs'], true)
                ? ($this->salesforce->forFiche($fiche->id()) ?? false)
                : null,
            'suggestions_attente' => in_array('suggestions_attente', $section['blocs'], true)
                ? $this->suggestionsAttenteVars($fiche)
                : null,
        ];
    }

    /**
     * Bloc « Suggestions en attente » de l'onglet Informations générales :
     * corrections et enrichissements proposés par les vérifications
     * automatiques, une suggestion par ligne avec sa source (BAN aujourd'hui,
     * IA demain), à arbitrer en un clic. Les suggestions de l'extraction OCR
     * ne passent pas ici : elles vivent dans le flux d'extraction.
     *
     * @return array{lignes: list<array<string, mixed>>}
     */
    private function suggestionsAttenteVars(Fiche $fiche): array
    {
        $lignes = [];
        $localisation = $fiche->localisation();
        if (null !== $localisation && $localisation->banEcart()) {
            $proposition = $localisation->banProposition();
            $lignes[] = [
                'source' => LocalisationBanVerifier::estFrancaise($localisation) ? 'BAN' : 'Geoapify',
                'label' => 'Adresse',
                'actuel' => trim(sprintf(
                    '%s %s %s',
                    $localisation->ruePostale() ?? '',
                    $localisation->codePostal() ?? '',
                    $localisation->ville() ?? '',
                )),
                'valeur' => self::propositionAffichable($proposition),
                'confiance' => null === $localisation->banScore() ? null : (int) round($localisation->banScore() * 100),
                // Sans proposition (aucun résultat fiable), il n'y a rien à
                // accepter : la correction se fait dans la section Localisation.
                'accepter' => null === $proposition
                    ? null
                    : $this->adresseSuggestions->action($fiche->idString(), 'accepter')->createView(),
                'ignorer' => $this->adresseSuggestions->action($fiche->idString(), 'ignorer')->createView(),
            ];
        }

        return ['lignes' => $lignes];
    }

    /** @param array{label?: ?string, name?: ?string, codePostal?: ?string, ville?: ?string}|null $proposition */
    private static function propositionAffichable(?array $proposition): string
    {
        if (null === $proposition) {
            return 'Aucun résultat fiable dans la Base Adresse Nationale — adresse à vérifier manuellement.';
        }
        $label = $proposition['label'] ?? null;
        if (null !== $label && '' !== $label) {
            return $label;
        }

        return trim(sprintf(
            '%s %s %s',
            $proposition['name'] ?? '',
            $proposition['codePostal'] ?? '',
            $proposition['ville'] ?? '',
        ));
    }

    /** URL d'une section précise de l'éditeur, toutes gammes. */
    public function urlSection(TypeFiche $type, string $id, int $index): string
    {
        return TypeFiche::Lieu === $type
            ? $this->urls->generate('app_mdm_fiche_lieu', ['id' => $id, 'section' => $index])
            : $this->urls->generate('app_mdm_fiche_gamme', ['gamme' => self::slug($type), 'id' => $id, 'section' => $index]);
    }

    /** URL de la section de l'éditeur qui porte le bloc extraction. */
    public function urlExtraction(TypeFiche $type, string $id): string
    {
        $index = 0;
        foreach (FicheSectionsCatalogue::pour($type) as $i => $section) {
            if (in_array('suggestions', $section['blocs'], true)) {
                $index = $i;
                break;
            }
        }

        return TypeFiche::Lieu === $type
            ? $this->urls->generate('app_mdm_fiche_lieu', ['id' => $id, 'section' => $index])
            : $this->urls->generate('app_mdm_fiche_gamme', ['gamme' => self::slug($type), 'id' => $id, 'section' => $index]);
    }

    /**
     * Le bloc extraction en trois temps : déposer (si rien ne tourne), suivre
     * la lecture en cours, valider les valeurs lues. Une seule extraction à la
     * fois par fiche — le formulaire de dépôt disparaît tant qu'une lecture
     * n'est pas terminée.
     *
     * @return array<string, mixed>
     */
    private function extractionVars(Fiche $fiche): array
    {
        if (!$this->parametres->bool('box.ocr_active')) {
            return ['active' => false, 'en_cours' => null, 'form_depot' => null, 'a_revoir' => null, 'form_revue' => null];
        }
        $id = $fiche->idString();
        $enCours = $this->extractions->enCours($fiche);
        $aRevoir = $this->extractions->aRevoir($fiche);

        return [
            'active' => true,
            'en_cours' => $enCours,
            'form_depot' => null === $enCours
                ? $this->forms->create(OcrUploadType::class, null, [
                    'action' => $this->urls->generate('app_mdm_fiche_extraction_deposer', ['id' => $id]),
                    'category_choices' => $this->ocrCategories->choices($fiche->type()),
                ])->createView()
                : null,
            'a_revoir' => $aRevoir,
            'form_revue' => null !== $aRevoir
                ? $this->ocrRevues->review($aRevoir, $this->urls->generate('app_mdm_fiche_extraction_valider', [
                    'id' => $id,
                    'extractionId' => $aRevoir->id(),
                ]))->createView()
                : null,
        ];
    }

    /**
     * Variables de la gestion des médias (partiels repris des anciennes vues
     * « modifier », construits par les ViewBuilders existants).
     *
     * @param FormInterface<mixed> $form
     *
     * @return array<string, mixed>
     */
    private function medias(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form): array
    {
        if ($entite instanceof Lieu) {
            $vue = $this->lieuVue->form($form, $entite, false);

            return [
                'gamme' => 'lieu',
                'vars' => [
                    'lieu' => $entite,
                    'creation' => false,
                    'photos' => $vue['photos'],
                    'documents' => $vue['documents'],
                    'document_upload_form' => $vue['document_upload_form'],
                    'media_upload_form' => $vue['media_upload_form'],
                    'media_csrf_token' => $vue['media_csrf_token'],
                ],
            ];
        }
        [$vue, $route] = match (true) {
            $entite instanceof Restaurant => [$this->restaurantVue->form($form, $entite, false), 'app_pim_restaurant_document_download'],
            $entite instanceof Activite => [$this->activiteVue->form($form, $entite, false), 'app_pim_activite_document_download'],
            default => [$this->serviceVue->form($form, $entite, false), 'app_pim_service_document_download'],
        };

        return [
            'gamme' => $entite->fiche()->type()->value,
            'vars' => [
                'documents' => $vue['documents'],
                'entite_id' => (string) $entite->id(),
                'download_route' => $route,
            ],
        ];
    }

    public static function slug(TypeFiche $type): string
    {
        return match ($type) {
            TypeFiche::Lieu => 'lieux',
            TypeFiche::Restaurant => 'restaurants',
            TypeFiche::Activite => 'activites',
            TypeFiche::ServiceEvenementiel => 'services',
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de cette version du MDM.'),
        };
    }

    /**
     * Icône du rail pour une section, dérivée de son titre (glyphes du
     * design-system). Repli neutre pour les sections non mappées.
     */
    private static function iconeSection(string $titre): string
    {
        $t = mb_strtolower($titre);

        return match (true) {
            str_contains($t, 'information') => 'info-circle',
            str_contains($t, 'localisation'), str_contains($t, 'accès'), str_contains($t, 'acces') => 'area',
            str_contains($t, 'descript') => 'note',
            str_contains($t, 'héberg'), str_contains($t, 'heberg') => 'bed',
            str_contains($t, 'restaur') => 'utensils',
            str_contains($t, 'salle'), str_contains($t, 'capacit') => 'conference',
            str_contains($t, 'prestation') => 'call-bell',
            str_contains($t, 'service'), str_contains($t, 'équipement'), str_contains($t, 'equipement') => 'gear',
            str_contains($t, 'rse'), str_contains($t, 'engagement') => 'plant',
            str_contains($t, 'loisir'), str_contains($t, 'bien-être'), str_contains($t, 'bien-etre') => 'spa',
            str_contains($t, 'tarif'), str_contains($t, 'formule') => 'currency-euro',
            str_contains($t, 'administratif') => 'list',
            str_contains($t, 'média'), str_contains($t, 'media') => 'images',
            str_contains($t, 'disponibil'), str_contains($t, 'fermeture') => 'calendar',
            str_contains($t, 'collaborateur') => 'users',
            str_contains($t, 'visibilité'), str_contains($t, 'visibilite'), str_contains($t, 'diffusion') => 'rocket',
            str_contains($t, 'suggestion'), str_contains($t, 'historique'), str_contains($t, 'ia') => 'star',
            default => 'note',
        };
    }

    /**
     * Groupe de rail d'une section : « Paramètres » pour les sections de
     * configuration (blocs médias/collaborateurs/diffusion/salesforce/
     * historique), « Ma fiche » pour le contenu éditorial.
     *
     * @param list<string> $blocs
     */
    private static function groupeSection(array $blocs): string
    {
        $configuration = ['medias', 'collaborateurs', 'sites', 'salesforce', 'historique'];

        return array_intersect($blocs, $configuration) !== [] ? 'parametres' : 'ma_fiche';
    }

    /** Domaine des routes d'action existantes (app_pim_<domaine>_submit…). */
    private static function domaine(TypeFiche $type): string
    {
        return match ($type) {
            TypeFiche::Lieu => 'lieu',
            TypeFiche::Restaurant => 'restaurant',
            TypeFiche::Activite => 'activite',
            TypeFiche::ServiceEvenementiel => 'service',
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de cette version du MDM.'),
        };
    }

    /** @return list<string> */
    private function photoAssetIds(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        return match (true) {
            $entite instanceof Lieu => $this->lieux->photoAssetIds($entite),
            $entite instanceof Restaurant => $this->restaurants->photoAssetIds($entite),
            $entite instanceof Activite => $this->activites->photoAssetIds($entite),
            default => $this->services->photoAssetIds($entite),
        };
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<string>         $existing
     */
    private function enregistrer(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form, array $existing): void
    {
        match (true) {
            $entite instanceof Lieu => $this->lieux->save($entite, $form, $existing),
            $entite instanceof Restaurant => $this->restaurants->save($entite, $form, $existing, $this->actor->id()),
            $entite instanceof Activite => $this->activites->save($entite, $form, $existing, $this->actor->id()),
            default => $this->services->save($entite, $form, $existing, $this->actor->id()),
        };
    }

    /** @return array<int, ?int> Complétude par section (null quand la section ne porte aucun champ pondéré). */
    private function completudesParSection(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $type = $entite->fiche()->type();
        $configurations = $this->configurations->activeFor($type);
        $parPropriete = [];
        foreach ($configurations as $configuration) {
            $definition = $this->catalog->find($type, $configuration->fieldCode());
            if (null === $definition) {
                continue;
            }
            $racine = explode('.', $definition->path, 2)[0];
            $parPropriete[$racine][] = $configuration;
        }
        $resultats = [];
        foreach (FicheSectionsCatalogue::pour($type) as $i => $section) {
            $subset = [];
            foreach ($section['proprietes'] as $propriete) {
                $subset = [...$subset, ...($parPropriete[$propriete] ?? [])];
            }
            $resultats[$i] = [] === $subset
                ? null
                : $this->calculator->calculate($entite, $type, $subset)->global;
        }

        return $resultats;
    }
}
