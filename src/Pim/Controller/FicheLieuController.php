<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Service\SoumissionSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Éditeur de fiche Lieu par sections, à l'emplacement de la maquette front.
 * Le rail des sections porte la complétude par section ; chaque section
 * enregistre ses seuls champs (soumission partielle du LieuType existant).
 */
final class FicheLieuController extends AbstractController
{
    #[
        Route(
            '/referentiel/lieux/fiche/{id}',
            name: 'app_mdm_fiche_lieu',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET', 'POST'],
        ),
    ]
    public function __invoke(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheEditeurEcran $ecran,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());
        $section = FicheSectionsCatalogue::indexValide(TypeFiche::Lieu, $request->query->getInt('section'));

        $form = $ecran->formSection($lieu);
        $formSites = $ecran->formSites($lieu);
        $formSitesGeo = $ecran->formSitesGeo($lieu);
        $resultat = SoumissionSection::nonSoumise();
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
            if ($request->request->has('sites_geo')) {
                $ajoutes = $ecran->soumettreSitesGeo($request, $lieu, $formSitesGeo);
                if (null !== $ajoutes) {
                    $this->addFlash('success', $ajoutes > 0
                        ? sprintf('%d site(s) ajouté(s) selon les critères géographiques.', $ajoutes)
                        : 'Aucun site à ajouter : la fiche est déjà couverte.');

                    return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $id, 'section' => $section]);
                }
            } elseif ($request->request->has('sites_diffusion')) {
                if ($ecran->soumettreSites($request, $lieu, $formSites)) {
                    $this->addFlash('success', 'Diffusion mise à jour.');

                    return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $id, 'section' => $section]);
                }
            } else {
                $resultat = $ecran->soumettreSection($request, $lieu, $form);
                if ($resultat->estEnregistree()) {
                    $this->addFlash('success', 'Fiche enregistrée.');
                    if ($resultat->depubliee) {
                        $this->addFlash('warning', 'Fiche dépubliée : champs obligatoires vidés — '.implode(', ', $resultat->champsVides).'.');
                    }

                    return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $id, 'section' => $section]);
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
            'fiche' => $lieu->fiche(),
            'form' => $form->createView(),
            'form_sites' => $formSites->createView(),
            'form_sites_geo' => $formSitesGeo->createView(),
            'confirmation_depublication' => $resultat->attendConfirmation() ? $resultat->champsVides : null,
        ] + $ecran->variables($lieu, $section, $form), new Response(null, $soumisInvalide ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
