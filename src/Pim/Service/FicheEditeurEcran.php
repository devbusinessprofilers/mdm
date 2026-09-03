<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Service\CurrentActorProvider;
use App\Audit\Repository\AuditRevisionRepository;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FichePhotoPresenter;
use App\Etl\Repository\FicheSalesforceRepository;
use App\Etl\Service\MarketplaceRetrait;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Pim\Completeness\CompletenessCalculator;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Entity\VisibiliteGeoRun;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ActiviteType;
use App\Pim\Form\AdresseSuggestionFormFactory;
use App\Pim\Form\EnrichissementSuggestionFormFactory;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\LieuPhotoUploadType;
use App\Pim\Form\LieuType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Form\ActionType;
use App\Shared\Service\ParametreProviderInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Écran d'édition de fiche par sections (maquette front), pour les quatre
 * gammes de cette version. Chaque section soumet le formulaire complet
 * existant de la gamme en mode partiel — les champs absents de la section ne
 * sont pas touchés (submit avec clearMissing = false), les champs rendus mais
 * vidés côté client étant réinjectés par ChampsOmisCompleteur. Les mécanismes
 * restent ceux de l'édition classique : AdminManagers, politique de mutation
 * interne, complétude, audit.
 */
final readonly class FicheEditeurEcran
{
    /** Boutons d'en-tête : même gabarit que les liens Extraire/Traductions/Historique. */
    // md : aligné sur les boutons d'en-tête et de barre d'actions de la fiche.
    private const BOUTON_SOBRE = ['data-variant' => 'outline', 'data-size' => 'md', 'data-full' => '0'];

    /** Bouton « Enrichir ce qui manque » : primary + icône IA, comme la maquette. */
    private const BOUTON_ENRICHIR = ['data-variant' => 'primary', 'data-size' => 'md', 'data-full' => '0', 'data-icon' => 'ai'];

    /** Items du menu « Autres actions » : texte pleine largeur, alignés à gauche par le conteneur. */
    private const BOUTON_MENU = ['data-variant' => 'text', 'data-size' => 'md', 'data-full' => '1'];

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
        private \App\Etl\Service\SalesforceCsvSettings $salesforceCsv,
        private AdresseSuggestionFormFactory $adresseSuggestions,
        private FicheSuggestionRepository $enrichissementSuggestionsRepository,
        private EnrichissementSuggestionFormFactory $enrichissementSuggestions,
        private FichePhotoPresenter $fichePhotos,
        private MediaAssetRepository $mediaAssets,
        private CsrfTokenManagerInterface $csrfTokens,
        private FicheRepository $fiches,
        private SiteDiffusionGeoAttribueur $geoAttribueur,
        private VisibiliteGeoJournal $journalGeo,
        private LieuObligationsPublication $obligations,
        private MarketplaceRetrait $retraitMarketplace,
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
     * Fiche publiée dont un champ obligatoire de la bible (gamme Lieu) vient
     * d'être vidé : rien n'est enregistré tant que l'utilisateur n'a pas
     * confirmé (modale de l'éditeur, bouton « Oui, dépublier ») ; confirmé,
     * l'enregistrement dépublie la fiche et la retire de la marketplace. Seuls
     * les manquants nouveaux comptent — les lieux publiés incomplets de longue
     * date s'enregistrent sans question.
     *
     * @param FormInterface<mixed> $form
     */
    public function soumettreSection(Request $request, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form): SoumissionSection
    {
        // Les fichiers déposés (dropzones des sections Médias) arrivent dans
        // $request->files : sans cette fusion, submit() ne voit jamais les
        // uploads et les ignore en silence.
        $data = array_replace_recursive(
            $request->request->all($form->getName()),
            $request->files->all($form->getName()),
        );
        if ([] === $data) {
            return SoumissionSection::nonSoumise();
        }
        // Case décochée, liste multiple vidée, collection sans ligne : le
        // navigateur n'envoie rien, et la soumission partielle ignorerait le
        // champ — la suppression serait perdue sans message.
        $data = ChampsOmisCompleteur::completer($form, $data, $entite->fiche()->type());
        $fiche = $entite->fiche();
        $existing = $this->photoAssetIds($entite);
        $avant = $entite instanceof Lieu ? $this->obligations->manquants($entite) : [];
        $dateAvant = $fiche->updatedAt();

        // Soumission « à blanc » : les setters de l'entité remontent
        // markChanged (statut En cours en mémoire pour un éditeur). Le
        // workflow est figé pour pouvoir re-rendre la page sans transition
        // fantôme quand la confirmation est requise — sans enregistrer(),
        // rien n'est persisté.
        $vides = [];
        $refus = $fiche->preserveWorkflowDuring(function () use ($form, $data, $entite, $fiche, $avant, &$vides): ?SoumissionSection {
            $form->submit($data, false);
            if (!$form->isValid()) {
                return SoumissionSection::invalide();
            }
            if ($entite instanceof Lieu && StatutFiche::Publiee === $fiche->status()) {
                $vides = array_values(array_diff_key($this->obligations->manquants($entite), $avant));
                $bouton = $form->get('confirmerDepublication');
                if ([] !== $vides && !($bouton instanceof ClickableInterface && $bouton->isClicked())) {
                    return SoumissionSection::confirmationRequise($vides);
                }
            }

            return null;
        });
        if (null !== $refus) {
            return $refus;
        }
        // touch() remplace l'objet updatedAt : une soumission sans changement
        // ne doit pas faire repasser la fiche en cours.
        $modifiee = $fiche->updatedAt() !== $dateAvant;
        if ([] !== $vides) {
            // Dépublication confirmée, dans le même flush que l'enregistrement.
            $fiche->unpublishForMissingRequiredFields($vides);
            $this->retraitMarketplace->retirer($fiche);
        }

        return $this->policy->execute($fiche, function () use ($form, $entite, $fiche, $existing, $modifiee, $vides): SoumissionSection {
            // Reproduit la transition que les setters auraient faite hors du
            // gel : touch seul pour un validateur, En cours pour un éditeur.
            if ($modifiee) {
                $fiche->markChanged();
            }
            try {
                $this->enregistrer($entite, $form, $existing);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return SoumissionSection::invalide();
            } catch (FilesystemException) {
                $form->addError(new FormError('Le stockage des médias est temporairement indisponible.'));

                return SoumissionSection::invalide();
            }

            return [] === $vides ? SoumissionSection::enregistree() : SoumissionSection::depubliee($vides);
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
            // Liste plate (l'ordre suit déjà les groupes) : le composant Select
            // du portail ne rend pas les optgroup, et un select multiple reste
            // plus lisible qu'une longue liste de cases.
            $choices[$site->label().$mention] = $site->id();
            if ($site->obligatoire()) {
                $obligatoires[] = $site->id();
            }
        }
        $selection = array_values(array_unique([...$fiche->siteDiffusionIds(), ...$obligatoires]));

        return $this->forms->createNamedBuilder('sites_diffusion', options: ['csrf_token_id' => 'sites-'.$fiche->idString()])
            ->add('sites', ChoiceType::class, [
                // Le titre du bloc porte déjà « Sites de diffusion ».
                'label' => false,
                'required' => false,
                'multiple' => true,
                // Select multiple (composant Select) — les sites obligatoires
                // sont de toute façon réimposés par soumettreSites().
                'expanded' => false,
                'choices' => $choices,
                'data' => $selection,
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer la diffusion'])
            ->getForm();
    }

    /**
     * Bouton « Appliquer les sites automatiques » (section Visibilité) :
     * rattache la fiche aux sites dont un critère géographique couvre son
     * adresse. Ajout seul, à la demande — l'attribution automatique ne joue
     * sinon qu'à la création, pour ne pas importuner l'éditeur.
     *
     * @return FormInterface<mixed>
     */
    public function formSitesGeo(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): FormInterface
    {
        return $this->forms->createNamed('sites_geo', ActionType::class, null, [
            'button_label' => 'Appliquer les sites automatiques',
            'button_attr' => self::BOUTON_SOBRE,
            'csrf_token_id' => 'sites-geo-'.$entite->fiche()->idString(),
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return ?int Nombre de sites ajoutés, null si le formulaire n'est pas soumis
     */
    public function soumettreSitesGeo(Request $request, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form): ?int
    {
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return null;
        }
        $fiche = $entite->fiche();
        $ajoutes = (int) $this->policy->execute($fiche, fn (): int => $this->geoAttribueur->attribuer($fiche));
        // Historique du journal /outils : le clic laisse sa trace même sans
        // effet — l'absence d'effet se lit sinon comme une absence de passage.
        $this->journalGeo->traceFiche(VisibiliteGeoRun::DECLENCHEUR_BOUTON, $fiche, $ajoutes);
        $this->workflow->indexAndFlush($fiche);

        return $ajoutes;
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
        // Compteur de champs des en-têtes de carte (maquette) : une section
        // range souvent un unique sous-formulaire, on compte ses feuilles.
        foreach ($sections as $i => $section) {
            $nb = 0;
            foreach ($section['champs'] as $champ) {
                // Un champ de section peut être une feuille pointée (`groupe.champ`).
                $noeud = $form;
                foreach (explode('.', $champ) as $segment) {
                    if (!$noeud->has($segment)) {
                        continue 2;
                    }
                    $noeud = $noeud->get($segment);
                }
                $nb += self::nbChampsTerminaux($noeud);
            }
            $sections[$i]['nb_champs'] = $nb;
        }
        $parSection = $this->completudesParSection($entite);
        $lienSection = fn (int $i): string => $this->routes->editUrl($type, $fiche->idString(), $i);
        $onglets = [];
        foreach ($sections as $i => $section) {
            $onglets[] = [
                'index' => $i,
                'titre' => $section['titre'],
                'completude' => $parSection[$i],
                'actif' => $i === $index,
                'url' => $lienSection($i),
                'icone' => self::iconeSection($section['titre']),
                'groupe' => $section['groupe'] ?? self::groupeSection($section['blocs']),
            ];
        }
        $section = $sections[$index];
        $id = $fiche->idString();
        $statut = $fiche->status()->value;
        $domaine = self::domaine($type);

        return [
            'onglets' => $onglets,
            'sections' => $sections,
            'section' => $section,
            'section_index' => $index,
            'entete' => [
                'reference' => sprintf('%s-%06d', self::PREFIXES[$type->value] ?? 'FIC', $fiche->code()),
                'version' => $fiche->version(),
                'statut' => $statut,
                'completude' => $entite->completeness(),
                'completude_canaux' => $entite->completenessByChannel(),
                'message_refus' => $fiche->validationFeedback(),
                // Fiche absorbée par une fusion : bandeau avec le lien vers la survivante.
                'fusion' => $this->fusion($fiche),
            ],
            'liens' => [
                // Le dépôt et la revue vivent dans la section extraction de l'éditeur.
                'ocr' => $this->urlExtraction($type, $id),
                'ocr_admin' => $this->urls->generate('app_ocr_index', ['id' => $id]),
                'traductions' => $this->urls->generate('app_enrichment_fiche_translation_show', ['id' => $id]),
                'historique' => $this->routes->historyUrl($type, $id),
            ],
            'actions' => array_filter([
                'submit' => 'en_cours' === $statut ? $this->actions->action($domaine, $id, 'submit', 'Soumettre à validation', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'validate' => 'en_attente_validation' === $statut ? $this->actions->action($domaine, $id, 'validate', 'Valider', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                // Raccourci validateur : les deux transitions en un clic (la
                // publication reste retenue si les photos ne sont pas conformes).
                'validate_and_publish' => 'en_attente_validation' === $statut ? $this->actions->validerPublier($id, self::BOUTON_SOBRE)->createView() : null,
                'reject' => 'en_attente_validation' === $statut ? $this->actions->reject($domaine, $id)->createView() : null,
                'publish' => 'validee' === $statut ? $this->actions->action($domaine, $id, 'publish', 'Publier', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'archive' => 'publiee' === $statut ? $this->actions->action($domaine, $id, 'archive', 'Archiver', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                // Depuis « archivée » : remise en cours ou republication directe.
                'unarchive' => 'archivee' === $statut ? $this->actions->action($domaine, $id, 'unarchive', 'Désarchiver', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'republish' => 'archivee' === $statut ? $this->actions->action($domaine, $id, 'republish', 'Republier', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
            ]),
            // Bouton « Envoyer à Salesforce » (système de transition CSV
            // e-mail) : présent seulement quand la synchro est configurée.
            'salesforce_envoi' => $this->salesforceCsv->isConfigured()
                ? $this->actions->salesforce($id, self::BOUTON_MENU)->createView()
                : null,
            // Bouton « Enrichir ce qui manque » : toujours affiché, les gates
            // par source s'appliquent au traitement (une source inactive est
            // simplement sautée par le scan).
            'action_enrichir' => $this->actions->enrichir($id, self::BOUTON_ENRICHIR)->createView(),
            'action_suppression' => $this->actions->action($domaine, $id, 'delete', 'Supprimer', true, match ($type) {
                TypeFiche::Lieu => 'Supprimer ce lieu ?',
                TypeFiche::Activite => 'Supprimer cette activité ?',
                TypeFiche::Restaurant => 'Supprimer ce restaurant ?',
                default => 'Supprimer ce service ?',
            }, self::BOUTON_MENU)->createView(),
            // Tous les blocs sont calculés quel que soit l'onglet actif : les
            // onglets basculent côté client sans recharger, le gabarit rend
            // chaque bloc dans le volet de sa section.
            'medias' => $this->medias($entite),
            'affiliations' => array_map(
                static fn (array $ligne): array => [
                    'affiliation' => $ligne['affiliation'],
                    'edition' => $ligne['edition']->createView(),
                    'suppression' => $ligne['suppression']->createView(),
                ],
                $this->collaborateurs->formsAffiliations($fiche, $this->affiliations->findBy(['fiche' => $fiche->id()])),
            ),
            'form_invitation' => $this->collaborateurs->formInvitation($fiche)->createView(),
            'extractions' => $this->extractions->history($fiche),
            'extraction' => $this->extractionVars($fiche),
            'historique' => $this->revisions->history($id, null, 3),
            // Données Salesforce en lecture seule (refresh quotidien) ;
            // false = fiche inconnue de Salesforce.
            'salesforce' => $this->salesforce->forFiche($fiche->id()) ?? false,
            'suggestions_attente' => $this->suggestionsAttenteVars($fiche),
            // Pilule « Suggérer » IA des champs de description : active seulement
            // si l'IA est branchée ; le jeton couvre l'endpoint de suggestion.
            'ia_suggestion_active' => $this->parametres->bool('openai.actif'),
            'suggestion_csrf' => $this->csrfTokens->getToken('fiche-suggestion')->getValue(),
            // Bouton « Suggérer les accès » du bloc Accès (Lieu et Restaurant) :
            // le jeton couvre l'endpoint de suggestion d'accès.
            'acces_suggestion_csrf' => $this->csrfTokens->getToken('fiche-acces-suggestion')->getValue(),
            'gamme_slug' => $type->slug(),
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
        // Suggestions génériques (Sirene : établissement cessé, backfill SIRET/TVA ;
        // Geoapify/DATAtourisme/Wikidata à venir) — même gabarit de ligne.
        foreach ($this->enrichissementSuggestionsRepository->findEnAttentePourFiche($fiche) as $suggestion) {
            $lignes[] = [
                'source' => $suggestion->source()->label(),
                'label' => $suggestion->label(),
                'actuel' => $suggestion->valeurActuelle() ?? '',
                'valeur' => $suggestion->valeurProposee() ?? '',
                // Enseigne absente de la LOV : accepter créera l'entrée — le dire.
                'note' => 'lieu_chaine' === $suggestion->champ() && ChaineLovResolution::creeraUneEntree($suggestion->valeurProposee())
                    ? 'Créera une nouvelle entrée dans la liste « Groupe et Chaîne hôtelière ».'
                    : null,
                'confiance' => null === $suggestion->score() ? null : (int) round($suggestion->score() * 100),
                'accepter' => $this->enrichissementSuggestions->action($suggestion->id(), 'accepter')->createView(),
                'ignorer' => $this->enrichissementSuggestions->action($suggestion->id(), 'ignorer')->createView(),
            ];
        }

        return ['lignes' => $lignes];
    }

    /** @return array<string, string> id de salle => nom, pour la barre de rattachement des photos */
    private static function sallesParId(Restaurant $restaurant): array
    {
        $salles = [];
        foreach ($restaurant->salles() as $salle) {
            $salles[$salle->id()] = $salle->nom();
        }

        return $salles;
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
        return $this->routes->editUrl($type, $id, $index);
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

        return $this->routes->editUrl($type, $id, $index);
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
     * « modifier », construits par les ViewBuilders existants). Publique : le
     * bloc est aussi re-rendu seul par FicheMediasBlocController après chaque
     * action média, sans recharger la page.
     *
     * @return array<string, mixed>
     */
    public function medias(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        if ($entite instanceof Lieu) {
            $vue = $this->lieuVue->mediasVars($entite);

            return [
                'gamme' => 'lieu',
                'bloc_url' => $this->urls->generate('app_pim_lieu_medias_bloc', ['id' => $entite->id()]),
                'onglets_actifs' => self::ongletsMediasActifs(TypeFiche::Lieu),
                'vars' => [
                    'lieu' => $entite,
                    'creation' => false,
                    'photos' => $vue['photos'],
                    'documents' => $vue['documents'],
                    'salles' => $vue['salles'],
                    'document_upload_forms' => $vue['document_upload_forms'],
                    'media_upload_form' => $vue['media_upload_form'],
                    'media_csrf_token' => $vue['media_csrf_token'],
                    'photo_usages' => self::photoUsagesInline(),
                ],
            ];
        }
        [$documentsVue, $route] = match (true) {
            $entite instanceof Restaurant => [$this->restaurantVue->documents($entite), 'app_pim_restaurant_document_download'],
            $entite instanceof Activite => [$this->activiteVue->documents($entite), 'app_pim_activite_document_download'],
            default => [$this->serviceVue->documents($entite), 'app_pim_service_document_download'],
        };

        // Même présentation que le lieu : les vignettes réelles des photos
        // (déposées par le portail prestataire) et le poids des documents.
        $assets = [];
        foreach ($this->mediaAssets->findByStringIds(array_map(
            static fn (array $d): string => $d['resource']->damAssetId(),
            $documentsVue,
        )) as $asset) {
            $assets[$asset->id()] = $asset;
        }
        $documents = [];
        foreach ($documentsVue as $document) {
            $documents[] = $document + [
                'asset' => $assets[$document['resource']->damAssetId()] ?? null,
                'onglet' => $document['resource']->documentUsage()?->ongletMedia() ?? 'documents',
            ];
        }

        $slug = $entite->fiche()->type()->slug();

        return [
            'gamme' => $entite->fiche()->type()->value,
            'bloc_url' => $this->urls->generate('app_pim_gamme_medias_bloc', ['gamme' => $slug, 'id' => (string) $entite->id()]),
            'onglets_actifs' => self::ongletsMediasActifs($entite->fiche()->type()),
            'vars' => [
                'photos' => $this->fichePhotos->photos($entite->fiche()),
                // Un Restaurant a ses salles : même catégorie « Salle » et même
                // barre de rattachement que le Lieu dans la galerie.
                'salles' => $entite instanceof Restaurant ? self::sallesParId($entite) : [],
                'documents' => $documents,
                'entite_id' => (string) $entite->id(),
                'download_route' => $route,
                // Galerie gérée comme le Lieu : dépôt AJAX + modales préchargées.
                'gamme_slug' => $slug,
                'media_upload_form' => $this->forms->createNamed('gamme_photo_upload', LieuPhotoUploadType::class, null, [
                    'action' => $this->urls->generate('app_pim_gamme_photo_upload', ['gamme' => $slug, 'id' => (string) $entite->id()]),
                    'method' => 'POST',
                ])->createView(),
                'media_csrf_token' => $this->csrfTokens->getToken('lieu-media-'.$entite->id())->getValue(),
                'photo_usages' => self::photoUsagesInline(),
            ],
        ];
    }

    /**
     * Onglets internes du volet Médias disponibles par gamme — les onglets
     * sans aucun usage documentaire possible sont grisés dans le shell.
     *
     * @return array<string, bool>
     */
    public static function ongletsMediasActifs(TypeFiche $type): array
    {
        return [
            'photos' => true,
            'plans' => in_array($type, [TypeFiche::Lieu, TypeFiche::Restaurant], true),
            'supports' => true,
            'video' => true,
            'documents' => TypeFiche::Lieu === $type,
        ];
    }

    /**
     * Catégories proposées par le select inline sous chaque vignette.
     * « Salle de réunion » fait apparaître la barre de choix de salle sur la
     * photo ; « Plan de salle » reste réservé à la modale de paramètres.
     *
     * @return array<string, string>
     */
    public static function photoUsagesInline(): array
    {
        return array_diff_key(PhotoUsageCatalog::LABELS, ['CONFIG_PLAN_SALLE' => true]);
    }

    /**
     * Bandeau « Fusionnée » d'une fiche absorbée : libellé et lien vers la
     * fiche survivante (lien absent si elle a disparu depuis).
     *
     * @return array{label: string, url: ?string}|null
     */
    private function fusion(Fiche $fiche): ?array
    {
        $survivantId = $fiche->mergedIntoId();
        if (null === $survivantId) {
            return null;
        }
        $survivante = $this->fiches->find($survivantId);
        if (!$survivante instanceof Fiche) {
            return ['label' => (string) $survivantId, 'url' => null];
        }

        return [
            'label' => $survivante->label() ?? sprintf('fiche %d', $survivante->code()),
            'url' => $this->routes->editUrl($survivante->type(), $survivante->idString()),
        ];
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
            // Avant localisation/accès : « Prestation & accessibilité » contient « acces ».
            str_contains($t, 'prestation') => 'call-bell',
            str_contains($t, 'classification') => 'squares-four',
            str_contains($t, 'localisation'), str_contains($t, 'accès'), str_contains($t, 'acces') => 'area',
            str_contains($t, 'descript') => 'note',
            str_contains($t, 'héberg'), str_contains($t, 'heberg') => 'bed',
            str_contains($t, 'restaur') => 'utensils',
            str_contains($t, 'salle'), str_contains($t, 'capacit'), str_contains($t, 'réunion'), str_contains($t, 'reunion') => 'conference',
            str_contains($t, 'thématique'), str_contains($t, 'thematique'), str_contains($t, 'ambiance') => 'confetti',
            str_contains($t, 'facturation'), str_contains($t, 'partenariat') => 'list',
            str_contains($t, 'template'), str_contains($t, 'message') => 'paper-plane',
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

    /**
     * Nombre de champs « terminaux » d'un champ de formulaire : une liste de
     * choix ou une collection comptent pour un, un sous-formulaire pour la
     * somme de ses feuilles.
     *
     * @param FormInterface<mixed> $champ
     */
    private static function nbChampsTerminaux(FormInterface $champ): int
    {
        $type = $champ->getConfig()->getType()->getInnerType();
        if ($type instanceof ChoiceType || null !== $champ->getConfig()->getOption('prototype') || 0 === $champ->count()) {
            return 1;
        }
        $nb = 0;
        foreach ($champ as $enfant) {
            $nb += self::nbChampsTerminaux($enfant);
        }

        return $nb;
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
