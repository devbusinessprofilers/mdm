<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Entity\VisibiliteGeoRun;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Service\FicheWorkflowManager;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\SiteDiffusionGeoAttribueur;
use App\Pim\Service\VisibiliteGeoJournal;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Section Visibilité de l'éditeur : sélection des sites de diffusion (les
 * obligatoires sont réimposés) et bouton « Appliquer les sites automatiques »
 * (critères géographiques, ajout seul).
 */
final readonly class EditeurSitesDiffusion
{
    private const BOUTON_SOBRE = ['data-variant' => 'outline', 'data-size' => 'md', 'data-full' => '0'];

    public function __construct(
        private FormFactoryInterface $forms,
        private SiteDiffusionRepository $sites,
        private InternalFicheMutationPolicy $policy,
        private FicheWorkflowManager $workflow,
        private SiteDiffusionGeoAttribueur $geoAttribueur,
        private VisibiliteGeoJournal $journalGeo,
    ) {
    }

    /** @return FormInterface<mixed> Sélection des sites de diffusion. */
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
     * Bouton « Appliquer les sites automatiques » : rattache la fiche aux
     * sites dont un critère géographique couvre son adresse. Ajout seul, à
     * la demande — l'attribution automatique ne joue sinon qu'à la création,
     * pour ne pas importuner l'éditeur.
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
}
