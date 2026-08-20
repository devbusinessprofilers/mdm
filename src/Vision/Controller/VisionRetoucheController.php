<?php

declare(strict_types=1);

namespace App\Vision\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Form\VisionFormFactory;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Service\ImageEnhancementManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions de l'onglet « Import & retouche » de /medias : lancement sur une
 * sélection de fiches, décision avant/après, relance et retour arrière.
 * Toutes 404 tant que l'IA n'est pas activée (openai.actif).
 */
#[Route('/medias/retouche', name: 'app_mdm_retouche_')]
final class VisionRetoucheController extends AbstractController
{
    #[Route('/lancer', name: 'lancer', methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function lancer(Request $request, VisionFormFactory $forms, ImageEnhancementManager $manager, CurrentActorProvider $actor, ParametreProviderInterface $parametres): RedirectResponse
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        $form = $forms->lancementRetouche();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La sélection de fiches est invalide.');

            return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'import']);
        }
        $fiches = iterator_to_array($form->get('fiches')->getData() ?? [], false);
        try {
            $launched = $manager->launchForFiches($fiches, $actor->id());
            $this->addFlash(
                $launched > 0 ? 'success' : 'error',
                $launched > 0
                    ? sprintf('%d retouche%s placée%s dans la file.', $launched, $launched > 1 ? 's' : '', $launched > 1 ? 's' : '')
                    : 'Aucune photo exploitable : les photos doivent être traitées et sans retouche en cours.',
            );
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'import']);
    }

    #[Route('/{id}/accepter', name: 'accepter', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function accepter(string $id, Request $request, ImageEnhancementManager $manager, CurrentActorProvider $actor, ParametreProviderInterface $parametres): RedirectResponse
    {
        $this->garde($parametres, $request, 'vision-retouche-accepter-'.$id);
        try {
            $manager->accept($id, $actor->id());
            $this->addFlash('success', 'Retouche acceptée : les variantes vont être régénérées et la fiche resynchronisée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    #[Route('/{id}/rejeter', name: 'rejeter', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function rejeter(string $id, Request $request, ImageEnhancementManager $manager, CurrentActorProvider $actor, ParametreProviderInterface $parametres): RedirectResponse
    {
        $this->garde($parametres, $request, 'vision-retouche-rejeter-'.$id);
        try {
            $manager->reject($id, $actor->id());
            $this->addFlash('success', 'Retouche rejetée : la version proposée a été supprimée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    #[Route('/{id}/relancer', name: 'relancer', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function relancer(string $id, Request $request, ImageEnhancementManager $manager, ParametreProviderInterface $parametres): RedirectResponse
    {
        $this->garde($parametres, $request, 'vision-retouche-relancer-'.$id);
        try {
            $manager->retry($id);
            $this->addFlash('success', 'La retouche a été replacée dans la file.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    #[Route('/{id}/revenir', name: 'revenir', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function revenir(string $id, Request $request, ImageEnhancementRepository $enhancements, ImageEnhancementManager $manager, ParametreProviderInterface $parametres): RedirectResponse
    {
        $this->garde($parametres, $request, 'vision-retouche-revenir-'.$id);
        $enhancement = $enhancements->find($id);
        if (!$enhancement instanceof ImageEnhancement) {
            throw $this->createNotFoundException('Retouche introuvable.');
        }
        try {
            $manager->revert($enhancement->media()->id());
            $this->addFlash('success', 'Retour à l’original : les variantes vont être régénérées.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    /** Prévisualisation « après » : URL S3 privée temporaire, jamais publique. */
    #[Route('/{id}/apercu', name: 'apercu', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function apercu(string $id, ImageEnhancementRepository $enhancements, PrivateObjectStorageInterface $storage, ParametreProviderInterface $parametres): RedirectResponse
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        $enhancement = $enhancements->find($id);
        $key = $enhancement instanceof ImageEnhancement ? $enhancement->resultStorageKey() : null;
        if (null === $key) {
            throw $this->createNotFoundException('Cette retouche n’a pas de résultat à prévisualiser.');
        }

        return new RedirectResponse($storage->temporaryUrl($key, new \DateTimeImmutable('+15 minutes')));
    }

    public function garde(ParametreProviderInterface $parametres, Request $request, string $csrfTokenId): void
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid($csrfTokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    public function retour(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'import', 'page' => $request->query->getInt('page', 1)]);
    }
}
