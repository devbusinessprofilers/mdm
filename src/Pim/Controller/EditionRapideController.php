<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\EditionRapideEcran;
use App\Pim\Service\ReferentielListeProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Édition rapide d'une fiche depuis la liste du référentiel : adresse,
 * classification et sites de diffusion, avec navigation vers les fiches
 * voisines du même filtre. Le front en fera une modale ; la page porte déjà
 * tout le fonctionnement.
 */
final class EditionRapideController extends AbstractController
{
    #[Route(
        '/referentiel/fiche/{id}/edition-rapide',
        name: 'app_mdm_edition_rapide',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['GET', 'POST'],
    ),]
    public function __invoke(
        Request $request,
        string $id,
        FicheRepository $fiches,
        EditionRapideEcran $ecran,
        ReferentielListeProvider $provider,
    ): Response {
        $fiche = $fiches->find($id);
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);

        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $localisation = $fiche->localisation() ?? new Localisation();
        $form = $ecran->form($fiche, $localisation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            // Les voisines dépendent de updated_at : les calculer avant
            // l'enregistrement, sinon la fiche éditée remonte en tête de la
            // liste et « suivante » renvoie la fiche précédente.
            $voisines = $provider->voisines($filtres, $id);
            $ecran->enregistrer($fiche, $localisation, $data);
            $this->addFlash('success', 'Fiche mise à jour.');
            // « Enregistrer et passer à la suivante » avance dans le filtre ;
            // « Enregistrer » recharge la même fiche — la réponse doit rester
            // dans la turbo-frame de la modale (jamais vers la liste).
            $boutonSuivante = $form->get('enregistrerSuivante');
            $versSuivante = $boutonSuivante instanceof SubmitButton && $boutonSuivante->isClicked();

            return $this->redirectToRoute('app_mdm_edition_rapide', [
                'id' => $versSuivante && null !== $voisines['suivante'] ? $voisines['suivante'] : $id,
                'f' => $request->query->all('f'),
            ]);
        }

        // 422 : Turbo Drive ignore une réponse 200 à un POST de formulaire.
        return $this->render('mdm/edition_rapide.html.twig', [
            'fiche' => $fiche,
            'form' => $form->createView(),
            'voisines' => $provider->voisines($filtres, $id),
            'parametres_filtre' => ['f' => $request->query->all('f')],
            'adresses_partagees' => $ecran->adressesPartagees($fiche),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
