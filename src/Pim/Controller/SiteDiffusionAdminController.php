<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Entity\SiteDiffusion;
use App\Pim\Form\SiteDiffusionType;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\SiteDiffusionAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration du référentiel des sites de diffusion. Ce n'est pas une LOV :
 * un site porte un groupe, des mentions (obligatoire, payant) et une
 * présélection par gamme — l'écran vit donc à côté des listes de valeurs.
 */
#[Route('/admin/sites-de-diffusion', name: 'app_pim_site_diffusion_')]
#[IsGranted('ROLE_ADMIN')]
final class SiteDiffusionAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SiteDiffusionRepository $sites): Response
    {
        return $this->render('pim/site_diffusion/index.html.twig', [
            'sites' => $sites->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
        ]);
    }

    #[Route('/ajouter', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, SiteDiffusionAdminManager $manager): Response
    {
        $form = $this->createForm(SiteDiffusionType::class, [
            'position' => 0,
            'actif' => true,
            'obligatoire' => false,
            'payant' => false,
            'gammesParDefaut' => [],
            'criteresGeo' => [],
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            try {
                $site = $manager->creer($data);
                $this->addFlash('success', sprintf('Site « %s » créé.', $site->label()));

                return $this->redirectToRoute('app_pim_site_diffusion_index');
            } catch (\DomainException $exception) {
                $form->get('code')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pim/site_diffusion/form.html.twig', [
            'form' => $form->createView(),
            'site' => null,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        int $id,
        SiteDiffusionRepository $sites,
        SiteDiffusionAdminManager $manager,
    ): Response {
        $site = $sites->find($id);
        if (!$site instanceof SiteDiffusion) {
            throw $this->createNotFoundException('Site de diffusion introuvable.');
        }
        $form = $this->createForm(SiteDiffusionType::class, SiteDiffusionAdminManager::donnees($site), [
            'edition' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $manager->modifier($site, $data);
            $this->addFlash('success', sprintf('Site « %s » mis à jour.', $site->label()));

            return $this->redirectToRoute('app_pim_site_diffusion_index');
        }

        return $this->render('pim/site_diffusion/form.html.twig', [
            'form' => $form->createView(),
            'site' => $site,
        ]);
    }

    /**
     * Suggestions de villes pour les critères géographiques (contrôleur
     * critere-geo) : géocodage Geoapify restreint au niveau ville, borné à un
     * pays. Les coordonnées retournées remplissent les champs cachés du
     * critère — jamais de saisie manuelle.
     */
    #[Route('/ville-autocomplete', name: 'ville_autocomplete', methods: ['GET'])]
    public function villeAutocomplete(Request $request, GeoapifyClient $geocodeur): Response
    {
        $q = trim($request->query->getString('q'));
        $pays = trim($request->query->getString('pays'));
        if (mb_strlen($q) < 2 || 1 !== preg_match('/^[a-zA-Z]{2}$/', $pays)) {
            return $this->json(['suggestions' => []]);
        }
        try {
            $resultats = $geocodeur->autocomplete($q, $pays, 6, type: 'city');
        } catch (\RuntimeException) {
            // L'autocomplétion est un confort : API indisponible = pas de suggestion.
            $resultats = [];
        }
        $suggestions = [];
        foreach ($resultats as $resultat) {
            if (null === $resultat['latitude'] || null === $resultat['longitude']) {
                continue;
            }
            $suggestions[] = [
                'label' => $resultat['label'],
                'latitude' => $resultat['latitude'],
                'longitude' => $resultat['longitude'],
            ];
        }

        return $this->json(['suggestions' => $suggestions]);
    }
}
