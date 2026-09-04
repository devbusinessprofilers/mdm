<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Etl\Service\MarketplaceRetrait;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Form\ActiviteType;
use App\Pim\Form\LieuType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Service\Editeur\EditeurEntete;
use App\Pim\Service\Editeur\EditeurExtraction;
use App\Pim\Service\Editeur\EditeurMedias;
use App\Pim\Service\Editeur\EditeurNavigation;
use App\Pim\Service\Editeur\EditeurSuggestionsAttente;
use App\Shared\Service\ParametreProviderInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Écran d'édition de fiche par sections (maquette front), pour les quatre
 * gammes de cette version. Chaque section soumet le formulaire complet
 * existant de la gamme en mode partiel — les champs absents de la section ne
 * sont pas touchés (submit avec clearMissing = false), les champs rendus mais
 * vidés côté client étant réinjectés par ChampsOmisCompleteur. Les mécanismes
 * restent ceux de l'édition classique : FicheAdminManager, politique de
 * mutation interne, complétude, audit.
 *
 * Les blocs du gabarit sont fournis par les services de Editeur\ (navigation,
 * en-tête, médias, suggestions en attente, extraction) ; variables() les
 * assemble. La section Visibilité passe par EditeurSitesDiffusion.
 */
final readonly class FicheEditeurEcran
{
    public function __construct(
        private FormFactoryInterface $forms,
        private FicheAdminManager $admin,
        private InternalFicheMutationPolicy $policy,
        private LieuObligationsPublication $obligations,
        private MarketplaceRetrait $retraitMarketplace,
        private FicheAffiliationRepository $affiliations,
        private FicheCollaborateursEcran $collaborateurs,
        private ParametreProviderInterface $parametres,
        private CsrfTokenManagerInterface $csrfTokens,
        private EditeurNavigation $navigation,
        private EditeurEntete $entete,
        private EditeurMedias $medias,
        private EditeurSuggestionsAttente $suggestions,
        private EditeurExtraction $extraction,
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
        $existing = $this->admin->photoAssetIds($entite);
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
                $this->admin->save($entite, $form, $existing);
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

    /**
     * Variables du gabarit hors formulaires de la section. Tous les blocs
     * sont calculés quel que soit l'onglet actif : les onglets basculent côté
     * client sans recharger, le gabarit rend chaque bloc dans le volet de sa
     * section.
     *
     * @param FormInterface<mixed> $form Le formulaire complet de la gamme (compteur de champs des sections)
     *
     * @return array<string, mixed>
     */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, int $index, FormInterface $form): array
    {
        $fiche = $entite->fiche();

        return $this->navigation->variables($entite, $index, $form)
            + $this->entete->variables($entite)
            + $this->extraction->variables($fiche)
            + [
                'medias' => $this->medias->variables($entite),
                'affiliations' => array_map(
                    static fn (array $ligne): array => [
                        'affiliation' => $ligne['affiliation'],
                        'edition' => $ligne['edition']->createView(),
                        'suppression' => $ligne['suppression']->createView(),
                    ],
                    $this->collaborateurs->formsAffiliations($fiche, $this->affiliations->findBy(['fiche' => $fiche->id()])),
                ),
                'form_invitation' => $this->collaborateurs->formInvitation($fiche)->createView(),
                'suggestions_attente' => $this->suggestions->variables($fiche),
                // Pilule « Suggérer » IA des champs de description : active seulement
                // si l'IA est branchée ; le jeton couvre l'endpoint de suggestion.
                'ia_suggestion_active' => $this->parametres->bool('openai.actif'),
                'suggestion_csrf' => $this->csrfTokens->getToken('fiche-suggestion')->getValue(),
                // Bouton « Suggérer les accès » du bloc Accès (Lieu et Restaurant) :
                // le jeton couvre l'endpoint de suggestion d'accès.
                'acces_suggestion_csrf' => $this->csrfTokens->getToken('fiche-acces-suggestion')->getValue(),
                'gamme_slug' => $fiche->type()->slug(),
            ];
    }
}
