<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Service\SoumissionSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Éditeur de fiche par sections pour les gammes Restaurant, Activité et
 * Service événementiel — même patron que l'éditeur Lieu. La gamme Traiteur
 * (plateaux repas) est hors de cette version du MDM.
 */
final class FicheGammeController extends AbstractController
{
    #[
        Route(
            '/referentiel/{gamme}/fiche/{id}',
            name: 'app_mdm_fiche_gamme',
            requirements: ['gamme' => 'restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET', 'POST'],
        ),
    ]
    public function __invoke(
        Request $request,
        string $gamme,
        string $id,
        RestaurantRepository $restaurants,
        ActiviteRepository $activites,
        ServiceEvenementielRepository $services,
        FicheEditeurEcran $ecran,
    ): Response {
        $entite = match ($gamme) {
            'restaurants' => $restaurants->find($id),
            'activites' => $activites->find($id),
            default => $services->find($id),
        };
        if (!$entite instanceof Restaurant && !$entite instanceof Activite && !$entite instanceof ServiceEvenementiel) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $type = $entite->fiche()->type();
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        $section = FicheSectionsCatalogue::indexValide($type, $request->query->getInt('section'));

        $form = $ecran->formSection($entite);
        $formSites = $ecran->formSites($entite);
        $formSitesGeo = $ecran->formSitesGeo($entite);
        $resultat = SoumissionSection::nonSoumise();
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
            if ($request->request->has('sites_geo')) {
                $ajoutes = $ecran->soumettreSitesGeo($request, $entite, $formSitesGeo);
                if (null !== $ajoutes) {
                    $this->addFlash('success', $ajoutes > 0
                        ? sprintf('%d site(s) ajouté(s) selon les critères géographiques.', $ajoutes)
                        : 'Aucun site à ajouter : la fiche est déjà couverte.');

                    return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => $gamme, 'id' => $id, 'section' => $section]);
                }
            } elseif ($request->request->has('sites_diffusion')) {
                if ($ecran->soumettreSites($request, $entite, $formSites)) {
                    $this->addFlash('success', 'Diffusion mise à jour.');

                    return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => $gamme, 'id' => $id, 'section' => $section]);
                }
            } else {
                $resultat = $ecran->soumettreSection($request, $entite, $form);
                if ($resultat->estEnregistree()) {
                    $this->addFlash('success', 'Fiche enregistrée.');
                    if ($resultat->depubliee) {
                        $this->addFlash('warning', 'Fiche dépubliée : champs obligatoires vidés — '.implode(', ', $resultat->champsVides).'.');
                    }

                    return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => $gamme, 'id' => $id, 'section' => $section]);
                }
            }
        }

        // 422 : Turbo Drive ignore une réponse 200 à un POST de formulaire —
        // y compris pour la demande de confirmation de dépublication.
        $soumisInvalide = ($form->isSubmitted() && !$form->isValid())
            || $resultat->attendConfirmation()
            || ($formSites->isSubmitted() && !$formSites->isValid())
            || ($formSitesGeo->isSubmitted() && !$formSitesGeo->isValid());

        return $this->render('mdm/fiche_editeur.html.twig', [
            'fiche' => $entite->fiche(),
            'form' => $form->createView(),
            'form_sites' => $formSites->createView(),
            'form_sites_geo' => $formSitesGeo->createView(),
            'confirmation_depublication' => $resultat->attendConfirmation() ? $resultat->champsVides : null,
        ] + $ecran->variables($entite, $section, $form), new Response(null, $soumisInvalide ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
