<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Service\Editeur\EditeurSitesDiffusion;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheRouteResolver;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Service\SoumissionSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Éditeur de fiche par sections, toutes gammes, à l'emplacement de la
 * maquette front. Le rail des sections porte la complétude par section ;
 * chaque section enregistre ses seuls champs (soumission partielle du
 * formulaire complet de la gamme). Les deux noms de routes historiques sont
 * conservés : app_mdm_fiche_lieu et app_mdm_fiche_gamme.
 */
final class FicheEditeurController extends AbstractController
{
    #[Route('/referentiel/lieux/fiche/{id}', name: 'app_mdm_fiche_lieu', defaults: ['gamme' => 'lieux'], requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    #[Route('/referentiel/{gamme}/fiche/{id}', name: 'app_mdm_fiche_gamme', requirements: ['gamme' => 'restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        string $gamme,
        string $id,
        FicheDetailResolver $details,
        FicheEditeurEcran $ecran,
        EditeurSitesDiffusion $sites,
        FicheRouteResolver $routes,
    ): Response {
        $entite = $details->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        $type = $entite->fiche()->type();
        $section = FicheSectionsCatalogue::indexValide($type, $request->query->getInt('section'));

        $form = $ecran->formSection($entite);
        $formSites = $sites->formSites($entite);
        $formSitesGeo = $sites->formSitesGeo($entite);
        $resultat = SoumissionSection::nonSoumise();
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
            if ($request->request->has('sites_geo')) {
                $ajoutes = $sites->soumettreSitesGeo($request, $entite, $formSitesGeo);
                if (null !== $ajoutes) {
                    $this->addFlash('success', $ajoutes > 0
                        ? sprintf('%d site(s) ajouté(s) selon les critères géographiques.', $ajoutes)
                        : 'Aucun site à ajouter : la fiche est déjà couverte.');

                    return $this->redirect($routes->editUrl($type, $id, $section));
                }
            } elseif ($request->request->has('sites_diffusion')) {
                if ($sites->soumettreSites($request, $entite, $formSites)) {
                    $this->addFlash('success', 'Diffusion mise à jour.');

                    return $this->redirect($routes->editUrl($type, $id, $section));
                }
            } else {
                $resultat = $ecran->soumettreSection($request, $entite, $form);
                if ($resultat->estEnregistree()) {
                    $this->addFlash('success', 'Fiche enregistrée.');
                    if ($resultat->depubliee) {
                        $this->addFlash('warning', 'Fiche dépubliée : champs obligatoires vidés — '.implode(', ', $resultat->champsVides).'.');
                    }

                    return $this->redirect($routes->editUrl($type, $id, $section));
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
